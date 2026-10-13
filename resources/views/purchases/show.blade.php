<x-app-layout :title="'Purchase invoice ' . $bill->bill_number">
    @php
        $vendor = $bill->vendor;
        $meta = 'text-xs font-semibold text-gray-500';
    @endphp
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Purchase invoices', 'href' => route('purchases.index')],
            ['label' => $bill->bill_number],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Purchase invoice <span class="font-mono">{{ $bill->bill_number }}</span></h1>
                <x-status-badge :status="$bill->status === 'unpaid' && $bill->isOverdue() ? 'overdue' : $bill->status" :label="$bill->statusLabel()" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('purchases.edit', $bill) }}" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Edit</a>
                <x-confirm-form :action="route('purchases.destroy', $bill)" method="DELETE"
                    title="Delete this purchase invoice?"
                    message="The purchase invoice, its lines, payments and linked expense entry will be deleted from your books."
                    confirm-label="Delete purchase invoice" confirm-class="bg-danger-600 hover:bg-danger-700" tone="danger">
                    <button type="button" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-danger-300 text-danger-700 hover:bg-danger-50 text-sm font-semibold rounded-md">Delete</button>
                </x-confirm-form>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Input credit warnings (s.16(4) time limit, Rule 37). Advice only:
                 the bill stays as it is. --}}
            @foreach ($creditNotes ?? [] as $note)
                <div class="p-3 rounded-lg bg-accent-50 border border-accent-200 text-sm text-accent-900" role="status">{{ $note }}</div>
            @endforeach

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                {{-- Bill --}}
                <div class="xl:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm p-4 sm:p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div class="col-span-2">
                                <div class="{{ $meta }}">Vendor</div>
                                <div class="font-semibold text-gray-900">{{ $vendor->name }}</div>
                                @if ($vendor->gstin)<div class="font-mono text-xs text-gray-600">GSTIN {{ $vendor->gstin }}</div>@endif
                                <div class="text-xs text-gray-500">
                                    {{ collect([$vendor->address_line1, $vendor->address_line2, $vendor->city])->filter()->implode(', ') }}
                                    @if ($vendor->state) · {{ $vendor->state->name }} ({{ $vendor->state->gst_code }})@endif
                                </div>
                            </div>
                            <div>
                                <div class="{{ $meta }}">Invoice date</div>
                                <div class="text-gray-900">{{ $bill->bill_date->format('d M Y') }}</div>
                                @if ($bill->booked_on)
                                    {{-- Arrived after its month was locked: booked (and ITC claimed) later. --}}
                                    <div class="text-xs text-gray-600 mt-0.5">Booked on {{ $bill->booked_on->format('d M Y') }} (its month was locked)</div>
                                @endif
                            </div>
                            <div>
                                <div class="{{ $meta }}">Due date</div>
                                <div class="{{ $bill->isOverdue() ? 'text-danger-700 font-semibold' : 'text-gray-900' }}">{{ $bill->due_date?->format('d M Y') ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="{{ $meta }}">Tax type</div>
                                <div class="text-gray-900">{{ $bill->is_interstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + ' . $bill->stateTaxLabel() . ')' }}</div>
                            </div>
                            <div>
                                <div class="{{ $meta }}">Reverse charge</div>
                                <div class="text-gray-900">{{ $bill->reverse_charge ? 'Yes, payable by you' : 'No' }}</div>
                            </div>
                            <div>
                                <div class="{{ $meta }}">Input credit</div>
                                <div class="text-gray-900">{{ ! $bill->itc_eligible ? 'Blocked (Section 17(5))' : ($bill->isItcTimeBarred() ? 'Time-barred (Section 16(4))' : 'Claimable') }}</div>
                            </div>
                            <div>
                                <div class="{{ $meta }}">Category</div>
                                <div class="text-gray-900">{{ config('expense_categories.' . $bill->expense_category . '.label', ucfirst((string) $bill->expense_category)) }}</div>
                            </div>
                        </div>

                        <div class="mt-6 overflow-x-auto print:overflow-visible">
                        <table class="w-full text-sm min-w-[520px] print:min-w-0">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600 text-left text-xs font-semibold">
                                    <th class="px-2 py-2">#</th>
                                    <th class="px-2 py-2">Description</th>
                                    <th class="px-2 py-2">HSN/SAC</th>
                                    <th class="px-2 py-2 text-right">Qty</th>
                                    <th class="px-2 py-2 text-right">Rate</th>
                                    <th class="px-2 py-2 text-right">GST%</th>
                                    <th class="px-2 py-2 text-right">Taxable</th>
                                    <th class="px-2 py-2 text-right">{{ $bill->is_interstate ? 'IGST' : 'CGST + ' . $bill->stateTaxLabel() }}</th>
                                    <th class="px-2 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bill->items as $idx => $item)
                                    <tr class="border-b">
                                        <td class="px-2 py-2">{{ $idx + 1 }}</td>
                                        <td class="px-2 py-2">{{ $item->description }}
                                            @if ($item->product)
                                                <a href="{{ route($item->product->track_stock ? 'products.stock' : 'products.edit', $item->product) }}" class="block text-xs text-brand-700 hover:underline">Item: {{ $item->product->name }}{{ $item->product->track_stock ? ' · stock updated' : '' }}</a>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 font-mono">{{ $item->hsn_sac }}</td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums">{{ inr($item->rate) }}</td>
                                        <td class="px-2 py-2 text-right">{{ rtrim(rtrim(inr($item->gst_rate), '0'), '.') }}%</td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums">{{ inr($item->amount) }}</td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums text-gray-700">{{ inr((float) $item->cgst_amount + (float) $item->sgst_amount + (float) $item->igst_amount) }}</td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums font-medium">{{ inr($bill->reverse_charge ? $item->amount : $item->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <table class="text-sm w-full sm:w-72 tabular-nums">
                                <tr><td class="py-1 text-gray-600">Taxable value</td><td class="py-1 text-right font-mono">{{ inr($bill->subtotal) }}</td></tr>
                                @if ($bill->is_interstate)
                                    <tr><td class="py-1 text-gray-600">IGST{{ $bill->reverse_charge ? ' (payable by you)' : '' }}</td><td class="py-1 text-right font-mono">{{ inr($bill->total_igst) }}</td></tr>
                                @else
                                    <tr><td class="py-1 text-gray-600">CGST{{ $bill->reverse_charge ? ' (payable by you)' : '' }}</td><td class="py-1 text-right font-mono">{{ inr($bill->total_cgst) }}</td></tr>
                                    <tr><td class="py-1 text-gray-600">{{ $bill->stateTaxLabel() }}{{ $bill->reverse_charge ? ' (payable by you)' : '' }}</td><td class="py-1 text-right font-mono">{{ inr($bill->total_sgst) }}</td></tr>
                                @endif
                                <tr><td class="py-1 text-gray-600">Round off</td><td class="py-1 text-right font-mono">{{ inr($bill->round_off) }}</td></tr>
                                <tr class="border-t"><td class="py-1 font-bold">Payable to vendor</td><td class="py-1 text-right font-mono font-bold">₹{{ inr($bill->grand_total) }}</td></tr>
                                <tr><td class="py-1 text-gray-600">Paid</td><td class="py-1 text-right font-mono">{{ inr($bill->paid_amount) }}</td></tr>
                                <tr class="border-t"><td class="py-1 font-semibold">Balance</td><td class="py-1 text-right font-mono font-semibold">₹{{ inr($bill->balance) }}</td></tr>
                            </table>
                        </div>
                        @if ($bill->reverse_charge && (float) $bill->total_tax > 0)
                            <p class="mt-3 text-xs text-accent-800 bg-accent-50 border border-accent-200 rounded-md px-2 py-1.5">
                                Reverse charge: ₹{{ inr($bill->total_tax) }} GST is payable by you to the government (GSTR-3B 3.1(d)){{ $bill->itc_eligible ? ' and claimable as input credit' : '' }}; it is not owed to the vendor.
                            </p>
                        @endif
                        @if ($bill->notes)
                            <div class="mt-4 text-sm text-gray-700"><span class="font-semibold">Notes:</span> {{ $bill->notes }}</div>
                        @endif
                    </div>
                </div>

                {{-- Payments --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm p-5">
                        <h2 class="font-semibold text-gray-900">Payments made</h2>
                        @if ($bill->payments->isEmpty())
                            <p class="mt-2 text-sm text-gray-500">No payments yet.</p>
                        @else
                            <table class="w-full mt-3 text-sm">
                                @foreach ($bill->payments as $p)
                                    <tr class="border-b">
                                        <td class="py-1.5">
                                            <div class="font-mono">₹{{ inr($p->amount) }}</div>
                                            <div class="text-xs text-gray-500">{{ $p->paid_on->format('d M Y') }} · {{ config('payment_methods.methods.' . $p->method . '.label', ucfirst($p->method)) }}@if ($p->reference_number) · {{ $p->reference_number }}@endif</div>
                                        </td>
                                        <td class="py-1.5 text-right">
                                            <x-confirm-form :action="route('purchases.payments.destroy', [$bill, $p])" method="DELETE"
                                                title="Remove this payment?" message="This amount is added back to the invoice balance."
                                                confirm-label="Remove" confirm-class="bg-danger-600 hover:bg-danger-700" tone="danger">
                                                <button type="button" class="inline-flex items-center min-h-[36px] text-sm text-danger-600 hover:underline">Remove</button>
                                            </x-confirm-form>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if ((float) $bill->balance > 0)
                            <form method="POST" action="{{ route('purchases.payments.store', $bill) }}" class="mt-4 space-y-3">
                                @csrf
                                <h3 class="text-sm font-semibold text-gray-900">Record a payment <span class="font-normal text-gray-500">· balance ₹{{ inr($bill->balance) }}</span></h3>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2">
                                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount (₹) *</label>
                                        <input inputmode="decimal" id="amount" name="amount" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror type="number" step="any" min="0.01" max="{{ $bill->balance }}" value="{{ plain_amount(old('amount', $bill->balance)) }}" required class="mt-1 block w-full border-gray-300 rounded text-sm text-right">
                                        <x-input-error :messages="$errors->get('amount')" for="amount" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="method" class="block text-sm font-medium text-gray-700">Method *</label>
                                        <select id="method" name="method" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                            @foreach ($methods as $key => $m)
                                                <option value="{{ $key }}" @selected(old('method', 'neft') === $key)>{{ $m['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="paid_on" class="block text-sm font-medium text-gray-700">Paid on *</label>
                                        <input id="paid_on" name="paid_on" type="date" @error('paid_on') aria-invalid="true" aria-describedby="paid_on-error" @enderror value="{{ old('paid_on', now()->toDateString()) }}" required class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        <x-input-error :messages="$errors->get('paid_on')" for="paid_on" class="mt-1" />
                                    </div>
                                    <div class="col-span-2">
                                        <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference / UTR</label>
                                        <input id="reference_number" name="reference_number" type="text" maxlength="80" value="{{ old('reference_number') }}" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        <x-input-error :messages="$errors->get('reference_number')" for="reference_number" class="mt-1" />
                                    </div>
                                </div>
                                <button class="w-full h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md">Record payment</button>
                            </form>
                        @endif
                    </div>

                    @if ($bill->expense_id)
                        <div class="bg-gray-50 rounded-xl ring-1 ring-gray-200 p-4 text-sm text-gray-600">
                            Posted as an expense entry, so it is included in your <a href="{{ route('finance.index') }}" class="text-brand-700 hover:underline">P&amp;L</a> and <a href="{{ route('finance.gstr3b') }}" class="text-brand-700 hover:underline">GSTR-3B</a>.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
