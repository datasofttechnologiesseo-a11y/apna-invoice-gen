<x-app-layout :title="$isEdit ? 'Edit purchase invoice' : 'New purchase invoice'">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $isEdit ? 'Edit purchase invoice ' . $bill->bill_number : 'New purchase invoice' }}</h1>
            <a href="{{ route('purchases.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All purchase invoices</a>
        </div>
    </x-slot>

    @php
        // Fields are laid out in the order a bill is read: who it is from, the
        // bill's own number and date, the lines, then tax treatment and payment.
        $oldItems = old('items', $items);
        if (empty($oldItems)) {
            $oldItems = [['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'discount' => 0, 'gst_rate' => 18]];
        }
        $initial = [
            'vendorId' => (string) old('vendor_id', $bill->vendor_id ?? ''),
            'interstateOverride' => old('is_interstate', $bill->exists ? ($bill->is_interstate ? 1 : 0) : null),
            'reverseCharge' => (bool) old('reverse_charge', $bill->reverse_charge ?? false),
            'paidNow' => (float) old('paid_now', 0),
            // From the GSTR-2B match: a supplier not saved yet opens quick-add, filled in.
            'prefillVendor' => $prefillVendor ?? null,
            // Saved vendors for the picker (name and GSTIN, as people search them).
            'vendors' => $vendors->map(fn ($v) => ['id' => (string) $v->id, 'label' => $v->name . ($v->gstin ? ' · ' . $v->gstin : '')])->values(),
            // Bill-level discount: re-shown form keeps the typed value; an existing
            // bill opens with the sum of its saved line discounts.
            'discount' => (float) old('bill_discount', collect($oldItems)->sum(fn ($i) => (float) ($i['discount'] ?? 0))),
        ];
        $commonRates = [0, 5, 18, 40];
        $rareRates = collect(config('gst.rates'))->whereNotIn('value', $commonRates)->values();
    @endphp

    <div class="py-8"
         x-data='purchaseBillForm(@json($oldItems), @json($vendorStates), {{ $company->state_id ?? 'null' }}, @json($utStateIds), @json($initial), @json(route('vendors.store')), @json(csrf_token()), @json($products))'>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ $isEdit ? route('purchases.update', $bill) : route('purchases.store') }}" class="space-y-6">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                @if ($errors->any())
                    <div class="p-4 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm">
                        <div class="font-semibold mb-1">Correct the following before saving:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                @if (session('error'))
                    <div class="p-3 bg-danger-50 border border-danger-200 text-danger-800 rounded text-sm" role="alert">{{ session('error') }}</div>
                @endif

                {{-- 1. Vendor + bill identity --}}
                <div class="bg-white p-6 rounded-xl ring-1 ring-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-5">
                            <div class="flex items-center justify-between">
                                <x-input-label for="vendor_id" value="Vendor *" />
                                <button type="button" @click="quickAdd.open = !quickAdd.open" class="text-xs font-semibold text-brand-700 hover:underline">+ New vendor</button>
                            </div>
                            <select id="vendor_id" name="vendor_id" x-model="vendorId" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="">Select vendor</option>
                                <template x-for="v in vendorList" :key="v.id">
                                    <option :value="String(v.id)" x-text="v.label" :selected="String(v.id) === vendorId"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-500" x-show="vendorId">
                                Tax mode: <strong x-text="taxModeLabel"></strong>
                                <span x-show="!interstateOverridden">(based on the vendor's state)</span>
                            </p>

                            {{-- Quick-add: name, GSTIN, state - enough for a bill. Address can follow in Vendors. --}}
                            <div x-show="quickAdd.open" x-cloak class="mt-3 p-3 rounded-lg bg-brand-50 border border-brand-200 space-y-2">
                                <div class="text-xs font-bold uppercase tracking-wider text-brand-800">New vendor</div>
                                <input type="text" x-model="quickAdd.name" placeholder="Vendor name *" aria-label="New vendor name" aria-required="true" maxlength="160" autocomplete="off" class="block w-full border-gray-300 rounded text-sm">
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="quickAdd.gstin" placeholder="GSTIN (optional)" aria-label="New vendor GSTIN (optional)" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" class="block w-full border-gray-300 rounded text-sm font-mono uppercase">
                                    <select x-model="quickAdd.state_id" aria-label="New vendor state" class="block w-full border-gray-300 rounded text-sm">
                                        <option value="">State</option>
                                        @foreach ($states as $st)
                                            <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->gst_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p x-show="quickAdd.error" x-text="quickAdd.error" role="alert" class="text-xs text-danger-700"></p>
                                <div class="flex gap-2">
                                    <button type="button" @click="saveVendor()" :disabled="quickAdd.saving" class="min-h-[36px] px-3 py-1.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded disabled:opacity-50">Save vendor</button>
                                    <button type="button" @click="quickAdd.open = false" class="min-h-[36px] px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800">Cancel</button>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label for="bill_number" value="Vendor's invoice no. *" />
                            <x-text-input id="bill_number" name="bill_number" type="text" class="mt-1 block w-full font-mono" :value="old('bill_number', $bill->bill_number)" maxlength="40" required placeholder="As printed on their invoice" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="bill_date" value="Invoice date *" />
                            <x-text-input id="bill_date" name="bill_date" type="date" class="mt-1 block w-full" :value="old('bill_date', optional($bill->bill_date)->toDateString() ?? now()->toDateString())" required />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="due_date" value="Due date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', optional($bill->due_date)->toDateString())" />
                        </div>
                    </div>
                </div>

                {{-- 2. Line items --}}
                <div class="bg-white p-6 rounded-xl ring-1 ring-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-gray-900 text-sm uppercase tracking-wider text-gray-700">Items on the invoice *</h2>
                        <button type="button" @click="addRow()" class="text-sm text-brand-700 font-semibold hover:underline">+ Add row</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-2 py-2 text-left w-8">#</th>
                                    <th class="px-2 py-2 text-left">Description</th>
                                    <th class="px-2 py-2 text-left">HSN/SAC</th>
                                    <th class="px-2 py-2 text-right">Qty</th>
                                    <th class="px-2 py-2 text-left">Unit</th>
                                    <th class="px-2 py-2 text-right">Rate (₹)</th>
                                    <th class="px-2 py-2 text-right">GST%</th>
                                    <th class="px-2 py-2 text-right">Amount (incl. GST)</th>
                                    <th class="px-2 py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="idx">
                                    <tr class="border-t">
                                        <td class="px-2 py-2 text-gray-500" x-text="idx + 1"></td>
                                        <td class="px-2 py-2">
                                            <template x-if="products.length">
                                                <select :name="`items[${idx}][product_id]`" :aria-label="`Item for line ${idx + 1}`" x-model="item.product_id" @change="useProduct(item)"
                                                        class="mb-1 w-full min-w-[12rem] border-gray-200 rounded text-xs text-gray-600 bg-gray-50">
                                                    <option value="">Link to an item (optional)</option>
                                                    <template x-for="p in products" :key="p.id">
                                                        <option :value="p.id" x-text="p.name + (p.track ? ' · stock' : '')" :selected="p.id === item.product_id"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <input :name="`items[${idx}][description]`" :aria-label="`Description for line ${idx + 1}`" x-model="item.description" maxlength="255" required class="w-full min-w-[12rem] border-gray-300 rounded text-sm">
                                            <p x-show="isTracked(item)" x-cloak class="mt-0.5 text-[11px] font-semibold text-money-700">Adds to stock</p>
                                        </td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" :aria-label="`HSN or SAC for line ${idx + 1}`" x-model="item.hsn_sac" maxlength="10" inputmode="numeric" class="w-24 border-gray-300 rounded text-sm font-mono"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][quantity]`" :aria-label="`Quantity for line ${idx + 1}`" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" required class="w-20 border-gray-300 rounded text-sm text-right"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][unit]`" :aria-label="`Unit for line ${idx + 1}`" x-model="item.unit" maxlength="20" placeholder="NOS" class="w-20 border-gray-300 rounded text-sm"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" :aria-label="`Rate for line ${idx + 1}`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" required class="w-28 border-gray-300 rounded text-sm text-right"></td>
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][gst_rate]`" :aria-label="`GST rate for line ${idx + 1}`" x-model.number="item.gst_rate" @change="recompute()" class="w-24 border-gray-300 rounded text-sm">
                                                @foreach ($commonRates as $r)<option value="{{ $r }}">{{ $r }}%</option>@endforeach
                                                @foreach ($rareRates as $r)<option value="{{ $r['value'] }}">{{ rtrim(rtrim(number_format($r['value'], 2), '0'), '.') }}%{{ in_array((float) $r['value'], [12.0, 28.0], true) ? ' (legacy)' : '' }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono tabular-nums">
                                            <div class="font-medium" x-text="fmt(item.total)"></div>
                                            <div class="text-[11px] text-gray-500" x-show="item.tax > 0" x-text="'Taxable ' + fmt(item.amount)"></div>
                                        </td>
                                        <td class="px-2 py-2 text-right"><button type="button" @click="removeRow(idx)" x-show="items.length > 1" class="text-danger-600 hover:text-danger-800 text-lg leading-none" title="Remove">×</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 3. Tax treatment + totals --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl ring-1 ring-gray-200 shadow-sm space-y-4">
                        <h2 class="font-semibold text-sm uppercase tracking-wider text-gray-700">Tax treatment</h2>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="hidden" name="is_interstate" :value="isInterstate ? 1 : 0" x-bind:disabled="!interstateOverridden">
                            <input type="checkbox" x-model="interstateOverridden" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-800">Set tax type manually</span>
                                <span class="block text-xs text-gray-500">Normally set by the vendor's state. Override for bill-to/ship-to cases.</span></span>
                        </label>
                        <div x-show="interstateOverridden" x-cloak class="ml-6 flex gap-4 text-sm">
                            <label class="inline-flex items-center gap-1.5"><input type="radio" :checked="!manualInterstate" @change="manualInterstate = false; recompute()" class="border-gray-300 text-brand-700"> CGST + <span x-text="stateTaxLabel"></span></label>
                            <label class="inline-flex items-center gap-1.5"><input type="radio" :checked="manualInterstate" @change="manualInterstate = true; recompute()" class="border-gray-300 text-brand-700"> IGST</label>
                        </div>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="reverse_charge" value="1" x-model="reverseCharge" @change="recompute()" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-800">Reverse charge (GST payable by you)</span>
                                <span class="block text-xs text-gray-500">Section 9(3)/9(4), e.g. GTA, legal and security services, import of services, commercial rent from an unregistered owner. The vendor charges no tax; you pay it in GSTR-3B 3.1(d) and claim ITC if eligible.</span></span>
                        </label>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="itc_eligible" value="1" @checked(old('itc_eligible', $bill->exists ? $bill->itc_eligible : true)) class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-800">Input tax credit claimable</span>
                                <span class="block text-xs text-gray-500">Untick for blocked credit under Section 17(5) (motor vehicles, staff food and catering, personal use). The GST is then added to cost.</span></span>
                        </label>

                        <div>
                            <x-input-label for="expense_category" value="Expense category (for the P&L)" />
                            <select id="expense_category" name="expense_category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                @foreach ($categories as $key => $cat)
                                    <option value="{{ $key }}" @selected(old('expense_category', $bill->expense_category ?? 'misc') === $key)>{{ $cat['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('notes', $bill->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl ring-1 ring-gray-200 shadow-sm">
                        <h2 class="font-semibold text-sm uppercase tracking-wider text-gray-700 mb-3">Totals</h2>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between"><dt class="text-gray-600">Items total</dt><dd class="font-mono tabular-nums" x-text="fmt(totals.gross)"></dd></div>
                            {{-- One discount for the whole bill, applied before GST and spread
                                 across the lines (same as sales invoices). --}}
                            <div class="flex justify-between items-center gap-2">
                                <dt class="text-gray-600 flex items-center gap-1.5">Discount
                                    <span class="inline-flex rounded border border-gray-300 overflow-hidden text-[11px] font-semibold">
                                        <button type="button" @click="discountMode = 'amount'; recompute()" :class="discountMode === 'amount' ? 'bg-brand-700 text-white' : 'bg-white text-gray-600'" class="px-1.5 py-0.5">₹</button>
                                        <button type="button" @click="discountMode = 'percent'; recompute()" :class="discountMode === 'percent' ? 'bg-brand-700 text-white' : 'bg-white text-gray-600'" class="px-1.5 py-0.5">%</button>
                                    </span>
                                </dt>
                                <dd><input type="number" step="any" min="0" inputmode="decimal" x-model.number="discountInput" @input="recompute()" aria-label="Discount" class="w-28 border-gray-300 rounded text-sm text-right" placeholder="0">
                                    <input type="hidden" name="bill_discount" :value="totals.discount"></dd>
                            </div>
                            <div class="flex justify-between border-t pt-1.5"><dt class="text-gray-600">Taxable value</dt><dd class="font-mono tabular-nums" x-text="fmt(totals.subtotal)"></dd></div>
                            <template x-if="!isInterstate">
                                <div>
                                    <div class="flex justify-between"><dt class="text-gray-600">CGST</dt><dd class="font-mono tabular-nums" x-text="fmt(totals.cgst)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-600" x-text="stateTaxLabel"></dt><dd class="font-mono tabular-nums" x-text="fmt(totals.sgst)"></dd></div>
                                </div>
                            </template>
                            <template x-if="isInterstate">
                                <div class="flex justify-between"><dt class="text-gray-600">IGST</dt><dd class="font-mono tabular-nums" x-text="fmt(totals.igst)"></dd></div>
                            </template>
                            <div class="flex justify-between"><dt class="text-gray-600">Round off</dt><dd class="font-mono tabular-nums" x-text="fmt(totals.roundOff)"></dd></div>
                            <div class="flex justify-between border-t pt-2 mt-2 text-base"><dt class="font-bold text-gray-900">Payable to vendor</dt><dd class="font-mono tabular-nums font-extrabold text-gray-900" x-text="'₹ ' + fmt(totals.grandTotal)"></dd></div>
                        </dl>
                        <p x-show="reverseCharge && totals.tax > 0" x-cloak class="mt-3 text-xs text-accent-800 bg-accent-50 border border-accent-200 rounded px-2 py-1.5">
                            Reverse charge: <strong x-text="'₹ ' + fmt(totals.tax)"></strong> GST is payable by you to the government, not the vendor, in GSTR-3B 3.1(d).
                        </p>

                        {{-- 4. Paid now - most small bills are settled on the spot. --}}
                        <div class="mt-5 pt-4 border-t border-gray-100">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Paid already?</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="paid_now" class="text-xs text-gray-600">Amount paid (₹)</label>
                                    <input inputmode="decimal" id="paid_now" name="paid_now" type="number" step="any" min="0" x-model.number="paidNow" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" placeholder="0">
                                    <button type="button" @click="paidNow = totals.grandTotal" class="mt-1 text-[11px] text-brand-700 hover:underline">Paid in full</button>
                                </div>
                                <div>
                                    <label for="payment_method" class="text-xs text-gray-600">Method</label>
                                    <select id="payment_method" name="payment_method" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        @foreach ($methods as $key => $m)
                                            <option value="{{ $key }}" @selected(old('payment_method', 'neft') === $key)>{{ $m['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="paidNow > 0" x-cloak>
                                    <label for="paid_on" class="text-xs text-gray-600">Paid on</label>
                                    <input id="paid_on" name="paid_on" type="date" value="{{ old('paid_on', now()->toDateString()) }}" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                                <div x-show="paidNow > 0" x-cloak>
                                    <label for="reference_number" class="text-xs text-gray-600">Reference / UTR</label>
                                    <input id="reference_number" name="reference_number" type="text" value="{{ old('reference_number', $bill->reference_number) }}" maxlength="80" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                            </div>
                            @if ($isEdit)
                                <p class="mt-2 text-[11px] text-gray-500">Manage recorded payments on the purchase invoice page.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('purchases.index') }}" class="text-sm text-gray-500 hover:underline">← Cancel</a>
                    <x-primary-button>{{ $isEdit ? 'Save changes' : 'Record purchase invoice' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function purchaseBillForm(initialItems, vendorStates, companyStateId, utStateIds, initial, vendorStoreUrl, csrf, products) {
            return {
                items: initialItems.map(i => ({ discount: 0, gst_rate: 18, product_id: '', ...i, product_id: String(i.product_id || ''), amount: 0, tax: 0, total: 0 })),
                products: products || [],
                isTracked(item) { const p = this.products.find(x => x.id === item.product_id); return !!(p && p.track); },
                // Fill the line from the item; what the user already typed wins.
                useProduct(item) {
                    const p = this.products.find(x => x.id === item.product_id);
                    if (!p) return;
                    if (!item.description) item.description = p.name;
                    if (!item.hsn_sac) item.hsn_sac = p.hsn_sac || '';
                    if (!item.unit) item.unit = p.unit || '';
                    if (!(parseFloat(item.rate) > 0) && p.rate > 0) item.rate = p.rate;
                    item.gst_rate = p.gst_rate;
                    this.recompute();
                },
                vendorStates: vendorStates || {},
                vendorList: (initial.vendors || []).slice(),
                companyStateId,
                vendorId: initial.vendorId || '',
                interstateOverridden: initial.interstateOverride !== null && initial.interstateOverride !== undefined,
                manualInterstate: !!(initial.interstateOverride),
                reverseCharge: !!initial.reverseCharge,
                paidNow: initial.paidNow || 0,
                quickAdd: { open: !!(initial.prefillVendor && initial.prefillVendor.gstin), saving: false, error: '',
                    name: (initial.prefillVendor && initial.prefillVendor.name) || '', gstin: (initial.prefillVendor && initial.prefillVendor.gstin) || '', state_id: '' },
                totals: { gross: 0, discount: 0, subtotal: 0, cgst: 0, sgst: 0, igst: 0, tax: 0, roundOff: 0, grandTotal: 0 },
                discountMode: 'amount',
                discountInput: initial.discount || '',
                get vendorStateId() { return this.vendorId ? this.vendorStates[this.vendorId] : null; },
                get autoInterstate() {
                    const vs = this.vendorStateId;
                    // Number(): ids arrive as strings from the quick-add response and
                    // as integers from the page, and "26" !== 26 would flip to IGST.
                    return !!(vs && this.companyStateId && Number(vs) !== Number(this.companyStateId));
                },
                get isInterstate() { return this.interstateOverridden ? this.manualInterstate : this.autoInterstate; },
                get stateTaxLabel() { return utStateIds.includes(this.companyStateId) ? 'UTGST' : 'SGST'; },
                get taxModeLabel() { return this.isInterstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + ' + this.stateTaxLabel + ')'; },
                init() {
                    this.recompute();
                    this.$watch('vendorId', () => this.recompute());
                },
                addRow() { this.items.push({ product_id: '', description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, discount: 0, gst_rate: 18, amount: 0, tax: 0, total: 0 }); },
                removeRow(i) { if (this.items.length > 1) this.items.splice(i, 1); this.recompute(); },
                fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                recompute() {
                    // Same arithmetic as InvoiceCalculator: line discount before tax,
                    // each head at half the rate rounded on its own.
                    const inter = this.isInterstate;
                    let sub = 0, cgst = 0, sgst = 0, igst = 0;
                    // Bill-level discount spread pro rata before tax
                    // (InvoiceCalculator::apportionDiscount on the server).
                    const grossOf = item => +((parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0)).toFixed(2);
                    const grossList = this.items.map(grossOf);
                    const totalGross = +grossList.reduce((a, b) => a + b, 0).toFixed(2);
                    const input = Math.max(0, parseFloat(this.discountInput) || 0);
                    let discount = this.discountMode === 'percent' ? +(totalGross * Math.min(input, 100) / 100).toFixed(2) : +input.toFixed(2);
                    discount = Math.min(discount, totalGross);
                    let lastIdx = -1;
                    grossList.forEach((g, i) => { if (g > 0) lastIdx = i; });
                    let allocated = 0;
                    this.items.forEach((item, i) => {
                        if (discount <= 0 || grossList[i] <= 0) { item.discount = 0; return; }
                        let share = i === lastIdx ? +(discount - allocated).toFixed(2) : +(discount * grossList[i] / totalGross).toFixed(2);
                        share = Math.min(share, grossList[i]);
                        item.discount = share;
                        allocated = +(allocated + share).toFixed(2);
                    });
                    this.items.forEach(item => {
                        const gross = grossOf(item);
                        const disc = Math.max(0, Math.min(parseFloat(item.discount) || 0, gross));
                        const amount = +(gross - disc).toFixed(2);
                        const gst = parseFloat(item.gst_rate) || 0;
                        let c = 0, s = 0, ig = 0;
                        if (gst > 0) {
                            if (inter) ig = +(amount * gst / 100).toFixed(2);
                            else { c = +(amount * gst / 200).toFixed(2); s = c; }
                        }
                        item.amount = amount; item.tax = +(c + s + ig).toFixed(2); item.total = +(amount + item.tax).toFixed(2);
                        sub += amount; cgst += c; sgst += s; igst += ig;
                    });
                    const tax = +(cgst + sgst + igst).toFixed(2);
                    const owed = this.reverseCharge ? sub : sub + tax;
                    const grand = Math.round(owed);
                    this.totals = { gross: totalGross, discount: +allocated.toFixed(2), subtotal: +sub.toFixed(2), cgst: +cgst.toFixed(2), sgst: +sgst.toFixed(2), igst: +igst.toFixed(2), tax, roundOff: +(grand - owed).toFixed(2), grandTotal: grand };
                },
                async saveVendor() {
                    this.quickAdd.error = '';
                    if (!this.quickAdd.name.trim()) { this.quickAdd.error = 'Vendor name is required.'; return; }
                    this.quickAdd.saving = true;
                    try {
                        const res = await fetch(vendorStoreUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ name: this.quickAdd.name.trim(), gstin: this.quickAdd.gstin.trim() || null, state_id: this.quickAdd.state_id || null }),
                        });
                        const body = await res.json();
                        if (!res.ok) { this.quickAdd.error = body.message || Object.values(body.errors || {}).flat().join(' ') || 'Could not save the vendor.'; return; }
                        const label = body.name + (body.state_name ? ' · ' + body.state_name : '') + (body.gstin ? ' · ' + body.gstin : '');
                        this.vendorList.push({ id: String(body.id), label });
                        this.vendorStates[String(body.id)] = body.state_id;
                        this.$nextTick(() => { this.vendorId = String(body.id); });
                        this.quickAdd = { open: false, saving: false, error: '', name: '', gstin: '', state_id: '' };
                    } catch (e) {
                        this.quickAdd.error = 'Could not save the vendor. Check your connection and try again.';
                    } finally {
                        this.quickAdd.saving = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
