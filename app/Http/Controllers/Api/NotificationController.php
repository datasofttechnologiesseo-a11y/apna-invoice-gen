<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The notification bell in the mobile app: the same feed as the web top bar
 * (NotificationFeed) - overdue invoices, payments received in the last
 * seven days and invoices issued in the last 24 hours, for the open business.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationFeed $feed): JsonResponse
    {
        $f = $feed->build($request->user());

        return response()->json([
            'overdue_count' => $f['overdueCount'],
            'total_count' => $f['totalCount'],
            // The "new activity" dot; cleared by POST /api/notifications/seen.
            // Overdue is not part of it: its badge stays until the money arrives.
            'has_unseen' => $f['hasUnseen'],
            'overdue' => $f['overdue']->map(fn ($inv) => [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name,
                'due_date' => $inv->due_date?->toDateString(),
                'days_overdue' => $inv->due_date ? (int) $inv->due_date->diffInDays(today()) : null,
                'balance' => (float) $inv->balance,
            ])->values(),
            'recent_payments' => $f['recentPayments']->map(fn ($p) => [
                'payment_id' => $p->id,
                'invoice_id' => $p->invoice_id,
                'invoice_number' => $p->invoice?->invoice_number,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'received_at' => $p->received_at?->toDateString(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values(),
            'recent_invoices' => $f['recentFinalized']->map(fn ($inv) => [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name,
                'grand_total' => (float) $inv->grand_total,
                'finalized_at' => $inv->finalized_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Clears the "new activity" dot (not the overdue badge). */
    public function seen(Request $request, NotificationFeed $feed): JsonResponse
    {
        $feed->markSeen($request->user());

        return response()->json(['ok' => true]);
    }
}
