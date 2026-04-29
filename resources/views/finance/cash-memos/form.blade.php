<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">New Cash Memo</h2>
            <a href="{{ route('finance.cash-memos.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All memos</a>
        </div>
    </x-slot>

    @php
        $oldItems = old('items', $items);
        if (empty($oldItems)) {
            $oldItems = [['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'amount' => 0]];
        }
        // Default GST: intra-state (CGST/SGST) when seller_state matches company state OR not set.
        $companyStateName = optional($company->state)->name;
    @endphp

    <div class="py-8" x-data='cashMemoForm(@json($oldItems), {{ (int) old('is_interstate', 0) }}, {{ (float) old('gst_rate', 0) }}, {{ (float) old('discount', 0) }})'>
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="p-6 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('finance.cash-memos.store') }}" class="space-y-8">
                    @csrf

                    @if ($errors->any())
                        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                            <div class="font-semibold mb-1">Please fix the following:</div>
                            <ul class="list-disc pl-5 space-y-0.5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- ─── Memo header ─── --}}
                    <section>
                        <h3 class="font-semibold text-gray-900 mb-3 text-sm uppercase tracking-wider text-gray-700">Memo details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="memo_date" value="Date *" />
                                <x-text-input id="memo_date" name="memo_date" type="date" class="mt-1 block w-full"
                                              :value="old('memo_date', now()->toDateString())" required />
                            </div>
                            <div x-data="{ locked: !{{ old('memo_number') ? 'true' : 'false' }} && true }">
                                <div class="flex items-center justify-between">
                                    <x-input-label for="memo_number" value="Memo number" />
                                    <button type="button" @click="locked = !locked; if (!locked) $nextTick(() => $refs.memoInput.focus())"
                                            class="text-[11px] font-semibold text-brand-600 hover:underline">
                                        <span x-show="locked">Edit manually</span>
                                        <span x-show="!locked">Use auto-generated</span>
                                    </button>
                                </div>
                                <input id="memo_number" name="memo_number" type="text" maxlength="40"
                                       x-ref="memoInput"
                                       :readonly="locked"
                                       :class="locked ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-900'"
                                       value="{{ old('memo_number', $nextMemoNumber) }}"
                                       class="mt-1 block w-full font-mono text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <p class="mt-1 text-[11px] text-gray-500">
                                    <span x-show="locked">Auto-generated · FY-based sequence. Click <em>Edit manually</em> to override.</span>
                                    <span x-show="!locked" class="text-amber-700">Custom number — must be unique within your company. Auto counter will <strong>not</strong> advance.</span>
                                </p>
                                @error('memo_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    {{-- ─── Purchased From ─── --}}
                    <section>
                        <h3 class="font-semibold text-gray-900 mb-3 text-sm uppercase tracking-wider text-gray-700">Purchased From <span class="text-red-500">*</span></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <x-input-label for="seller_name" value="Seller / Vendor name *" />
                                <x-text-input id="seller_name" name="seller_name" type="text" class="mt-1 block w-full"
                                              :value="old('seller_name')" placeholder="E.g. Sharma Stationery, Ballabgarh" required maxlength="160" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="seller_address" value="Address" />
                                <textarea id="seller_address" name="seller_address" rows="2" maxlength="500"
                                          placeholder="Shop / street, city, pin code"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('seller_address') }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="seller_gstin" value="GSTIN (if any)" />
                                <x-text-input id="seller_gstin" name="seller_gstin" type="text" class="mt-1 block w-full font-mono uppercase"
                                              :value="old('seller_gstin')" maxlength="20" placeholder="22AAAAA0000A1Z5" />
                            </div>
                            <div>
                                <x-input-label for="seller_phone" value="Phone" />
                                <x-text-input id="seller_phone" name="seller_phone" type="text" class="mt-1 block w-full"
                                              :value="old('seller_phone')" maxlength="30" placeholder="+91 …" />
                            </div>
                            <div>
                                <x-input-label for="seller_state" value="Seller state" />
                                <select id="seller_state" name="seller_state"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— Select —</option>
                                    @foreach ($states as $st)
                                        <option value="{{ $st->name }}" @selected(old('seller_state') === $st->name)>{{ $st->name }}</option>
                                    @endforeach
                                </select>
                                @if ($companyStateName)
                                    <p class="mt-1 text-[11px] text-gray-500">Your company is in <strong>{{ $companyStateName }}</strong>. Different state ⇒ IGST.</p>
                                @endif
                            </div>
                        </div>
                    </section>

                    {{-- ─── Particulars / line items ─── --}}
                    <section>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900 text-sm uppercase tracking-wider text-gray-700">Particulars *</h3>
                            <button type="button" @click="addRow()" class="text-sm text-brand-700 font-semibold hover:underline">+ Add row</button>
                        </div>

                        {{-- Mobile: card layout --}}
                        <div class="md:hidden space-y-3">
                            <template x-for="(item, idx) in items" :key="`m-${idx}`">
                                <div class="border rounded-lg p-3 bg-gray-50 space-y-2">
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span>Item <span x-text="idx + 1"></span></span>
                                        <button type="button" @click="removeRow(idx)" x-show="items.length > 1" class="text-red-600 text-xs">Remove</button>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Description</label>
                                        <input :name="`items[${idx}][description]`" x-model="item.description" maxlength="255" class="mt-1 block w-full border-gray-300 rounded text-sm" required>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="flex items-center justify-between gap-2">
                                                <label class="text-xs text-gray-500 font-semibold">HSN/SAC</label>
                                                <a href="https://services.gst.gov.in/services/searchhsnsac"
                                                   target="_blank" rel="noopener"
                                                   onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
                                                   style="font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px;"
                                                   class="text-brand-600 hover:text-brand-700 hover:underline whitespace-nowrap"
                                                   title="Search HSN/SAC code on the official GST portal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:12px;height:12px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                                    <span>[Search HSN/SAC]</span>
                                                </a>
                                            </div>
                                            <input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" maxlength="10" class="mt-1 block w-full border-gray-300 rounded text-sm font-mono">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 font-semibold">Unit</label>
                                            <input :name="`items[${idx}][unit]`" x-model="item.unit" maxlength="20" class="mt-1 block w-full border-gray-300 rounded text-sm" placeholder="NOS, KGS…">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 font-semibold">Qty</label>
                                            <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="1" min="1" inputmode="numeric" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 font-semibold">Rate (₹)</label>
                                            <input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm pt-2 border-t">
                                        <span class="text-gray-500">Amount</span>
                                        <span class="font-mono font-semibold" x-text="'₹ ' + fmt(item.quantity * item.rate)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Desktop: table --}}
                        <div class="hidden md:block overflow-x-auto border rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-10">#</th>
                                        <th class="px-3 py-2 text-left">Description</th>
                                        <th class="px-3 py-2 text-left">
                                            <span class="inline-flex items-center gap-2">
                                                <span>HSN/SAC</span>
                                                <a href="https://services.gst.gov.in/services/searchhsnsac"
                                                   target="_blank" rel="noopener"
                                                   onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
                                                   style="text-transform:none; letter-spacing:0; font-weight:500; font-size:11px; display:inline-flex; align-items:center; gap:4px;"
                                                   class="text-brand-600 hover:text-brand-700 hover:underline whitespace-nowrap"
                                                   title="Search HSN/SAC code on the official GST portal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                                    <span>[Search HSN/SAC]</span>
                                                </a>
                                            </span>
                                        </th>
                                        <th class="px-3 py-2 text-left">Qty</th>
                                        <th class="px-3 py-2 text-left">Unit</th>
                                        <th class="px-3 py-2 text-right">Rate (₹)</th>
                                        <th class="px-3 py-2 text-right">Amount (₹)</th>
                                        <th class="px-3 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, idx) in items" :key="`d-${idx}`">
                                        <tr class="border-t">
                                            <td class="px-3 py-2 text-gray-500" x-text="idx + 1"></td>
                                            <td class="px-2 py-2">
                                                <input :name="`items[${idx}][description]`" x-model="item.description" maxlength="255" class="w-full border-gray-300 rounded text-sm" required>
                                            </td>
                                            <td class="px-2 py-2">
                                                <input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" maxlength="10" class="w-24 border-gray-300 rounded text-sm font-mono">
                                            </td>
                                            <td class="px-2 py-2">
                                                <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="1" min="1" inputmode="numeric" class="w-20 border-gray-300 rounded text-sm text-right" required>
                                            </td>
                                            <td class="px-2 py-2">
                                                <input :name="`items[${idx}][unit]`" x-model="item.unit" maxlength="20" class="w-20 border-gray-300 rounded text-sm" placeholder="NOS">
                                            </td>
                                            <td class="px-2 py-2">
                                                <input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="0.01" min="0" class="w-24 border-gray-300 rounded text-sm text-right" required>
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono tabular-nums" x-text="fmt(item.quantity * item.rate)"></td>
                                            <td class="px-2 py-2 text-right">
                                                <button type="button" @click="removeRow(idx)" x-show="items.length > 1" class="text-red-600 hover:text-red-800 text-lg leading-none" title="Remove">×</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- ─── Discount + GST + Totals ─── --}}
                    <section>
                        <h3 class="font-semibold text-gray-900 mb-3 text-sm uppercase tracking-wider text-gray-700">Totals</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="discount" value="Discount (₹)" />
                                    <div class="mt-1 relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                                        <input id="discount" name="discount" type="number" step="0.01" min="0"
                                               x-model.number="discount" @input="recompute()"
                                               class="block w-full pl-8 border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="gst_rate" value="GST rate" />
                                    <select id="gst_rate" name="gst_rate" x-model.number="gstRate" @change="recompute()"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="0">No GST (most cash memos)</option>
                                        <option value="5">5%</option>
                                        <option value="12">12%</option>
                                        <option value="18">18%</option>
                                        <option value="28">28%</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Most cash memos from unregistered vendors carry no GST. Add it only if the seller charged tax.</p>
                                </div>
                                <div x-show="gstRate > 0" class="flex items-center gap-2">
                                    <input id="is_interstate" type="checkbox" x-model="isInterstate" @change="recompute()"
                                           name="is_interstate" value="1"
                                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <label for="is_interstate" class="text-sm text-gray-700">Inter-state purchase (charge IGST instead of CGST/SGST)</label>
                                </div>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.subtotal)"></span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Discount</span><span class="font-mono tabular-nums text-red-600" x-text="'− ₹ ' + fmt(totals.discount)"></span></div>
                                <div class="flex justify-between font-medium"><span>Taxable value</span><span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.taxable)"></span></div>
                                <template x-if="!isInterstate && gstRate > 0">
                                    <div>
                                        <div class="flex justify-between"><span class="text-gray-600">CGST <span x-text="'(' + (gstRate/2) + '%)'"></span></span><span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.cgst)"></span></div>
                                        <div class="flex justify-between"><span class="text-gray-600">SGST <span x-text="'(' + (gstRate/2) + '%)'"></span></span><span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.sgst)"></span></div>
                                    </div>
                                </template>
                                <template x-if="isInterstate && gstRate > 0">
                                    <div class="flex justify-between"><span class="text-gray-600">IGST <span x-text="'(' + gstRate + '%)'"></span></span><span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.igst)"></span></div>
                                </template>
                                <div class="flex justify-between text-xs text-gray-500"><span>Round off</span><span class="font-mono tabular-nums" x-text="(totals.roundOff >= 0 ? '+ ' : '− ') + '₹ ' + fmt(Math.abs(totals.roundOff))"></span></div>
                                <div class="flex justify-between pt-2 border-t border-gray-300 font-bold text-base">
                                    <span>Grand Total</span>
                                    <span class="font-mono tabular-nums" x-text="'₹ ' + fmt(totals.grandTotal)"></span>
                                </div>
                                <div class="text-xs text-gray-600 italic pt-1" x-text="amountInWords"></div>
                            </div>
                        </div>
                    </section>

                    {{-- ─── Payment + classification ─── --}}
                    <section>
                        <h3 class="font-semibold text-gray-900 mb-3 text-sm uppercase tracking-wider text-gray-700">Payment &amp; classification</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <x-input-label for="payment_mode" value="Payment mode *" />
                                <select id="payment_mode" name="payment_mode" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank transfer', 'cheque' => 'Cheque', 'other' => 'Other'] as $v => $label)
                                        <option value="{{ $v }}" @selected(old('payment_mode', 'cash') === $v)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="reference_number" value="Reference number" />
                                <x-text-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full font-mono"
                                              :value="old('reference_number')" maxlength="60" placeholder="UPI ref, cheque no., …" />
                            </div>
                            <div>
                                <x-input-label for="expense_category" value="Expense category" />
                                <select id="expense_category" name="expense_category"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    @foreach (config('expense_categories') as $key => $cfg)
                                        <option value="{{ $key }}" @selected(old('expense_category', 'misc') === $key)>{{ $cfg['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Used for the linked Expense entry in your P&amp;L.</p>
                            </div>
                        </div>
                    </section>

                    {{-- ─── Notes ─── --}}
                    <section>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2" maxlength="1000"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                  placeholder="Any additional remarks, terms, etc.">{{ old('notes') }}</textarea>
                    </section>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <a href="{{ route('finance.cash-memos.index') }}" class="text-sm text-gray-500 hover:underline">← Cancel</a>
                        <x-primary-button>Create Cash Memo</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function cashMemoForm(initialItems, initialInterstate, initialGstRate, initialDiscount) {
                return {
                    items: initialItems,
                    isInterstate: !!initialInterstate,
                    gstRate: initialGstRate || 0,
                    discount: initialDiscount || 0,
                    totals: { subtotal: 0, discount: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0, roundOff: 0, grandTotal: 0 },
                    amountInWords: '',
                    init() { this.recompute(); },
                    addRow() {
                        this.items.push({ description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, amount: 0 });
                    },
                    removeRow(i) {
                        if (this.items.length > 1) this.items.splice(i, 1);
                        this.recompute();
                    },
                    fmt(n) {
                        const v = (parseFloat(n) || 0);
                        return v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    recompute() {
                        let subtotal = 0;
                        for (const it of this.items) subtotal += (parseFloat(it.quantity) || 0) * (parseFloat(it.rate) || 0);
                        const discount = parseFloat(this.discount) || 0;
                        const taxable = Math.max(0, subtotal - discount);
                        const rate = parseFloat(this.gstRate) || 0;
                        const gstAmt = Math.round((taxable * rate / 100) * 100) / 100;
                        let cgst = 0, sgst = 0, igst = 0;
                        if (rate > 0) {
                            if (this.isInterstate) igst = gstAmt;
                            else { cgst = Math.round((gstAmt / 2) * 100) / 100; sgst = Math.round((gstAmt - cgst) * 100) / 100; }
                        }
                        const pre = taxable + cgst + sgst + igst;
                        const grand = Math.round(pre);
                        this.totals = {
                            subtotal: subtotal,
                            discount: discount,
                            taxable: taxable,
                            cgst: cgst, sgst: sgst, igst: igst,
                            roundOff: Math.round((grand - pre) * 100) / 100,
                            grandTotal: grand,
                        };
                        this.amountInWords = this.numberToWords(grand);
                    },
                    numberToWords(amount) {
                        // Quick client-side preview; canonical version computed server-side on save.
                        if (!amount || amount <= 0) return '';
                        const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                        const two = (n) => n < 20 ? ones[n] : (tens[Math.floor(n/10)] + (n%10 ? ' ' + ones[n%10] : ''));
                        const conv = (n) => {
                            if (n === 0) return 'Zero';
                            const parts = [];
                            const cr = Math.floor(n / 10000000); n %= 10000000;
                            if (cr) parts.push((cr >= 100 ? two(Math.floor(cr/100)) + ' Hundred ' : '') + two(cr%100) + ' Crore');
                            const lk = Math.floor(n / 100000); n %= 100000;
                            if (lk) parts.push(two(lk) + ' Lakh');
                            const th = Math.floor(n / 1000); n %= 1000;
                            if (th) parts.push(two(th) + ' Thousand');
                            const hu = Math.floor(n / 100); n %= 100;
                            if (hu) parts.push(ones[hu] + ' Hundred');
                            if (n) parts.push(two(n));
                            return parts.join(' ').replace(/\s+/g, ' ').trim();
                        };
                        return 'Rupees ' + conv(Math.floor(amount)) + ' Only';
                    },
                };
            }
        </script>
    </div>
</x-app-layout>
