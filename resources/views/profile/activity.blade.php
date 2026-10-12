<x-app-layout title="Activity log">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'All settings', 'href' => route('settings.index')],
            ['label' => 'Activity log'],
        ]" />
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Activity log</h1>
            <p class="text-sm text-gray-500 mt-1">Changes recorded for {{ $company->name }}</p>
        </div>
    </x-slot>

    @php
        // Stored action keys ("cash_memo.created") in words people use.
        $named = [
            'invoice.finalized' => 'Invoice issued',
            'invoice.cancelled' => 'Invoice cancelled',
            'invoice.deleted' => 'Invoice deleted',
            'invoice.duplicated' => 'Invoice duplicated',
            'payment.recorded' => 'Payment recorded',
            'payment.reversed' => 'Payment reversed',
            'purchase_bill.created' => 'Purchase invoice added',
            'purchase_bill.deleted' => 'Purchase invoice deleted',
            'report.exported' => 'Report exported',
            'team.invited' => 'Team member invited',
            'team.invite_resent' => 'Invitation sent again',
            'team.removed' => 'Team member removed',
            'team.role_changed' => 'Team role changed',
            'company.updated' => 'Company details changed',
            'company.bank_updated' => 'Bank details changed',
            'company.books_lock_changed' => 'Books lock changed',
            'bank.statement_uploaded' => 'Bank statement uploaded',
            'admin.impersonate.start' => 'Support session started',
            'admin.impersonate.stop' => 'Support session ended',
            'admin.user.password_reset' => 'Password reset by support',
        ];
        $label = function (string $action) use ($named): string {
            if (isset($named[$action])) {
                return $named[$action];
            }
            $nouns = ['purchase_bill' => 'purchase invoice', 'cash_memo' => 'cash memo', 'credit_note' => 'credit note'];
            $parts = explode('.', $action);
            $noun = $nouns[$parts[0]] ?? str_replace('_', ' ', $parts[0]);
            $rest = str_replace('_', ' ', implode(' ', array_slice($parts, 1)));

            return \Illuminate\Support\Str::ucfirst(trim($noun . ' ' . $rest));
        };
        $tone = fn (string $action) => match (true) {
            str_contains($action, 'deleted'), str_contains($action, 'cancelled'), str_contains($action, 'reversed'), str_contains($action, 'removed') => 'bg-danger-50 text-danger-700 ring-danger-200',
            str_contains($action, 'updated'), str_contains($action, 'changed') => 'bg-accent-50 text-accent-800 ring-accent-200',
            default => 'bg-gray-100 text-gray-700 ring-gray-200',
        };
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm text-gray-700">
                <div class="font-semibold text-gray-900 mb-1">Audit trail (Section 128, Companies Act 2013)</div>
                <p>Changes to invoices, payments, credit notes, expenses, cash memos and company settings are recorded with the user, date, time and IP address.</p>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                @if ($logs->isEmpty())
                    <div class="px-4 py-12 text-center text-gray-500 text-sm">No activity recorded yet.</div>
                @else
                    {{-- Phones: one entry per row, no sideways scrolling. --}}
                    <ul class="md:hidden divide-y divide-gray-100">
                        @foreach ($logs as $log)
                            <li class="px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $tone($log->action) }}">{{ $label($log->action) }}</span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                </div>
                                @if ($log->summary)
                                    <div class="mt-1.5 text-sm text-gray-800 break-words">{{ $log->summary }}</div>
                                @endif
                                <div class="mt-0.5 text-xs text-gray-500">{{ $log->user?->name ?? 'System' }}@if ($log->ip_address) · {{ $log->ip_address }}@endif</div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left">When</th>
                                    <th class="px-4 py-3 text-left">User</th>
                                    <th class="px-4 py-3 text-left">Action</th>
                                    <th class="px-4 py-3 text-left">Summary</th>
                                    <th class="px-4 py-3 text-left">IP address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($logs as $log)
                                    <tr class="hover:bg-gray-50 align-top">
                                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-700">
                                            <div>{{ $log->created_at->format('d M Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $log->created_at->format('h:i:s A') }}</div>
                                        </td>
                                        <td class="px-4 py-2.5">{{ $log->user?->name ?? 'System' }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $tone($log->action) }}">{{ $label($log->action) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $log->summary }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $log->ip_address ?: '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div>{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
