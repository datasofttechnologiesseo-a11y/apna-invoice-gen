<x-layouts.admin :title="$breach->title" subtitle="Breach record">

    @php
        $deadline = $breach->discovered_at->copy()->addHours(config('legal.breach_report_hours', 72));
    @endphp

    <div class="max-w-3xl space-y-6">

        {{-- 72-hour notification tracker --}}
        <div @class([
            'rounded-xl border p-4 sm:p-5',
            'bg-danger-50 border-danger-200' => $breach->notificationOverdue(),
            'bg-money-50 border-money-200' => ! $breach->notificationOverdue() && $breach->board_notified_at && $breach->users_notified_at,
            'bg-accent-50 border-accent-200' => ! $breach->notificationOverdue() && ! ($breach->board_notified_at && $breach->users_notified_at),
        ])>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-bold text-gray-900">Statutory notifications (DPDP Act Section 8(6))</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Notify the Data Protection Board and the affected users by
                        <strong>{{ $deadline->format('d M Y, H:i') }}</strong>,
                        {{ config('legal.breach_report_hours', 72) }} hours after discovery.
                    </p>
                </div>
                @if ($breach->notificationOverdue())
                    <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold bg-danger-600 text-white">Overdue</span>
                @endif
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-3">
                {{-- Board --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-xs font-semibold text-gray-600">Data Protection Board</div>
                    @if ($breach->board_notified_at)
                        <div class="mt-1 text-sm font-semibold text-money-700">Notified {{ $breach->board_notified_at->format('d M Y, H:i') }}</div>
                    @else
                        <form method="POST" action="{{ route('admin.breaches.notify', $breach) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="target" value="board">
                            <button class="inline-flex items-center min-h-[40px] px-3 py-1.5 bg-gray-900 text-white rounded-md text-xs font-semibold hover:bg-gray-700">Mark Board notified</button>
                        </form>
                    @endif
                </div>
                {{-- Users --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-xs font-semibold text-gray-600">Affected users</div>
                    @if ($breach->users_notified_at)
                        <div class="mt-1 text-sm font-semibold text-money-700">Notified {{ $breach->users_notified_at->format('d M Y, H:i') }}</div>
                    @else
                        <form method="POST" action="{{ route('admin.breaches.notify', $breach) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="target" value="users">
                            <button class="inline-flex items-center min-h-[40px] px-3 py-1.5 bg-gray-900 text-white rounded-md text-xs font-semibold hover:bg-gray-700">Mark users notified</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs font-semibold text-gray-600">Severity</dt><dd class="mt-1 font-semibold text-gray-900 capitalize">{{ $breach->severity }}</dd></div>
                <div><dt class="text-xs font-semibold text-gray-600">Status</dt><dd class="mt-1 font-semibold text-gray-900 capitalize">{{ $breach->status }}</dd></div>
                <div><dt class="text-xs font-semibold text-gray-600">Discovered</dt><dd class="mt-1 text-gray-700">{{ $breach->discovered_at->format('d M Y, H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold text-gray-600">Occurred</dt><dd class="mt-1 text-gray-700">{{ $breach->occurred_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
                <div><dt class="text-xs font-semibold text-gray-600">Affected users</dt><dd class="mt-1 text-gray-700 tabular-nums">{{ number_format($breach->affected_users) }}</dd></div>
                <div><dt class="text-xs font-semibold text-gray-600">Logged by</dt><dd class="mt-1 text-gray-700">{{ $breach->logger?->name ?? 'System' }}</dd></div>
            </dl>

            <dl class="mt-5 pt-5 border-t border-gray-100">
                <div>
                    <dt class="text-xs font-semibold text-gray-600">What happened</dt>
                    <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line break-words">{{ $breach->description }}</dd>
                </div>

                @if ($breach->data_categories)
                    <div class="mt-4">
                        <dt class="text-xs font-semibold text-gray-600">Data categories exposed</dt>
                        <dd class="mt-1 text-sm text-gray-700 break-words">{{ $breach->data_categories }}</dd>
                    </div>
                @endif

                @if ($breach->remediation)
                    <div class="mt-4">
                        <dt class="text-xs font-semibold text-gray-600">Remediation</dt>
                        <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line break-words">{{ $breach->remediation }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Quick status update --}}
        <form method="POST" action="{{ route('admin.breaches.update', $breach) }}" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
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
                    <label for="breach-status" class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="breach-status" name="status" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">
                        @foreach (\App\Models\DataBreach::STATUSES as $s)
                            <option value="{{ $s }}" @selected($breach->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="breach-severity" class="block text-sm font-semibold text-gray-700 mb-1">Severity</label>
                    <select id="breach-severity" name="severity" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">
                        @foreach (\App\Models\DataBreach::SEVERITIES as $s)
                            <option value="{{ $s }}" @selected($breach->severity === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label for="breach-remediation" class="block text-sm font-semibold text-gray-700 mb-1">Remediation</label>
                <textarea id="breach-remediation" name="remediation" rows="3" maxlength="5000" class="w-full rounded-lg border-gray-300 focus:border-danger-500 focus:ring-danger-500 text-sm">{{ $breach->remediation }}</textarea>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="px-5 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-semibold hover:bg-gray-700 transition">Save</button>
                <a href="{{ route('admin.breaches.index') }}" class="inline-flex items-center min-h-[40px] text-sm text-gray-500 hover:text-gray-800">Back to list</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
