<?php

namespace App\Http\Controllers;

use App\Mail\BackupMail;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(Request $request): View
    {
        return view('backups.index', [
            'user' => $request->user(),
        ]);
    }

    public function download(Request $request, BackupService $service): BinaryFileResponse
    {
        $path = $service->buildZipForUser($request->user());
        $filename = 'apna-invoice-backup-' . now()->format('Y-m-d') . '.zip';
        // deleteFileAfterSend: Laravel will unlink the temp file once streaming is done.
        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function emailNow(Request $request, BackupService $service): RedirectResponse
    {
        $user = $request->user();
        $path = $service->buildZipForUser($user);

        try {
            Mail::to($user->email)->send(new BackupMail($user, $path));
            $user->forceFill(['last_backup_sent_at' => now()])->save();
            return back()->with('status', "Backup emailed to {$user->email}.");
        } catch (\Throwable $e) {
            Log::error('Manual backup email failed: ' . $e->getMessage());
            return back()->withErrors(['backup' => 'Could not send backup email: ' . $e->getMessage()]);
        } finally {
            @unlink($path);
        }
    }

    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_backup_enabled' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $user->forceFill(['auto_backup_enabled' => (bool) $data['auto_backup_enabled']])->save();
        return back()->with('status', $user->auto_backup_enabled
            ? 'Auto-backup turned ON. You\'ll get a weekly ZIP by email.'
            : 'Auto-backup turned OFF.');
    }
}
