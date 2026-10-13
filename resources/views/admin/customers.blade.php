<x-layouts.admin title="Customers" subtitle="{{ $customers->total() }} total · every buyer recorded across all companies">
    <x-slot:action>
        <form method="GET" class="flex w-full md:w-auto gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, GSTIN" class="min-h-[40px] min-w-0 flex-1 md:flex-none md:w-64 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" aria-label="Search name, email, GSTIN">
            @if (request('search'))
                <a href="{{ route('admin.customers') }}" class="inline-flex items-center min-h-[40px] px-3 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      {{-- Phones: one card per customer --}}
      <ul class="sm:hidden divide-y divide-gray-100">
          @forelse ($customers as $cust)
              <li class="p-4">
                  <div class="font-medium text-gray-900 break-words">{{ $cust->name }}</div>
                  @if ($cust->gstin)
                      <div class="text-xs text-gray-500 font-mono">{{ $cust->gstin }}</div>
                  @endif
                  @if ($cust->email)
                      <div class="text-xs text-gray-500 break-all">{{ $cust->email }}</div>
                  @endif
                  <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Of company</dt>
                          <dd class="text-gray-700 break-words">{{ $cust->company?->name ?? '-' }}</dd>
                      </div>
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Owner</dt>
                          <dd class="break-words"><a href="{{ route('admin.users.show', $cust->user) }}" class="text-brand-700 hover:underline">{{ $cust->user?->name }}</a></dd>
                      </div>
                      <div class="col-span-2">
                          <dt class="text-xs text-gray-500">Location</dt>
                          <dd class="text-gray-700">{{ $cust->city ? $cust->city . ', ' : '' }}{{ $cust->state?->name ?? $cust->country }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Invoices</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $cust->invoices_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Added</dt>
                          <dd class="text-gray-700">{{ $cust->created_at->format('d M Y') }}</dd>
                      </div>
                  </dl>
              </li>
          @empty
              <li class="px-4 py-12 text-center text-gray-500">No customers match.</li>
          @endforelse
      </ul>

      {{-- Tablets and up: the full table --}}
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Customer</th>
                    <th class="px-5 py-3 text-left font-semibold">Of company</th>
                    <th class="px-5 py-3 text-left font-semibold">Owner</th>
                    <th class="px-5 py-3 text-left font-semibold">Location</th>
                    <th class="px-5 py-3 text-right font-semibold">Invoices</th>
                    <th class="px-5 py-3 text-left font-semibold">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customers as $cust)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-900">{{ $cust->name }}</div>
                            @if ($cust->gstin)
                                <div class="text-xs text-gray-500 font-mono">{{ $cust->gstin }}</div>
                            @endif
                            @if ($cust->email)
                                <div class="text-xs text-gray-500 truncate">{{ $cust->email }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">{{ $cust->company?->name ?? '-' }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.users.show', $cust->user) }}" class="text-brand-700 hover:underline text-sm">{{ $cust->user?->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">
                            {{ $cust->city ? $cust->city . ', ' : '' }}{{ $cust->state?->name ?? $cust->country }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $cust->invoices_count }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $cust->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No customers match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 max-sm:[&_a]:min-h-[40px] max-sm:[&_span]:min-h-[40px]">{{ $customers->links() }}</div>
</x-layouts.admin>
