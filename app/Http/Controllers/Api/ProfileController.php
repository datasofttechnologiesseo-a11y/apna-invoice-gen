<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AccountErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * JSON API for the user's own profile — update name/email, change password,
 * view the company audit trail, and delete the account. Mirrors the web
 * ProfileController + Breeze password update.
 */
class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function activity(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();

        $logs = AuditLog::where('company_id', $company->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json([
            'data' => collect($logs->items())->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                // The audit row keeps its text in `summary` (the web prints the same).
                'description' => $log->summary,
                'user_name' => $log->user?->name,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Close the account, exactly as the web does: AccountErasureService
     * deletes everything when no GST invoice was issued, otherwise keeps
     * the GST books and erases the person. Every token is revoked either way.
     */
    public function destroy(Request $request, AccountErasureService $eraser): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $result = $eraser->erase($request->user());

        return response()->json([
            'message' => $result['mode'] === 'deleted'
                ? 'Account deleted.'
                : 'Account closed and personal data erased. GST invoice records are kept only as long as tax law requires, then deleted automatically.',
            'mode' => $result['mode'],
        ]);
    }
}
