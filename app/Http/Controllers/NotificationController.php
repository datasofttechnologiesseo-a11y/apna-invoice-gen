<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark the notification bell as read.
     *
     * Fired when the user opens the dropdown. Only the informational dot is
     * cleared by this - the red overdue badge is not a notification, it is a
     * count of invoices that are still overdue after you have looked, and it
     * stays until the money arrives.
     */
    public function seen(Request $request): JsonResponse
    {
        app(\App\Services\NotificationFeed::class)->markSeen($request->user());

        return response()->json(['ok' => true]);
    }
}
