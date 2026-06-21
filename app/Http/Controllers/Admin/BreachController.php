<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataBreach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Personal-data breach register (DPDP §8(6)). Lets the operator record an
 * incident and track the two statutory notifications — to the Data Protection
 * Board and to affected data principals — within the 72-hour expectation.
 */
class BreachController extends Controller
{
    public function index(): View
    {
        $breaches = DataBreach::with('logger:id,name')
            ->orderByDesc('discovered_at')
            ->paginate(20);

        return view('admin.breaches.index', compact('breaches'));
    }

    public function create(): View
    {
        return view('admin.breaches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBreach($request);
        $data['logged_by'] = $request->user()->id;

        $breach = DataBreach::create($data);

        return redirect()
            ->route('admin.breaches.show', $breach)
            ->with('status', 'Breach logged. Notify the Board and affected users within 72 hours.');
    }

    public function show(DataBreach $breach): View
    {
        $breach->load('logger:id,name');

        return view('admin.breaches.show', compact('breach'));
    }

    public function update(Request $request, DataBreach $breach): RedirectResponse
    {
        $data = $this->validateBreach($request);
        $breach->update($data);

        return back()->with('status', 'Breach record updated.');
    }

    /**
     * Stamp a statutory notification milestone (Board or affected users).
     */
    public function notify(Request $request, DataBreach $breach): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'in:board,users'],
        ]);

        if ($data['target'] === 'board') {
            $breach->forceFill(['board_notified_at' => now()]);
            $message = 'Recorded: Data Protection Board notified.';
        } else {
            $breach->forceFill(['users_notified_at' => now()]);
            $message = 'Recorded: affected users notified.';
        }

        // Once both notifications are in, advance an open/contained breach to reported.
        if ($breach->board_notified_at && $breach->users_notified_at
            && in_array($breach->status, ['open', 'contained'], true)) {
            $breach->status = 'reported';
        }

        $breach->save();

        return back()->with('status', $message);
    }

    private function validateBreach(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'in:' . implode(',', DataBreach::STATUSES)],
            'severity' => ['required', 'in:' . implode(',', DataBreach::SEVERITIES)],
            'occurred_at' => ['nullable', 'date'],
            'discovered_at' => ['required', 'date'],
            'affected_users' => ['required', 'integer', 'min:0'],
            'data_categories' => ['nullable', 'string', 'max:2000'],
            'remediation' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
