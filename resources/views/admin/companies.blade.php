<x-layouts.admin title="Companies" subtitle="{{ $companies->total() }} total · every business entity on the platform">
    <x-slot:action>
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or GSTIN" class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
            @if (request('search'))
                <a href="{{ route('admin.companies') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Company</th>
                    <th class="px-5 py-3 text-left font-semibold">Owner</th>
                    <th class="px-5 py-3 text-left font-semibold">Location</th>
                    <th class="px-5 py-3 text-right font-semibold">Customers</th>
                    <th class="px-5 py-3 text-right font-semibold">Invoices</th>
                    <th class="px-5 py-3 text-left font-semibold">Onboarded</th>
                    <th class="px-5 py-3 text-left font-semibold">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($companies as $co)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-900">{{ $co->name }}</div>
                            @if ($co->gstin)
                                <div class="text-xs text-gray-500 font-mono">{{ $co->gstin }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.users.show', $co->user) }}" class="text-brand-700 hover:underline text-sm">{{ $co->user?->name }}</a>
                            <div class="text-xs text-gray-500 truncate">{{ $co->user?->email }}</div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">
                            {{ $co->city ? $co->city . ', ' : '' }}{{ $co->state?->name ?? $co->country }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $co->customers_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $co->invoices_count }}</td>
                        <td class="px-5 py-3">
                            @if ($co->onboarded_at)
                                <span class="text-xs px-2 py-0.5 rounded bg-money-100 text-money-700 font-semibold">✓ Onboarded</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 font-semibold">Pending</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $co->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No companies match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
</x-layouts.admin>
