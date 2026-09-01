<x-layouts.admin title="Data breaches" subtitle="Personal-data breach register - DPDP Act §8(6)">
    <x-slot name="action">
        <a href="{{ route('admin.breaches.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-danger-600 text-white rounded-lg text-sm font-semibold hover:bg-danger-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Log breach
        </a>
    </x-slot>

    @php
        $statusTone = [
            'open' => 'bg-danger-100 text-danger-700',
            'contained' => 'bg-accent-100 text-accent-800',
            'reported' => 'bg-brand-100 text-brand-700',
            'closed' => 'bg-gray-100 text-gray-600',
        ];
        $sevTone = [
            'low' => 'bg-gray-100 text-gray-600',
            'medium' => 'bg-accent-100 text-accent-800',
            'high' => 'bg-accent-100 text-accent-800',
            'critical' => 'bg-danger-100 text-danger-700',
        ];
    @endphp

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">Incident</th>
                    <th class="px-4 py-3 font-semibold">Severity</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Affected</th>
                    <th class="px-4 py-3 font-semibold">Discovered</th>
                    <th class="px-4 py-3 font-semibold">Notifications</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($breaches as $b)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.breaches.show', $b) }}" class="font-semibold text-gray-900 hover:text-danger-600">{{ $b->title }}</a>
                        </td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $sevTone[$b->severity] ?? '' }}">{{ ucfirst($b->severity) }}</span></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusTone[$b->status] ?? '' }}">{{ ucfirst($b->status) }}</span></td>
                        <td class="px-4 py-3 tabular-nums text-gray-700">{{ number_format($b->affected_users) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $b->discovered_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @if ($b->board_notified_at && $b->users_notified_at)
                                <span class="text-money-600 font-medium">Complete</span>
                            @elseif ($b->notificationOverdue())
                                <span class="text-danger-600 font-bold">Overdue (72h)</span>
                            @else
                                <span class="text-accent-600 font-medium">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No breaches recorded. Good.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $breaches->links() }}</div>
</x-layouts.admin>
