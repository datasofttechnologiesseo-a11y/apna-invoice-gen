<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $quotation->exists ? 'Edit ' . $quotation->displayNumber() : 'New quotation' }}
        </h2>
    </x-slot>

    @php
        $existingItems = $quotation->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'description' => $i->description,
            'hsn_sac' => $i->hsn_sac,
            'quantity' => (float) $i->quantity,
            'unit' => $i->unit,
            'rate' => (float) $i->rate,
            'discount' => (float) $i->discount,
            'gst_rate' => (float) $i->gst_rate,
        ])->toArray();
        $oldItems = old('items', $existingItems);
        if (empty($oldItems)) {
            $oldItems = [['product_id' => null, 'description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'discount' => 0, 'gst_rate' => 18]];
        }
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        $companyStateId = $company->state_id;
    @endphp

    <div class="py-10" x-data='quotationForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }}, @json($productIndex))'
         @customer-added.window="addCustomer($event.detail)">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-breadcrumbs :items="[
                ['label' => 'Quotations', 'href' => route('quotations.index')],
                ['label' => $quotation->exists ? $quotation->displayNumber() : 'New quotation'],
            ]" />

            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @unless ($quotation->exists)
                <div class="p-4 bg-brand-50 border border-brand-200 text-brand-900 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm">
                        <div class="font-semibold">Quotation, not a tax invoice.</div>
                        <div class="mt-0.5">A quote is a price proposal you send before a sale. It doesn't go on GSTR-1 or GSTR-3B. When the customer accepts, click <strong>Convert to Invoice</strong> — that's when GST is officially charged.</div>
                    </div>
                </div>
            @endunless

            <form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}" class="space-y-6">
                @csrf
                @if ($quotation->exists) @method('PATCH') @endif

                <div class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="customer_id" value="Customer *" />
                            <div class="flex items-center gap-2 mt-1">
                                <select id="customer_id" name="customer_id" x-model="customerId" @change="recompute()" class="block w-full border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">— Select customer —</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id) == $c->id)>
                                            {{ $c->name }}{{ $c->state?->name ? ' — ' . $c->state->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" @click="$dispatch('open-quick-customer')" class="inline-flex items-center justify-center min-h-[40px] px-3 text-brand-700 hover:text-white hover:bg-brand-600 ring-1 ring-brand-200 rounded-md text-sm font-semibold whitespace-nowrap" title="Add a new customer without leaving this page">+ New</button>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Quote no." />
                            <div class="mt-1 py-2 font-mono text-sm">
                                @if ($quotation->exists && $quotation->quote_number)
                                    <span class="text-gray-900 font-semibold">{{ $quotation->quote_number }}</span>
                                @else
                                    <span class="text-brand-700 font-semibold">{{ $previewNumber }}</span>
                                    <span class="block text-[10px] text-gray-500 uppercase tracking-wider font-sans">Auto-assigned when sent</span>
                                @endif
                            </div>
                        </div>

                        <input type="hidden" name="currency" value="INR">
                        <input type="hidden" name="style" value="{{ old('style', $quotation->style ?? 'classic') }}">

                        <div>
                            <x-input-label for="quote_date" value="Quote date *" />
                            <x-text-input id="quote_date" name="quote_date" type="date" class="mt-1 block w-full"
                                          :value="old('quote_date', $quotation->quote_date?->toDateString() ?? now()->toDateString())" required />
                        </div>

                        <div x-data="{
                            setValidity(days) {
                                const d = new Date();
                                d.setDate(d.getDate() + days);
                                this.$refs.validUntil.value = d.toISOString().slice(0, 10);
                            }
                        }">
                            <x-input-label for="valid_until" value="Valid until" />
                            <x-text-input id="valid_until" name="valid_until" type="date" class="mt-1 block w-full" x-ref="validUntil"
                                          :value="old('valid_until', $quotation->valid_until?->toDateString() ?? now()->addDays(30)->toDateString())" />
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ([7 => '7d', 15 => '15d', 30 => '30d', 60 => '60d'] as $days => $label)
                                    <button type="button" @click="setValidity({{ $days }})"
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 hover:bg-brand-100 hover:text-brand-700 text-gray-600 font-mono">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Tax mode (auto)" />
                            <div class="mt-2 text-sm">
                                <span x-show="!customerId" class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded">Select a customer…</span>
                                <span x-show="customerId && isInterstate" class="inline-block px-2 py-0.5 bg-amber-100 text-amber-800 rounded">Inter-state (IGST)</span>
                                <span x-show="customerId && !isInterstate" class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded">Intra-state (CGST + SGST)</span>
                            </div>
                            <p class="mt-1 text-[10px] text-gray-500">Mode is decided by your state vs. customer's state.</p>
                        </div>
                    </div>
                </div>

                {{-- Quote details — subject, reference and delivery period are
                     standard on Indian B2B quotations. All optional, but having
                     them surfaced means the operator can fill them in seconds
                     instead of cramming them into the free-text notes field. --}}
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <div>
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full"
                                      :value="old('subject', $quotation->subject)"
                                      placeholder="e.g. Quotation for supply of office furniture" maxlength="200" />
                        <p class="mt-1 text-[10px] text-gray-500">A one-line title shown at the top of the PDF — helps the customer file it against their RFQ.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="reference" value="Customer reference (optional)" />
                            <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full"
                                          :value="old('reference', $quotation->reference)"
                                          placeholder="e.g. Your enquiry ABC/RFQ/26-27/021 dated 12-Apr-2026" maxlength="100" />
                            <p class="mt-1 text-[10px] text-gray-500">Quote your customer's enquiry number — lets them track this against their PO chain.</p>
                        </div>
                        <div>
                            <x-input-label for="delivery_period" value="Delivery period (optional)" />
                            <x-text-input id="delivery_period" name="delivery_period" type="text" class="mt-1 block w-full"
                                          :value="old('delivery_period', $quotation->delivery_period)"
                                          placeholder="e.g. 15-20 working days from order confirmation" maxlength="100" />
                            <p class="mt-1 text-[10px] text-gray-500">Standard on Indian quotations — sets buyer expectation upfront.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b flex items-center justify-between gap-3 flex-wrap">
                        <h3 class="font-medium text-gray-900">Line items</h3>
                        <button type="button" @click="addRow" class="inline-flex items-center justify-center min-h-[40px] px-3 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded-md text-sm font-semibold">+ Add row</button>
                    </div>

                    {{-- Mobile / tablet stacked cards --}}
                    <div class="lg:hidden divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="idx">
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase font-bold tracking-wider text-gray-500">Item <span x-text="idx + 1"></span></span>
                                    <button type="button" @click="removeRow(idx)"
                                            :disabled="items.length <= 1"
                                            :class="items.length <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-red-600'"
                                            class="text-sm font-medium transition"
                                            aria-label="Remove row">Remove</button>
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
                                    <input :name="`items[${idx}][description]`" x-model="item.description" maxlength="150" placeholder="e.g. Website development — Phase 1" class="mt-1 block w-full border-gray-300 rounded text-sm" required>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <div class="flex items-center justify-between gap-1">
                                            <label class="text-xs text-gray-500 font-semibold">HSN/SAC</label>
                                            @include('partials.hsn-search-link')
                                        </div>
                                        <input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" maxlength="8" placeholder="998314" class="mt-1 block w-full border-gray-300 rounded text-sm font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Unit</label>
                                        <input :name="`items[${idx}][unit]`" x-model="item.unit" class="mt-1 block w-full border-gray-300 rounded text-sm" placeholder="NOS, HRS…">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Quantity</label>
                                        <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Rate (₹)</label>
                                        <input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Discount (₹)</label>
                                        <input :name="`items[${idx}][discount]`" x-model.number="item.discount" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">GST rate</label>
                                        <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                            @foreach (config('gst.rates') as $r)
                                                <option value="{{ $r['value'] }}" title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-between pt-2 border-t text-sm">
                                    <span class="text-gray-500">Line amount</span>
                                    <span class="font-mono font-semibold text-gray-900" x-text="'₹ ' + fmt(item.amount)"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Desktop table --}}
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    @if ($productIndex->isNotEmpty())
                                        <th class="px-3 py-2 text-left">Product</th>
                                    @endif
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span>HSN/SAC</span>
                                            @include('partials.hsn-search-link')
                                        </span>
                                    </th>
                                    <th class="px-3 py-2 text-left">Quantity</th>
                                    <th class="px-3 py-2 text-right">Rate (₹)</th>
                                    <th class="px-3 py-2 text-right">Disc (₹)</th>
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
                                        <td class="px-2 py-2"><input :name="`items[${idx}][description]`" x-model="item.description" maxlength="150" placeholder="e.g. Website development" class="w-full border-gray-300 rounded text-sm" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" maxlength="8" placeholder="998314" class="w-28 border-gray-300 rounded text-sm font-mono"></td>
                                        <td class="px-2 py-2">
                                            <div class="flex items-center gap-1">
                                                <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="w-20 border-gray-300 rounded text-sm text-right" required>
                                                <input :name="`items[${idx}][unit]`" x-model="item.unit" class="w-20 border-gray-300 rounded text-sm" placeholder="unit">
                                            </div>
                                        </td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="w-28 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][discount]`" x-model.number="item.discount" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" placeholder="0.00" class="w-24 border-gray-300 rounded text-sm text-right"></td>
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="w-36 border-gray-300 rounded text-sm">
                                                @foreach (config('gst.rates') as $r)
                                                    <option value="{{ $r['value'] }}" title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-sm font-medium" x-text="fmt(item.amount)"></td>
                                        <td class="px-2 py-2 text-right w-12">
                                            {{-- Always render so the column doesn't collapse; disable when
                                                 it's the last row so the form keeps at least one item. --}}
                                            <button type="button" @click="removeRow(idx)"
                                                    :disabled="items.length <= 1"
                                                    :title="items.length <= 1 ? 'At least one line item is required' : 'Remove this row'"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-md text-lg leading-none transition"
                                                    :class="items.length <= 1
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : 'text-red-500 hover:text-white hover:bg-red-500'"
                                                    aria-label="Remove row">×</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-t bg-gray-50">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="terms" value="Terms & conditions" />
                                <textarea id="terms" name="terms" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono text-xs"
                                          placeholder="1. Payment: 50% advance on order, balance against proforma before dispatch.&#10;2. Taxes: GST extra at applicable rates.&#10;3. Freight: At actuals, on customer's account.&#10;4. Warranty: 12 months from date of dispatch.&#10;5. Disputes: Subject to {{ $company->city ?? 'local' }} jurisdiction.">{{ old('terms', $quotation->terms ?? $company->default_terms) }}</textarea>
                                <p class="mt-1 text-[10px] text-gray-500">Numbered terms render cleanly on the PDF. The placeholder shows a typical Indian B2B set — adapt to your business.</p>
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes (shown below Terms)" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $quotation->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="md:pl-6 space-y-2 text-sm">
                            <div class="flex justify-between"><span>Subtotal</span><span class="font-mono" x-text="fmt(totals.subtotal)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>SGST</span><span class="font-mono" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono" x-text="fmt(totals.grandTotal)"></span></div>
                            <p class="pt-2 text-[11px] text-gray-500">This is a price proposal — no GST is charged or filed yet. Convert to a tax invoice after the customer confirms.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('quotations.index') }}" class="text-gray-500 hover:underline">← Cancel</a>
                    <x-primary-button>{{ $quotation->exists ? 'Save changes' : 'Create draft' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Inline "+ New customer" modal — adds without leaving the page so typed
         line items stay intact. --}}
    <x-quick-customer-modal :states="$states" />

    @push('scripts')
    <script>
        function quotationForm(initialItems, customerStates, companyStateId, productIndex) {
            const productMap = {};
            (productIndex || []).forEach(p => { productMap[p.id] = p; });
            return {
                items: initialItems.map(i => ({product_id: null, ...i, amount: 0, tax: 0, total: 0})),
                customerId: @json(old('customer_id', $quotation->customer_id)),
                customerStates,
                companyStateId,
                productMap,
                totals: {subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                get isInterstate() {
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                init() { this.recompute(); },
                /**
                 * Wired to @customer-added.window — pushes the new customer
                 * (saved by the inline modal) into the dropdown and selects it,
                 * so the quotation's typed line items aren't lost.
                 */
                addCustomer(c) {
                    if (!c || !c.id) return;
                    const select = document.getElementById('customer_id');
                    if (select) {
                        const option = document.createElement('option');
                        option.value = c.id;
                        option.textContent = c.name + (c.state_name ? ' — ' + c.state_name : '');
                        select.appendChild(option);
                    }
                    this.customerStates[c.id] = c.state_id;
                    this.customerId = String(c.id);
                    this.recompute();
                },
                addRow() {
                    this.items.push({product_id: null, description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, discount: 0, gst_rate: 18, amount: 0, tax: 0, total: 0});
                },
                removeRow(i) {
                    if (this.items.length > 1) this.items.splice(i, 1);
                    this.recompute();
                },
                pickProduct(idx, productId) {
                    const row = this.items[idx];
                    if (!productId) { row.product_id = null; this.recompute(); return; }
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
                        const gross = +(qty * rate).toFixed(2);
                        const disc = Math.max(0, Math.min(parseFloat(item.discount) || 0, gross));
                        const amount = +(gross - disc).toFixed(2);
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
                    this.totals = {
                        subtotal: +sub.toFixed(2),
                        cgst: +cgst.toFixed(2),
                        sgst: +sgst.toFixed(2),
                        igst: +igst.toFixed(2),
                        totalTax,
                        grandTotal: Math.round(sub + totalTax),
                    };
                },
                fmt(n) { return (parseFloat(n) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
            };
        }
    </script>
    @endpush
</x-app-layout>
