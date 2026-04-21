<x-app-layout>
    @php $restricted = $restricted ?? false; @endphp
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $invoice->exists ? 'Edit ' . $invoice->displayNumber() : 'New invoice' }}
            @if ($restricted)
                <span class="ml-2 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 uppercase font-bold tracking-wider">Limited edit</span>
            @endif
        </h2>
    </x-slot>

    @php
        $existingItems = $invoice->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'description' => $i->description,
            'hsn_sac' => $i->hsn_sac,
            'quantity' => (float) $i->quantity,
            'unit' => $i->unit,
            'rate' => (float) $i->rate,
            'gst_rate' => (float) $i->gst_rate,
        ])->toArray();
        $oldItems = old('items', $existingItems);
        if (empty($oldItems)) {
            $oldItems = [['product_id' => null, 'description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'gst_rate' => 18]];
        }
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        $companyStateId = $company->state_id;
        $productIndex = $company->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate']);
    @endphp

    <div class="py-10" x-data='invoiceForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }}, @json($productIndex))'>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-breadcrumbs :items="[
                ['label' => 'Invoices', 'href' => route('invoices.index')],
                ['label' => $invoice->exists ? $invoice->displayNumber() : 'New invoice'],
            ]" />
            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($restricted)
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                    <div class="text-sm">
                        <div class="font-semibold">This invoice is finalised — limited editing only.</div>
                        <div class="mt-0.5">You can update <strong>notes, terms, due date, and transporter details</strong>. Amounts, line items, customer, and invoice number are legally locked per GST rules. To change those, cancel this invoice and issue a credit note / revised invoice.</div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6">
                @csrf
                @if ($invoice->exists) @method('PATCH') @endif

                <div class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="customer_id" value="Customer *" />
                            <div class="flex items-center gap-2 mt-1">
                                <select id="customer_id" name="customer_id" x-model="customerId" @change="recompute()" class="block w-full border-gray-300 rounded-md shadow-sm {{ $restricted ? 'bg-gray-100 cursor-not-allowed' : '' }}" required @disabled($restricted)>
                                    <option value="">— Select customer —</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" @selected(old('customer_id', $invoice->customer_id) == $c->id)>
                                            {{ $c->name }}{{ $c->state?->name ? ' — ' . $c->state->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('customers.create') }}" target="_blank" class="text-brand-600 text-sm whitespace-nowrap">+ New</a>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Invoice no." />
                            <div class="mt-1 py-2 font-mono text-sm">
                                @if ($invoice->exists && ! $invoice->isDraft())
                                    <span class="text-gray-900 font-semibold">{{ $invoice->invoice_number }}</span>
                                @else
                                    <span class="text-brand-700 font-semibold">{{ $previewNumber ?? $invoice->company?->nextInvoiceNumber() }}</span>
                                    <span class="block text-[10px] text-gray-500 uppercase tracking-wider font-sans">Auto-assigned on finalize</span>
                                @endif
                            </div>
                        </div>

                        <input type="hidden" name="currency" value="INR">

                        <div>
                            <x-input-label for="invoice_date" value="Invoice date *" />
                            <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full {{ $restricted ? 'bg-gray-100 cursor-not-allowed' : '' }}" :value="old('invoice_date', $invoice->invoice_date?->toDateString() ?? now()->toDateString())" required :disabled="$restricted" />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $invoice->due_date?->toDateString())" />
                        </div>

                        <div>
                            <x-input-label value="Tax mode (auto)" />
                            <div class="mt-2 text-sm">
                                <span x-show="!customerId" class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded">Select a customer…</span>
                                <span x-show="customerId && isInterstate" class="inline-block px-2 py-0.5 bg-amber-100 text-amber-800 rounded">Inter-state (IGST)</span>
                                <span x-show="customerId && !isInterstate" class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded">Intra-state (CGST + SGST)</span>
                            </div>
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="reverse_charge" value="1" @checked(old('reverse_charge', $invoice->reverse_charge ?? false)) class="rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                                <span>Reverse charge applicable</span>
                                <span class="text-xs text-gray-400" title="Tax paid by recipient under Section 9(3)/(4) of CGST Act">(?)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden {{ $restricted ? 'opacity-70' : '' }}">
                    <div class="px-6 py-4 border-b flex items-center justify-between gap-3 flex-wrap">
                        <h3 class="font-medium text-gray-900">Line items @if ($restricted)<span class="ml-2 text-xs text-amber-700 font-normal">🔒 Locked — amounts are immutable</span>@endif</h3>
                        <div class="flex items-center gap-3">
                            @if ($productIndex->isEmpty() && ! $restricted)
                                <a href="{{ route('products.create') }}" target="_blank" class="text-xs text-brand-700 hover:underline">💡 Save products for faster billing →</a>
                            @endif
                            @if (! $restricted)
                                <button type="button" @click="addRow" class="text-brand-600 text-sm hover:underline">+ Add row</button>
                            @endif
                        </div>
                    </div>
                    <fieldset @disabled($restricted) class="{{ $restricted ? 'pointer-events-none' : '' }}">

                    {{-- Mobile: stacked cards (one per row). Table view from md up. --}}
                    <div class="md:hidden divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="idx">
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase font-bold tracking-wider text-gray-500">Item <span x-text="idx + 1"></span></span>
                                    @if (! $restricted)
                                        <button type="button" @click="removeRow(idx)" class="text-red-600 text-sm" x-show="items.length > 1" aria-label="Remove row">Remove</button>
                                    @endif
                                </div>
                                @if ($productIndex->isNotEmpty())
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Product</label>
                                        <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                        <select @change="pickProduct(idx, $event.target.value)" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                            <option value="">— Custom —</option>
                                            @foreach ($productIndex as $p)
                                                <option value="{{ $p->id }}" :selected="item.product_id == {{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div>
                                    <label class="text-xs text-gray-500 font-semibold">Description</label>
                                    <input :name="`items[${idx}][description]`" x-model="item.description" class="mt-1 block w-full border-gray-300 rounded text-sm" required>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">HSN/SAC</label>
                                        <input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" inputmode="numeric" class="mt-1 block w-full border-gray-300 rounded text-sm font-mono" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Unit</label>
                                        <input :name="`items[${idx}][unit]`" x-model="item.unit" class="mt-1 block w-full border-gray-300 rounded text-sm" placeholder="NOS, KGS…">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Quantity</label>
                                        <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="0.001" min="0.001" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Rate (₹)</label>
                                        <input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="0.01" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-semibold">GST rate</label>
                                    <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        @foreach (config('gst.rates') as $r)
                                            <option value="{{ $r['value'] }}" title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex justify-between pt-2 border-t text-sm">
                                    <span class="text-gray-500">Line amount</span>
                                    <span class="font-mono font-semibold text-gray-900" x-text="'₹ ' + fmt(item.amount)"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- md+: full table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    @if ($productIndex->isNotEmpty())
                                        <th class="px-3 py-2 text-left">Product</th>
                                    @endif
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">HSN/SAC</th>
                                    <th class="px-3 py-2 text-left">Quantity</th>
                                    <th class="px-3 py-2 text-right">Rate (₹)</th>
                                    <th class="px-3 py-2 text-right">GST%</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="`d-${idx}`">
                                    <tr class="border-t">
                                        @if ($productIndex->isNotEmpty())
                                            <td class="px-2 py-2">
                                                <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                                <select @change="pickProduct(idx, $event.target.value)" class="w-40 border-gray-300 rounded text-sm">
                                                    <option value="">— Custom —</option>
                                                    @foreach ($productIndex as $p)
                                                        <option value="{{ $p->id }}" :selected="item.product_id == {{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td class="px-2 py-2"><input :name="`items[${idx}][description]`" x-model="item.description" class="w-full border-gray-300 rounded text-sm" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" inputmode="numeric" class="w-28 border-gray-300 rounded text-sm font-mono" required></td>
                                        <td class="px-2 py-2">
                                            <div class="flex items-center gap-1">
                                                <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="0.001" min="0.001" inputmode="decimal" class="w-20 border-gray-300 rounded text-sm text-right" required>
                                                <input :name="`items[${idx}][unit]`" x-model="item.unit" class="w-20 border-gray-300 rounded text-sm" placeholder="unit">
                                            </div>
                                        </td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="0.01" min="0" inputmode="decimal" class="w-28 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="w-36 border-gray-300 rounded text-sm">
                                                @foreach (config('gst.rates') as $r)
                                                    <option value="{{ $r['value'] }}" title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-sm font-medium" x-text="fmt(item.amount)"></td>
                                        <td class="px-2 py-2 text-right">@if (! $restricted)<button type="button" @click="removeRow(idx)" class="text-red-500 hover:text-red-700 text-lg leading-none" x-show="items.length > 1" aria-label="Remove row">×</button>@endif</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    </fieldset>

                    @php
                        $transporterPrefilled = old('transporter_name', $invoice->transporter_name)
                            || old('transporter_id', $invoice->transporter_id)
                            || old('vehicle_number', $invoice->vehicle_number)
                            || old('transport_mode', $invoice->transport_mode)
                            || old('eway_bill_number', $invoice->eway_bill_number);
                    @endphp

                    <div class="border-t bg-gray-50" x-data="{ open: {{ $transporterPrefilled ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-100 transition"
                                :aria-expanded="open">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6 0a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                </svg>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Shipping goods? Add transporter details</div>
                                    <div class="text-xs text-gray-500">Skip this if you're billing for services. Used for e-way bill and goods delivery.</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak class="px-6 pb-6 pt-2 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="transporter_name" value="Transporter name" />
                                    <x-text-input id="transporter_name" name="transporter_name" type="text" class="mt-1 block w-full" :value="old('transporter_name', $invoice->transporter_name)" />
                                </div>
                                <div>
                                    <x-input-label for="transporter_id" value="Transporter ID / GSTIN" />
                                    <x-text-input id="transporter_id" name="transporter_id" type="text" class="mt-1 block w-full" :value="old('transporter_id', $invoice->transporter_id)" />
                                </div>
                                <div>
                                    <x-input-label for="vehicle_number" value="Vehicle number" />
                                    <x-text-input id="vehicle_number" name="vehicle_number" type="text" class="mt-1 block w-full uppercase" :value="old('vehicle_number', $invoice->vehicle_number)" placeholder="e.g. MH12AB1234" />
                                </div>
                                <div>
                                    <x-input-label for="transport_mode" value="Mode" />
                                    <select id="transport_mode" name="transport_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">— Select —</option>
                                        @foreach (['Road', 'Rail', 'Air', 'Ship'] as $m)
                                            <option value="{{ $m }}" @selected(old('transport_mode', $invoice->transport_mode) === $m)>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="eway_bill_number" value="E-way bill number" />
                                    <x-text-input id="eway_bill_number" name="eway_bill_number" type="text" class="mt-1 block w-full font-mono" :value="old('eway_bill_number', $invoice->eway_bill_number)" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-t bg-gray-50">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="terms" value="Terms & conditions" />
                                <textarea id="terms" name="terms" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('terms', $invoice->terms ?? $company->default_terms) }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes (shown below Terms)" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $invoice->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="md:pl-6 space-y-2 text-sm">
                            <div class="flex justify-between"><span>Subtotal</span><span class="font-mono" x-text="fmt(totals.subtotal)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>SGST</span><span class="font-mono" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono" x-text="fmt(totals.grandTotal)"></span></div>
                            <div class="flex justify-between items-center">
                                <label for="paid_amount">Paid amount</label>
                                <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="{{ old('paid_amount', $invoice->paid_amount ?? 0) }}" x-ref="paidInput" @input="recompute()" class="w-32 border-gray-300 rounded text-sm text-right">
                            </div>
                            <div class="flex justify-between border-t pt-2"><span>Balance</span><span class="font-mono" x-text="fmt(balance)"></span></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('invoices.index') }}" class="text-gray-500 hover:underline">← Cancel</a>
                    <x-primary-button>{{ $restricted ? 'Save changes' : ($invoice->exists ? 'Save draft' : 'Create draft') }}</x-primary-button>
                </div>
            </form>

            @if ($invoice->exists && $invoice->isEditable() && ! $restricted)
                <div class="mt-6 p-5 bg-red-50 border border-red-200 rounded-lg flex items-center justify-between">
                    <div class="text-sm">
                        <div class="font-semibold text-red-800">Delete this draft</div>
                        <div class="text-red-700">Once deleted, the draft and its line items are gone permanently.</div>
                    </div>
                    <x-confirm-form
                        :action="route('invoices.destroy', $invoice)"
                        method="DELETE"
                        title="Delete this draft?"
                        message="This draft and all its line items are permanently removed. This cannot be undone."
                        confirm-label="Delete draft"
                        confirm-class="bg-red-600 hover:bg-red-700"
                        tone="danger">
                        <button type="button" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 font-semibold text-sm">Delete draft</button>
                    </x-confirm-form>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function invoiceForm(initialItems, customerStates, companyStateId, productIndex) {
            const productMap = {};
            (productIndex || []).forEach(p => { productMap[p.id] = p; });
            return {
                items: initialItems.map(i => ({product_id: null, ...i, amount: 0, tax: 0, total: 0})),
                customerId: @json(old('customer_id', $invoice->customer_id)),
                customerStates,
                companyStateId,
                productMap,
                totals: {subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                balance: 0,
                get isInterstate() {
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                init() { this.recompute(); },
                addRow() {
                    this.items.push({product_id: null, description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, gst_rate: 18, amount: 0, tax: 0, total: 0});
                },
                removeRow(i) {
                    if (this.items.length > 1) this.items.splice(i, 1);
                    this.recompute();
                },
                pickProduct(idx, productId) {
                    const row = this.items[idx];
                    if (!productId) {
                        row.product_id = null;
                        this.recompute();
                        return;
                    }
                    const p = this.productMap[productId];
                    if (!p) return;
                    row.product_id = p.id;
                    row.description = p.name;
                    row.hsn_sac = p.hsn_sac;
                    row.unit = p.unit;
                    row.rate = parseFloat(p.rate) || 0;
                    row.gst_rate = parseFloat(p.gst_rate) || 0;
                    this.recompute();
                },
                recompute() {
                    const inter = this.isInterstate;
                    let sub = 0, cgst = 0, sgst = 0, igst = 0;
                    this.items.forEach(item => {
                        const qty = parseFloat(item.quantity) || 0;
                        const rate = parseFloat(item.rate) || 0;
                        const gst = parseFloat(item.gst_rate) || 0;
                        const amount = +(qty * rate).toFixed(2);
                        let c = 0, s = 0, ig = 0;
                        if (gst > 0) {
                            const tax = +(amount * gst / 100).toFixed(2);
                            if (inter) {
                                ig = tax;
                            } else {
                                c = +(tax / 2).toFixed(2);
                                s = +(tax - c).toFixed(2);
                            }
                        }
                        item.amount = amount;
                        item.tax = +(c + s + ig).toFixed(2);
                        item.total = +(amount + item.tax).toFixed(2);
                        sub += amount; cgst += c; sgst += s; igst += ig;
                    });
                    const totalTax = +(cgst + sgst + igst).toFixed(2);
                    const raw = sub + totalTax;
                    const grand = Math.round(raw);
                    this.totals = {
                        subtotal: +sub.toFixed(2),
                        cgst: +cgst.toFixed(2),
                        sgst: +sgst.toFixed(2),
                        igst: +igst.toFixed(2),
                        totalTax,
                        grandTotal: grand,
                    };
                    const paid = parseFloat(this.$refs.paidInput?.value || 0) || 0;
                    this.balance = +(grand - paid).toFixed(2);
                },
                fmt(n) { return (parseFloat(n) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
            };
        }
    </script>
    @endpush
</x-app-layout>
