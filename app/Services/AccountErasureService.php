<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DPDP Act §12 right to erasure, reconciled with the statutory GST record
 * retention duty (CGST Act §36 — 72 months) that is the very reason the
 * invoice foreign keys use restrictOnDelete.
 *
 * Two paths:
 *  - No statutory records → full hard delete of the user and everything they
 *    own, in foreign-key dependency order. Genuine erasure.
 *  - Statutory records exist → depersonalise: hard-delete every non-statutory
 *    record (DRAFT/non-GST invoices, quotations, products, referrals, activity
 *    logs, now-orphaned customers), scrub the login identity so the account can
 *    never be used or re-identified, and retain only the GST books for the
 *    retention window. The scrubbed shell is purged later by PurgeErasedAccounts
 *    once the books age out.
 *
 * A "GST invoice" is one that has been finalised (finalized_at set). It cannot
 * be deleted — only cancelled — so a cancelled invoice keeps finalized_at and
 * is still retained. Drafts (finalized_at null) are not tax documents, carry no
 * retention duty, and are erased outright.
 *
 * DPDP §8(7) expressly permits retaining personal data where another law
 * requires it, so the depersonalise path is compliant — we keep only what GST
 * law compels and nothing more.
 */
class AccountErasureService
{
    /**
     * @return array{mode: string, retained_invoices: int}
     */
    public function erase(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $hasStatutoryRecords = $user->invoices()->whereNotNull('finalized_at')->exists();

            if ($hasStatutoryRecords) {
                $retained = $this->depersonalise($user);

                Log::info('DPDP erasure (depersonalise + retain)', [
                    'user_id' => $user->id,
                    'retained_invoices' => $retained,
                ]);

                return ['mode' => 'depersonalised', 'retained_invoices' => $retained];
            }

            $this->hardDelete($user);

            Log::info('DPDP erasure (full hard delete)', ['user_id' => $user->id]);

            return ['mode' => 'deleted', 'retained_invoices' => 0];
        });
    }

    /**
     * Final purge of a depersonalised shell once its retained GST records have
     * aged out of the retention window. Called by PurgeErasedAccounts.
     */
    public function purge(User $user): void
    {
        DB::transaction(fn () => $this->hardDelete($user));

        Log::info('DPDP erasure (post-retention purge)', ['user_id' => $user->id]);
    }

    /**
     * Full erasure. Delete child→parent so the restrictOnDelete foreign keys on
     * invoices/quotations/customers never block the wipe. DB-level cascades
     * take the line items, payments and credit notes with their parents.
     */
    private function hardDelete(User $user): void
    {
        Invoice::where('user_id', $user->id)->delete();      // → items, payments, credit notes
        Quotation::where('user_id', $user->id)->delete();    // → quotation items
        CashMemo::where('user_id', $user->id)->delete();     // → memo items
        Expense::where('user_id', $user->id)->delete();
        Product::where('user_id', $user->id)->delete();
        Customer::where('user_id', $user->id)->delete();     // now unreferenced
        Referral::where('referrer_user_id', $user->id)
            ->orWhere('referee_user_id', $user->id)->delete();
        AuditLog::where('user_id', $user->id)->delete();
        Company::where('user_id', $user->id)->delete();      // now unreferenced

        // Detach active company so the FK doesn't block the user delete, then
        // remove the row. user_consents cascade away automatically.
        $user->forceFill(['active_company_id' => null])->save();
        $user->delete();
    }

    /**
     * Keep the GST books; destroy everything else and the personal identity.
     */
    private function depersonalise(User $user): int
    {
        $retained = $user->invoices()->whereNotNull('finalized_at')->count();

        // Non-statutory data we are under no legal duty to keep. Draft (non-GST)
        // invoices are not tax documents, so they are erased; their line items
        // and any payments cascade. Finalised (and cancelled) GST invoices stay.
        Invoice::where('user_id', $user->id)->whereNull('finalized_at')->delete();
        Quotation::where('user_id', $user->id)->delete();
        Product::where('user_id', $user->id)->delete();
        Referral::where('referrer_user_id', $user->id)
            ->orWhere('referee_user_id', $user->id)->delete();
        AuditLog::where('user_id', $user->id)->delete();

        // Drop customers no longer attached to any retained GST invoice — keeping
        // their personal data once the only references (drafts/quotations) are
        // gone would have no lawful basis (DPDP data minimisation). Customers on
        // retained invoices stay, as the tax record needs the recipient details.
        $retainedCustomerIds = Invoice::where('user_id', $user->id)
            ->whereNotNull('finalized_at')
            ->pluck('customer_id')
            ->unique()
            ->all();
        Customer::where('user_id', $user->id)
            ->whereNotIn('id', $retainedCustomerIds)
            ->delete();

        // Scrub the login identity. A random unguessable password + a
        // non-routable email mean the account can never be accessed or matched
        // back to a person, while the FK from retained invoices stays intact.
        $user->forceFill([
            'name' => 'Erased account',
            'email' => 'erased+' . $user->id . '@erased.invalid',
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'email_verified_at' => null,
            'is_super_admin' => false,
            'auto_backup_enabled' => false,
            'referral_code' => null,
            'referred_by_user_id' => null,
            'erased_at' => now(),
        ])->save();

        // Audit the erasure decision in the immutable consent log.
        UserConsent::record($user->id, 'marketing', false, 'erasure');
        UserConsent::record($user->id, 'account_erasure', true, 'erasure');

        return $retained;
    }
}
