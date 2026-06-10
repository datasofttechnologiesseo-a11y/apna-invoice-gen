<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BackupMail;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * JSON API for self-service data backups. Mirrors the web BackupController:
 * download a full ZIP of the user's data, email it, or toggle weekly auto-backup.
 */
class BackupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'auto_backup_enabled' => (bool) $user->auto_backup_enabled,
            'last_backup_sent_at' => optional($user->last_backup_sent_at)->toIso8601String(),
        ]);
    }

    public function download(Request $request, BackupService $service): BinaryFileResponse
    {
        $path = $service->buildZipForUser($request->user());
        $filename = 'apna-invoice-backup-' . now()->format('Y-m-d') . '.zip';

        return response()->download($path, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function emailNow(Request $request, BackupService $service): JsonResponse
    {
        $user = $request->user();
        $path = $service->buildZipForUser($user);

        try {
            Mail::to($user->email)->send(new BackupMail($user, $path));
            $user->forceFill(['last_backup_sent_at' => now()])->save();

            return response()->json(['message' => "Backup emailed to {$user->email}."]);
        } catch (\Throwable $e) {
            Log::error('Manual backup email failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not send backup email right now.'], 422);
        } finally {
            @unlink($path);
        }
    }

    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_backup_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill(['auto_backup_enabled' => (bool) $data['auto_backup_enabled']])->save();

        return response()->json([
            'auto_backup_enabled' => (bool) $user->auto_backup_enabled,
            'message' => $user->auto_backup_enabled
                ? "Auto-backup ON. You'll get a weekly ZIP by email."
                : 'Auto-backup OFF.',
        ]);
    }
}
