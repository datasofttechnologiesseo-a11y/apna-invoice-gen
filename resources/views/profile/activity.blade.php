<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Activity Log</h2>
                <p class="text-sm text-gray-500 mt-1">Who did what, when · {{ $company->name }}</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to profile</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                <div class="font-semibold mb-1">Audit trail</div>
                <p>Every meaningful change to your books — expenses, cash memos, invoice payments, etc. — is recorded here with the user, timestamp, and IP address. This is your defensibility record for any tax assessment or audit.</p>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">When</th>
                                <th class="px-4 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Action</th>
                                <th class="px-4 py-3 text-left font-semibold">Summary</th>
                                <th class="px-4 py-3 text-left font-semibold">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-700">
                                        <div>{{ $log->created_at->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ $log->created_at->format('H:i:s') }}</div>
                                    </td>
                                    <td class="px-4 py-2">{{ $log->user?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $tone = match (true) {
                                                str_contains($log->action, 'deleted') => 'bg-red-100 text-red-800',
                                                str_contains($log->action, 'updated') => 'bg-amber-100 text-amber-800',
                                                str_contains($log->action, 'created') => 'bg-emerald-100 text-emerald-800',
                                                default => 'bg-gray-100 text-gray-700',
                                            };
                                        @endphp
                                        <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider {{ $tone }}">{{ str_replace('.', ' · ', $log->action) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $log->summary }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500 font-mono">{{ $log->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">No activity recorded yet. Once you create or modify expenses, cash memos, etc., entries will appear here.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
