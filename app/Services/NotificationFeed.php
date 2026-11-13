<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What the notification bell shows, for the web top bar
 * (components/notification-bell) and the mobile app (Api\NotificationController).
 *
 * Computed from existing tables - no schema change, no background workers -
 * and only for the business that is open: a team member invited to one of the
 * owner's businesses must not see the other's customers here, and an owner
 * with two businesses sees each one's alerts in its own place.
 */
class NotificationFeed
{
    /**
     * @return array{overdue: Collection, recentPayments: Collection, recentFinalized: Collection,
     *               overdueCount: int, totalCount: int, hasUnseen: bool}
     */
    public function build(User $user): array
    {
        $companyId = $user->ensureCompany()->id;

        // 1. ACTION NEEDED - overdue, unpaid invoices.
        $overdue = Invoice::query()
            ->where('user_id', $user->id)->where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->where('balance', '>', 0)
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'invoice_number', 'customer_id', 'due_date', 'balance']);

        // 2. RECENT - payments received in the last 7 days.
        $recentPayments = Payment::query()
            ->where('user_id', $user->id)->where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['invoice:id,invoice_number'])
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'invoice_id', 'amount', 'method', 'received_at', 'created_at']);

        // 3. RECENT - invoices finalized in the last 24 h. Useful "what did I do
        // this morning" peek when a user opens the app on their phone.
        $recentFinalized = Invoice::query()
            ->where('user_id', $user->id)->where('company_id', $companyId)
            ->where('finalized_at', '>=', now()->subDay())
            ->with('customer:id,name')
            ->latest('finalized_at')
            ->limit(5)
            ->get(['id', 'invoice_number', 'customer_id', 'finalized_at', 'grand_total']);

        $overdueCount = $overdue->count();

        // Is there anything here the user has not already looked at? Overdue is
        // deliberately not part of this: a bill that is still overdue after you
        // have looked is not old news, and its red badge stays.
        $latestActivity = collect([
            $recentPayments->max('created_at'),
            $recentFinalized->max('finalized_at'),
        ])->filter()->max();

        return [
            'overdue' => $overdue,
            'recentPayments' => $recentPayments,
            'recentFinalized' => $recentFinalized,
            'overdueCount' => $overdueCount,
            'totalCount' => $overdueCount + $recentPayments->count() + $recentFinalized->count(),
            'hasUnseen' => $latestActivity !== null
                && ($user->notifications_seen_at === null || $latestActivity->gt($user->notifications_seen_at)),
        ];
    }

    /** Clears the bell's "new activity" dot (not the overdue badge). */
    public function markSeen(User $user): void
    {
        $user->forceFill(['notifications_seen_at' => now()])->save();
    }
}
