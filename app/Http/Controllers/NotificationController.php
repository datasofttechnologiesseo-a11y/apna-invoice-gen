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
        $request->user()->forceFill(['notifications_seen_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
