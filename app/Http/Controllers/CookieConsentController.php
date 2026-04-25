<?php

namespace App\Http\Controllers;

use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookieConsentController extends Controller
{
    /**
     * Persist a signed-in user's cookie choice to the audit log. For anonymous
     * visitors the choice stays in localStorage (no user_id to attach it to).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'analytics' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
        ]);

        $userId = $request->user()?->id;

        UserConsent::record($userId, 'cookies_analytics', (bool) $data['analytics'], 'banner', $request);
        UserConsent::record($userId, 'cookies_marketing', (bool) $data['marketing'], 'banner', $request);

        return response()->json(['ok' => true]);
    }
}
