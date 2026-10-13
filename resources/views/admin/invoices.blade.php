<x-layouts.admin title="Invoices" subtitle="{{ $invoices->total() }} total · every invoice on the platform">
    <x-slot:action>
        <form method="GET" class="flex flex-wrap w-full md:w-auto items-center gap-2">
            <select name="status" class="min-h-[40px] border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" onchange="this.form.submit()" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (['draft', 'final', 'partially_paid', 'paid', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ (new \App\Models\Invoice(['status' => $s]))->statusLabel() }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search number" class="min-h-[40px] min-w-[8rem] flex-1 md:flex-none md:w-48 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" aria-label="Search number">
            @if (request('search') || request('status'))
                <a href="{{ route('admin.invoices') }}" class="inline-flex items-center min-h-[40px] px-3 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    @php
        $statusColor = [
            'draft' => 'bg-gray-100 text-gray-700',
            'final' => 'bg-brand-100 text-brand-700',
            'partially_paid' => 'bg-accent-100 text-accent-700',
            'paid' => 'bg-money-100 text-money-700',
            'cancelled' => 'bg-danger-100 text-danger-700',
        ];
    @endphp

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      {{-- Phones: one card per invoice --}}
      <ul class="sm:hidden divide-y divide-gray-100">
          @forelse ($invoices as $inv)
              <li class="p-4">
                  <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                          <div class="font-mono text-sm font-semibold text-gray-900 break-all">{{ $inv->displayNumber() }}</div>
                          <div class="text-xs text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</div>
                      </div>
                      <span class="shrink-0 text-xs px-2 py-0.5 rounded font-semibold {{ $statusColor[$inv->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $inv->statusLabel() }}</span>
                  </div>
                  <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Grand total</dt>
                          <dd class="font-mono tabular-nums font-semibold text-gray-900 break-all">₹{{ rupees($inv->grand_total) }}</dd>
                      </div>
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Balance</dt>
                          <dd class="font-mono tabular-nums break-all {{ (float) $inv->balance > 0 ? 'text-accent-700 font-semibold' : 'text-gray-500' }}">₹{{ rupees($inv->balance) }}</dd>
                      </div>
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Customer</dt>
                          <dd class="text-gray-700 break-words">{{ $inv->customer?->name ?? '-' }}</dd>
                      </div>
                      <div class="min-w-0">
                          <dt class="text-xs text-gray-500">Company</dt>
                          <dd class="text-gray-700 break-words">{{ $inv->company?->name ?? '-' }}</dd>
                      </div>
                      <div class="col-span-2 min-w-0">
                          <dt class="text-xs text-gray-500">User</dt>
                          <dd class="break-words"><a href="{{ route('admin.users.show', $inv->user) }}" class="text-brand-700 hover:underline">{{ $inv->user?->name }}</a></dd>
                      </div>
                  </dl>
              </li>
          @empty
              <li class="px-4 py-12 text-center text-gray-500">No invoices match.</li>
          @endforelse
      </ul>

      {{-- Tablets and up: the full table --}}
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm min-w-[880px]">
            <thead class="bg-gray-50 text-xs text-gray-500">
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
            <tbody class="divide-y divide-gray-100">
                @forelse ($invoices as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $inv->displayNumber() }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.users.show', $inv->user) }}" class="text-brand-700 hover:underline text-sm">{{ $inv->user?->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-gray-700">{{ $inv->company?->name ?? '-' }}</td>
                        <td class="px-5 py-3 text-gray-700">{{ $inv->customer?->name ?? '-' }}</td>
                        <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded font-semibold {{ $statusColor[$inv->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $inv->statusLabel() }}</span></td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">₹{{ rupees($inv->grand_total) }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums {{ (float) $inv->balance > 0 ? 'text-accent-700 font-semibold' : 'text-gray-500' }}">₹{{ rupees($inv->balance) }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $inv->invoice_date?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No invoices match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 max-sm:[&_a]:min-h-[40px] max-sm:[&_span]:min-h-[40px]">{{ $invoices->links() }}</div>
</x-layouts.admin>
