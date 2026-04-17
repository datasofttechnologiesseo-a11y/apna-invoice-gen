<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $invoice->exists ? 'Edit invoice ' . ($invoice->invoice_number ?? '(draft)') : 'New invoice' }}
        </h2>
    </x-slot>

    @php
        $existingItems = $invoice->items->map(fn ($i) => [
            'description' => $i->description,
            'hsn_sac' => $i->hsn_sac,
            'quantity' => (float) $i->quantity,
            'unit' => $i->unit,
            'rate' => (float) $i->rate,
            'gst_rate' => (float) $i->gst_rate,
        ])->toArray();
        $oldItems = old('items', $existingItems);
        if (empty($oldItems)) {
            $oldItems = [['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'gst_rate' => 18]];
        }
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        $companyStateId = $company->state_id;
    @endphp

    <div class="py-10" x-data='invoiceForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }})'>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
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
                                <select id="customer_id" name="customer_id" x-model="customerId" @change="recompute()" class="block w-full border-gray-300 rounded-md shadow-sm" required>
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
                            <div class="mt-1 py-2 text-gray-700 font-mono text-sm">
                                {{ $invoice->invoice_number && ! str_starts_with($invoice->invoice_number, 'DRAFT-') ? $invoice->invoice_number : ($previewNumber ?? 'Assigned on finalize') }}
                            </div>
                        </div>

                        <div>
                            <x-input-label for="currency" value="Currency *" />
                            <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (['INR','USD','EUR','GBP','AED','SGD','AUD','CAD'] as $code)
                                    <option value="{{ $code }}" @selected(old('currency', $invoice->currency) === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="invoice_date" value="Invoice date *" />
                            <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full" :value="old('invoice_date', $invoice->invoice_date?->toDateString() ?? now()->toDateString())" required />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $invoice->due_date?->toDateString())" />
                        </div>

                        <div>
                            <x-input-label for="exchange_rate" value="Exchange rate (to INR)" />
                            <x-text-input id="exchange_rate" name="exchange_rate" type="number" step="0.000001" min="0" class="mt-1 block w-full" :value="old('exchange_rate', $invoice->exchange_rate ?? 1)" />
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

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="font-medium text-gray-900">Line items</h3>
                        <button type="button" @click="addRow" class="text-brand-600 text-sm hover:underline">+ Add row</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">HSN/SAC</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-left">Unit</th>
                                    <th class="px-3 py-2 text-right">Rate</th>
                                    <th class="px-3 py-2 text-right">GST%</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-right">Tax</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="idx">
                                    <tr class="border-t">
                                        <td class="px-2 py-2"><input :name="`items[${idx}][description]`" x-model="item.description" class="w-full border-gray-300 rounded text-sm" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" class="w-28 border-gray-300 rounded text-sm" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="0.001" min="0.001" class="w-20 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][unit]`" x-model="item.unit" class="w-20 border-gray-300 rounded text-sm" placeholder="pcs"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="0.01" min="0" class="w-28 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="w-20 border-gray-300 rounded text-sm">
                                                <option value="0">0</option>
                                                <option value="0.10">0.10</option>
                                                <option value="0.25">0.25</option>
                                                <option value="3">3</option>
                                                <option value="5">5</option>
                                                <option value="12">12</option>
                                                <option value="18">18</option>
                                                <option value="28">28</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-sm" x-text="fmt(item.amount)"></td>
                                        <td class="px-2 py-2 text-right font-mono text-sm text-gray-600" x-text="fmt(item.tax)"></td>
                                        <td class="px-2 py-2 text-right font-mono text-sm font-medium" x-text="fmt(item.total)"></td>
                                        <td class="px-2 py-2 text-right"><button type="button" @click="removeRow(idx)" class="text-red-500 hover:text-red-700" x-show="items.length > 1">×</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-t bg-gray-50">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $invoice->notes) }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="terms" value="Terms & conditions" />
                                <textarea id="terms" name="terms" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('terms', $invoice->terms ?? $company->default_terms) }}</textarea>
                            </div>
                        </div>

                        <div class="md:pl-6 space-y-2 text-sm">
                            <div class="flex justify-between"><span>Subtotal</span><span class="font-mono" x-text="fmt(totals.subtotal)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>SGST</span><span class="font-mono" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2"><span>Total tax</span><span class="font-mono" x-text="fmt(totals.totalTax)"></span></div>
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
                    <x-primary-button>{{ $invoice->exists ? 'Save draft' : 'Create draft' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function invoiceForm(initialItems, customerStates, companyStateId) {
            return {
                items: initialItems.map(i => ({...i, amount: 0, tax: 0, total: 0})),
                customerId: @json(old('customer_id', $invoice->customer_id)),
                customerStates,
                companyStateId,
                totals: {subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                balance: 0,
                get isInterstate() {
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                init() { this.recompute(); },
                addRow() {
                    this.items.push({description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, gst_rate: 18, amount: 0, tax: 0, total: 0});
                },
                removeRow(i) {
                    if (this.items.length > 1) this.items.splice(i, 1);
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
                            if (inter) ig = +(amount * gst / 100).toFixed(2);
                            else { c = +(amount * (gst / 2) / 100).toFixed(2); s = +(amount * (gst / 2) / 100).toFixed(2); }
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
