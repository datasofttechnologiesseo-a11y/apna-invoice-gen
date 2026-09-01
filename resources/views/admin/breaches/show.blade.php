<x-layouts.admin :title="$breach->title" subtitle="Breach record">

    @php
        $deadline = $breach->discovered_at->copy()->addHours(config('legal.breach_report_hours', 72));
    @endphp

    <div class="max-w-3xl space-y-6">

        {{-- 72-hour notification tracker --}}
        <div @class([
            'rounded-xl border p-5',
            'bg-danger-50 border-danger-200' => $breach->notificationOverdue(),
            'bg-money-50 border-money-200' => ! $breach->notificationOverdue() && $breach->board_notified_at && $breach->users_notified_at,
            'bg-accent-50 border-accent-200' => ! $breach->notificationOverdue() && ! ($breach->board_notified_at && $breach->users_notified_at),
        ])>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-bold text-gray-900">Statutory notifications (DPDP §8(6))</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Notify the Data Protection Board and affected data principals by
                        <strong>{{ $deadline->format('d M Y, H:i') }}</strong>
                        ({{ config('legal.breach_report_hours', 72) }}h from discovery).
                    </p>
                </div>
                @if ($breach->notificationOverdue())
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-danger-600 text-white">Overdue</span>
                @endif
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-3">
                {{-- Board --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-400">Data Protection Board</div>
                    @if ($breach->board_notified_at)
                        <div class="mt-1 text-sm font-semibold text-money-700">Notified {{ $breach->board_notified_at->format('d M Y, H:i') }}</div>
                    @else
                        <form method="POST" action="{{ route('admin.breaches.notify', $breach) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="target" value="board">
                            <button class="px-3 py-1.5 bg-gray-900 text-white rounded-md text-xs font-semibold hover:bg-gray-700">Mark Board notified</button>
                        </form>
                    @endif
                </div>
                {{-- Users --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-400">Affected users</div>
                    @if ($breach->users_notified_at)
                        <div class="mt-1 text-sm font-semibold text-money-700">Notified {{ $breach->users_notified_at->format('d M Y, H:i') }}</div>
                    @else
                        <form method="POST" action="{{ route('admin.breaches.notify', $breach) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="target" value="users">
                            <button class="px-3 py-1.5 bg-gray-900 text-white rounded-md text-xs font-semibold hover:bg-gray-700">Mark users notified</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Severity</dt><dd class="mt-1 font-semibold text-gray-900 capitalize">{{ $breach->severity }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Status</dt><dd class="mt-1 font-semibold text-gray-900 capitalize">{{ $breach->status }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Discovered</dt><dd class="mt-1 text-gray-700">{{ $breach->discovered_at->format('d M Y, H:i') }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Occurred</dt><dd class="mt-1 text-gray-700">{{ $breach->occurred_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Affected users</dt><dd class="mt-1 text-gray-700 tabular-nums">{{ number_format($breach->affected_users) }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Logged by</dt><dd class="mt-1 text-gray-700">{{ $breach->logger?->name ?? 'System' }}</dd></div>
            </dl>

            <div class="mt-5 pt-5 border-t border-gray-100">
                <dt class="text-xs uppercase tracking-wider font-bold text-gray-400">What happened</dt>
                <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $breach->description }}</dd>
            </div>

            @if ($breach->data_categories)
                <div class="mt-4">
                    <dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Data categories exposed</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ $breach->data_categories }}</dd>
                </div>
            @endif

            @if ($breach->remediation)
                <div class="mt-4">
                    <dt class="text-xs uppercase tracking-wider font-bold text-gray-400">Remediation</dt>
                    <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $breach->remediation }}</dd>
                </div>
            @endif
        </div>

        {{-- Quick status update --}}
        <form method="POST" action="{{ route('admin.breaches.update', $breach) }}" class="bg-white rounded-xl border border-gray-200 p-6">
            @csrf
            @method('PATCH')
            <h2 class="font-semibold text-gray-900 mb-3">Update record</h2>
            {{-- Resend the immutable fields so validation passes; only status/severity/remediation change in practice. --}}
            <input type="hidden" name="title" value="{{ $breach->title }}">
            <input type="hidden" name="description" value="{{ $breach->description }}">
            <input type="hidden" name="discovered_at" value="{{ $breach->discovered_at->format('Y-m-d\TH:i') }}">
            <input type="hidden" name="occurred_at" value="{{ $breach->occurred_at?->format('Y-m-d\TH:i') }}">
            <input type="hidden" name="affected_users" value="{{ $breach->affected_users }}">
            <input type="hidden" name="data_categories" value="{{ $breach->data_categories }}">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">
                        @foreach (\App\Models\DataBreach::STATUSES as $s)
                            <option value="{{ $s }}" @selected($breach->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Severity</label>
                    <select name="severity" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">
                        @foreach (\App\Models\DataBreach::SEVERITIES as $s)
                            <option value="{{ $s }}" @selected($breach->severity === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Remediation</label>
                <textarea name="remediation" rows="3" maxlength="5000" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">{{ $breach->remediation }}</textarea>
            </div>
            <button type="submit" class="mt-4 px-5 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-semibold hover:bg-gray-700 transition">Save</button>
            <a href="{{ route('admin.breaches.index') }}" class="ml-3 text-sm text-gray-500 hover:text-gray-800">Back to list</a>
        </form>
    </div>
</x-layouts.admin>
