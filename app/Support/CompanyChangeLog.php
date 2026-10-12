<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Support\Carbon;

/**
 * Activity-log entries for a change to a business's settings, written from
 * the attributes filled on the model but not saved yet. The same entries as
 * the web settings page (CompanyController::recordChanges), so a change made
 * from the mobile app is just as traceable:
 *
 *  - company.books_lock_changed  the books lock moved (opens or closes a period)
 *  - company.bank_updated        the bank details printed on every invoice;
 *                                account numbers masked to the last four digits
 *  - company.updated             everything else
 */
final class CompanyChangeLog
{
    private const BANK_FIELDS = [
        'bank_name' => 'bank name', 'bank_account_name' => 'payee name', 'bank_account_number' => 'account number',
        'bank_account_type' => 'account type', 'bank_ifsc' => 'IFSC', 'bank_branch' => 'branch', 'upi_id' => 'UPI ID',
    ];

    private const LABELS = [
        'name' => 'business name', 'gstin' => 'GSTIN', 'pan' => 'PAN', 'composition_dealer' => 'composition scheme',
        'lut_number' => 'LUT number', 'address_line1' => 'address', 'address_line2' => 'address', 'city' => 'city',
        'state_id' => 'state', 'postal_code' => 'PIN code', 'country' => 'country', 'phone' => 'phone', 'email' => 'email',
        'website' => 'website', 'default_terms' => 'terms', 'declaration' => 'declaration',
        'invoice_prefix' => 'invoice prefix', 'invoice_number_padding' => 'number padding',
        'invoice_number_format' => 'number format',
    ];

    /** Call after fill() and before save(). */
    public static function record(Company $company): void
    {
        $bank = [];
        $other = [];
        $lock = null;
        foreach (array_keys($company->getDirty()) as $field) {
            $old = self::plain($company->getOriginal($field));
            $new = self::plain($company->getAttribute($field));
            if ((string) $old === (string) $new) {
                continue;
            }
            if ($field === 'books_locked_until') {
                $lock = [$old, $new];
            } elseif (isset(self::BANK_FIELDS[$field])) {
                $bank[$field] = $field === 'bank_account_number' ? [self::mask($old), self::mask($new)] : [$old, $new];
            } elseif (isset(self::LABELS[$field])) {
                $other[$field] = [$old, $new];
            }
        }

        if ($other) {
            $what = collect(array_keys($other))->map(fn ($f) => self::LABELS[$f])->unique()->implode(', ');
            AuditLog::record('company.updated', "{$company->name}: {$what}", $company, $other);
        }
        if ($bank) {
            $what = collect(array_keys($bank))->map(fn ($f) => self::BANK_FIELDS[$f])->unique()->implode(', ');
            AuditLog::record('company.bank_updated', "{$company->name}: {$what}", $company, $bank);
        }
        if ($lock) {
            AuditLog::record(
                'company.books_lock_changed',
                $lock[1] ? "{$company->name}: books locked up to " . Carbon::parse($lock[1])->format('d M Y') : "{$company->name}: books unlocked",
                $company,
                ['books_locked_until' => $lock],
            );
        }
    }

    private static function plain($value)
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private static function mask($value)
    {
        return $value === null || $value === '' ? $value : 'ending ' . substr((string) $value, -4);
    }
}
