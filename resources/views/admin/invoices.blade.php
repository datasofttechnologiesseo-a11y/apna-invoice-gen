<x-layouts.admin title="Invoices" subtitle="{{ $invoices->total() }} total · every invoice on the platform">
    <x-slot:action>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="border-slate-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['draft', 'final', 'partially_paid', 'paid', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search number" class="w-48 border-slate-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @if (request('search') || request('status'))
                <a href="{{ route('admin.invoices') }}" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[880px]">
            <thead class="bg-slate-50 text-[10px] text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Number</th>
                    <th class="px-5 py-3 text-left font-semibold">User</th>
                    <th class="px-5 py-3 text-left font-semibold">Company</th>
                    <th class="px-5 py-3 text-left font-semibold">Customer</th>
                    <th class="px-5 py-3 text-left font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Grand total</th>
                    <th class="px-5 py-3 text-right font-semibold">Balance</th>
                    <th class="px-5 py-3 text-left font-semibold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @php
                    $statusColor = [
                        'draft' => 'bg-slate-100 text-slate-700',
                        'final' => 'bg-indigo-100 text-indigo-700',
                        'partially_paid' => 'bg-amber-100 text-amber-700',
                        'paid' => 'bg-emerald-100 text-emerald-700',
                        'cancelled' => 'bg-rose-100 text-rose-700',
                    ];
                @endphp
                @forelse ($invoices as $inv)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $inv->invoice_number ?? 'Draft #' . $inv->id }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.users.show', $inv->user) }}" class="text-indigo-700 hover:underline text-sm">{{ $inv->user?->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-slate-700">{{ $inv->company?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-700">{{ $inv->customer?->name ?? '—' }}</td>
                        <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded font-semibold {{ $statusColor[$inv->status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst(str_replace('_', ' ', $inv->status)) }}</span></td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">₹{{ number_format((float) $inv->grand_total, 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums {{ (float) $inv->balance > 0 ? 'text-amber-700 font-semibold' : 'text-slate-400' }}">₹{{ number_format((float) $inv->balance, 2) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $inv->invoice_date?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">No invoices match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.admin>
