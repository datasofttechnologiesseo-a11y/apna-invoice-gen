<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Token-based auth for the mobile app (Laravel Sanctum personal access tokens).
 *
 * The web app stays session/Breeze based; this controller is a parallel,
 * stateless entry point. Captcha (Turnstile) is intentionally NOT enforced
 * here — the mobile client can't render the widget — so keep these routes
 * behind throttling instead.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // Strip spaces/dashes before validating so "98765 43210" and
        // "+91-98765-43210" pass like their compact forms do.
        if (filled($request->phone)) {
            $request->merge(['phone' => preg_replace('/[\s\-()]/', '', (string) $request->phone)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Mandatory 10-digit Indian mobile — same policy as web signup, so
            // the team can reach every new user regardless of channel. Accepts
            // "9876543210", "+919876543210" or "09876543210" style input.
            'phone' => ['required', 'string', 'regex:/^(\+?91|0)?[6-9]\d{9}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.required' => 'Please enter your mobile number so we can help you get started.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // Normalised to +91XXXXXXXXXX, matching the web registration flow.
            'phone' => '+91' . substr(preg_replace('/\D/', '', $data['phone']), -10),
            'password' => $data['password'], // hashed via cast
        ]);

        // Make sure the user has a company context from day one, matching the
        // web onboarding flow's guarantee.
        $user->ensureCompany();

        return $this->tokenResponse($user, $data['device_name'] ?? 'mobile', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? 'mobile');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified' => $user->hasVerifiedEmail(),
                'is_super_admin' => $user->isSuperAdmin(),
                'active_company_id' => $user->active_company_id,
            ],
            'active_company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'gstin' => $company->gstin,
                'is_onboarded' => $company->isOnboarded(),
                'composition_dealer' => (bool) $company->composition_dealer,
                'default_currency' => $company->default_currency,
            ] : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token used for this request.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName)->plainTextToken;
        $company = $user->ensureCompany();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified' => $user->hasVerifiedEmail(),
                'is_super_admin' => $user->isSuperAdmin(),
                'active_company_id' => $user->active_company_id,
            ],
            'active_company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'is_onboarded' => $company->isOnboarded(),
            ] : null,
        ], $status);
    }
}
