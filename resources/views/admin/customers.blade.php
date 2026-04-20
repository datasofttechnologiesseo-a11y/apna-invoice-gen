<x-layouts.admin title="Customers" subtitle="{{ $customers->total() }} total · every buyer recorded across all companies">
    <x-slot:action>
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, GSTIN" class="w-64 border-slate-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @if (request('search'))
                <a href="{{ route('admin.customers') }}" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-slate-50 text-[10px] text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Customer</th>
                    <th class="px-5 py-3 text-left font-semibold">Of company</th>
                    <th class="px-5 py-3 text-left font-semibold">Owner</th>
                    <th class="px-5 py-3 text-left font-semibold">Location</th>
                    <th class="px-5 py-3 text-right font-semibold">Invoices</th>
                    <th class="px-5 py-3 text-left font-semibold">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $cust)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-slate-900">{{ $cust->name }}</div>
                            @if ($cust->gstin)
                                <div class="text-xs text-slate-500 font-mono">{{ $cust->gstin }}</div>
                            @endif
                            @if ($cust->email)
                                <div class="text-xs text-slate-500 truncate">{{ $cust->email }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-700">{{ $cust->company?->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.users.show', $cust->user) }}" class="text-indigo-700 hover:underline text-sm">{{ $cust->user?->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-700">
                            {{ $cust->city ? $cust->city . ', ' : '' }}{{ $cust->state?->name ?? $cust->country }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $cust->invoices_count }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $cust->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">No customers match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.admin>
