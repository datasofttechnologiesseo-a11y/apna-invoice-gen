<x-layouts.admin title="Companies" subtitle="{{ $companies->total() }} total · every business on the platform">
    <x-slot:action>
        <form method="GET" class="flex w-full md:w-auto gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or GSTIN" class="min-h-[40px] min-w-0 flex-1 md:flex-none md:w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" aria-label="Search name or GSTIN">
            @if (request('search'))
                <a href="{{ route('admin.companies') }}" class="inline-flex items-center min-h-[40px] px-3 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      {{-- Phones: one card per company --}}
      <ul class="sm:hidden divide-y divide-gray-100">
          @forelse ($companies as $co)
              <li class="p-4">
                  <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                          <div class="font-medium text-gray-900 break-words">{{ $co->name }}</div>
                          @if ($co->gstin)
                              <div class="text-xs text-gray-500 font-mono">{{ $co->gstin }}</div>
                          @endif
                      </div>
                      @if ($co->onboarded_at)
                          <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-money-100 text-money-700 font-semibold">✓ Onboarded</span>
                      @else
                          <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 font-semibold">Pending</span>
                      @endif
                  </div>
                  <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div class="col-span-2 min-w-0">
                          <dt class="text-xs text-gray-500">Owner</dt>
                          <dd>
                              <a href="{{ route('admin.users.show', $co->user) }}" class="text-brand-700 hover:underline">{{ $co->user?->name }}</a>
                              <div class="text-xs text-gray-500 break-all">{{ $co->user?->email }}</div>
                          </dd>
                      </div>
                      <div class="col-span-2">
                          <dt class="text-xs text-gray-500">Location</dt>
                          <dd class="text-gray-700">{{ $co->city ? $co->city . ', ' : '' }}{{ $co->state?->name ?? $co->country }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Customers</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $co->customers_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Invoices</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $co->invoices_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Created</dt>
                          <dd class="text-gray-700">{{ $co->created_at->format('d M Y') }}</dd>
                      </div>
                  </dl>
              </li>
          @empty
              <li class="px-4 py-12 text-center text-gray-500">No companies match.</li>
          @endforelse
      </ul>

      {{-- Tablets and up: the full table --}}
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-gray-50 text-xs text-gray-500">
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
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No companies match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 max-sm:[&_a]:min-h-[40px] max-sm:[&_span]:min-h-[40px]">{{ $companies->links() }}</div>
</x-layouts.admin>
