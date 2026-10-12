<x-app-layout :title="$quotation->exists ? 'Edit quotation' : 'New quotation'">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Quotations', 'href' => route('quotations.index')],
            ['label' => $quotation->exists ? $quotation->displayNumber() : 'New quotation'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
            {{ $quotation->exists ? 'Edit ' . $quotation->displayNumber() : 'New quotation' }}
        </h1>
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
        // Quote-level discount box: re-shown form keeps the typed value; an
        // existing quote opens with the sum of its saved line discounts.
        $initialDiscount = (float) old('quote_discount', collect($oldItems)->sum(fn ($i) => (float) ($i['discount'] ?? 0)));
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        $customerHasGstinMap = $customers->mapWithKeys(fn ($c) => [$c->id => ! empty($c->gstin)])->toJson();
        $companyStateId = $company->state_id;
        // Union Territories without a legislature levy UTGST instead of SGST.
        $utStateIds = \App\Models\State::where('is_union_territory', true)->pluck('id')->values();
    @endphp

    <div class="py-6 sm:py-8" x-data='quotationForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }}, @json($productIndex), {{ $customerHasGstinMap }}, {{ ! empty($company->gstin) ? 'true' : 'false' }})'
         @customer-added.window="addCustomer($event.detail)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-4 bg-danger-50 border border-danger-200 text-danger-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @unless ($quotation->exists)
                <div class="p-4 bg-brand-50 border border-brand-200 text-brand-900 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm">
                        <div class="font-semibold">A quotation is not a tax invoice.</div>
                        <div class="mt-0.5">It is not reported in GSTR-1 or GSTR-3B. GST is charged on the invoice you create with <strong>Convert to invoice</strong> after the customer accepts.</div>
                    </div>
                </div>
            @endunless

            <form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}" class="space-y-6">
                @csrf
                @if ($quotation->exists) @method('PATCH') @endif

                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 sm:p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="customer_id" value="Customer *" />
                            <div class="flex items-center gap-2 mt-1">
                                <select id="customer_id" name="customer_id" x-model="customerId" @change="recompute()" class="block w-full border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">Select customer</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id) == $c->id)>
                                            {{ $c->name }}{{ $c->state?->name ? ' · ' . $c->state->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" @click="$dispatch('open-quick-customer')" class="inline-flex items-center justify-center min-h-[40px] px-3 text-brand-700 hover:text-white hover:bg-brand-600 ring-1 ring-brand-200 rounded-md text-sm font-semibold whitespace-nowrap" title="Add a new customer">+ New</button>
                            </div>
                            <x-input-error :messages="$errors->get('customer_id')" for="customer_id" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="quote_number" value="Quote no." />
                            <x-text-input id="quote_number" name="quote_number" type="text" maxlength="50"
                                          class="mt-1 block w-full font-mono text-sm"
                                          :value="old('quote_number', $quotation->quote_number ?: $previewNumber)" />
                            <p class="mt-1 text-xs text-gray-500">Change it if you use your own series.</p>
                            <x-input-error :messages="$errors->get('quote_number')" for="quote_number" class="mt-1" />
                        </div>

                        <input type="hidden" name="currency" value="INR">
                        <input type="hidden" name="style" value="{{ old('style', $quotation->style ?? 'classic') }}">

                        <div>
                            <x-input-label for="quote_date" value="Quote date *" />
                            <x-text-input id="quote_date" name="quote_date" type="date" class="mt-1 block w-full"
                                          :value="old('quote_date', $quotation->quote_date?->toDateString() ?? now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('quote_date')" for="quote_date" class="mt-1" />
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
                            <x-input-error :messages="$errors->get('valid_until')" for="valid_until" class="mt-1" />
                            <div class="mt-1 flex flex-wrap gap-1">
                                {{-- $chip, not $label: partials.hsn-search-link reads $label,
                                     and the last chip ("60d") leaked into the HSN/SAC header. --}}
                                @foreach ([7 => '7 days', 15 => '15 days', 30 => '30 days', 60 => '60 days'] as $days => $chip)
                                    <button type="button" @click="setValidity({{ $days }})"
                                            class="text-xs min-h-[32px] px-2 rounded bg-gray-100 hover:bg-brand-100 hover:text-brand-700 text-gray-700">{{ $chip }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Tax mode (auto)" />
                            <div class="mt-2 text-sm">
                                <span x-show="!customerId" class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded">Select a customer…</span>
                                <span x-show="customerId && isInterstate" class="inline-block px-2 py-0.5 bg-accent-100 text-accent-800 rounded">Inter-state (IGST)</span>
                                <span x-show="customerId && !isInterstate" class="inline-block px-2 py-0.5 bg-money-100 text-money-800 rounded">Intra-state (CGST + <span x-text="stateTaxLabel"></span>)</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Based on your state and the customer's state.</p>
                        </div>
                    </div>
                </div>

                {{-- Quote details - subject, reference and delivery period are
                     standard on Indian B2B quotations. All optional, but having
                     them surfaced means the operator can fill them in seconds
                     instead of cramming them into the free-text notes field. --}}
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 sm:p-6 space-y-4">
                    <div>
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full"
                                      :value="old('subject', $quotation->subject)"
                                      placeholder="e.g. Quotation for supply of office furniture" maxlength="200" />
                        <p class="mt-1 text-xs text-gray-500">One line, printed at the top of the quotation.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="reference" value="Customer reference (optional)" />
                            <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full"
                                          :value="old('reference', $quotation->reference)"
                                          placeholder="e.g. Your enquiry ABC/RFQ/26-27/021 dated 12-Apr-2026" maxlength="100" />
                            <p class="mt-1 text-xs text-gray-500">The customer's enquiry or RFQ number.</p>
                        </div>
                        <div>
                            <x-input-label for="delivery_period" value="Delivery period (optional)" />
                            <x-text-input id="delivery_period" name="delivery_period" type="text" class="mt-1 block w-full"
                                          :value="old('delivery_period', $quotation->delivery_period)"
                                          placeholder="e.g. 15-20 working days from order confirmation" maxlength="100" />
                            <p class="mt-1 text-xs text-gray-500">Printed on the quotation.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                    <div class="px-4 sm:px-6 py-4 border-b">
                        <h2 class="font-semibold text-gray-900">Items</h2>
                    </div>

                    {{-- Mobile / tablet stacked cards --}}
                    <div class="lg:hidden divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="idx">
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-gray-600">Item <span x-text="idx + 1"></span></span>
                                    <button type="button" @click="removeRow(idx)"
                                            :disabled="items.length <= 1"
                                            :class="items.length <= 1 ? 'text-gray-600 cursor-not-allowed' : 'text-danger-700'"
                                            class="inline-flex items-center min-h-[40px] px-2 -mr-2 text-sm font-medium transition"
                                            aria-label="Remove row">Remove</button>
                                </div>
                                @if ($productIndex->isNotEmpty())
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Item</label>
                                        <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                        <select @change="pickProduct(idx, $event.target.value)" :aria-label="`Pick an item for line item ${idx + 1}`" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                            <option value="">Custom</option>
                                            @foreach ($productIndex as $p)
                                                <option value="{{ $p->id }}" :selected="item.product_id == {{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Description</label>
                                    <input :name="`items[${idx}][description]`" :aria-label="`Item description for line item ${idx + 1}`" x-model="item.description" maxlength="150" placeholder="Optional · filled from the product" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <div class="flex items-center justify-between gap-1">
                                            <label class="text-sm font-medium text-gray-700">HSN/SAC</label>
                                            @include('partials.hsn-search-link')
                                        </div>
                                        <input :name="`items[${idx}][hsn_sac]`" :aria-label="`HSN or SAC code for line item ${idx + 1}`" x-model="item.hsn_sac" maxlength="8" placeholder="998314" class="mt-1 block w-full border-gray-300 rounded text-sm font-mono">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Unit</label>
                                        <input :name="`items[${idx}][unit]`" :aria-label="`Unit for line item ${idx + 1}`" x-model="item.unit" class="mt-1 block w-full border-gray-300 rounded text-sm" placeholder="NOS, HRS…">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Quantity</label>
                                        <input :name="`items[${idx}][quantity]`" :aria-label="`Quantity for line item ${idx + 1}`" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Rate (₹)</label>
                                        <input :name="`items[${idx}][rate]`" :aria-label="`Rate for line item ${idx + 1}`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">GST rate</label>
                                        <select :name="`items[${idx}][gst_rate]`" :aria-label="`GST rate for line item ${idx + 1}`" x-model.number="item.gst_rate" @change="recompute()" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                            @foreach (config('gst.rates') as $r)
                                                <option value="{{ $r['value'] }}" title="{{ $r['label'] . ' · ' . $r['note'] }}">{{ (float) $r['value'] }}%</option>
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
                            <thead class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase">
                                <tr>
                                    @if ($productIndex->isNotEmpty())
                                        <th class="px-3 py-2 text-left">Item</th>
                                    @endif
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span>HSN/SAC</span>
                                            @include('partials.hsn-search-link')
                                        </span>
                                    </th>
                                    <th class="px-3 py-2 text-right">Quantity</th>
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
                                                <select @change="pickProduct(idx, $event.target.value)" :aria-label="`Pick an item for line item ${idx + 1}`" class="w-40 border-gray-300 rounded text-sm">
                                                    <option value="">Custom</option>
                                                    @foreach ($productIndex as $p)
                                                        <option value="{{ $p->id }}" :selected="item.product_id == {{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td class="px-2 py-2"><input :name="`items[${idx}][description]`" :aria-label="`Item description for line item ${idx + 1}`" x-model="item.description" maxlength="150" placeholder="Optional · filled from the product" class="w-full border-gray-300 rounded text-sm"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" :aria-label="`HSN or SAC code for line item ${idx + 1}`" x-model="item.hsn_sac" maxlength="8" placeholder="998314" class="w-28 border-gray-300 rounded text-sm font-mono"></td>
                                        <td class="px-2 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <input :name="`items[${idx}][quantity]`" :aria-label="`Quantity for line item ${idx + 1}`" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="w-20 border-gray-300 rounded text-sm text-right" required>
                                                <input :name="`items[${idx}][unit]`" :aria-label="`Unit for line item ${idx + 1}`" x-model="item.unit" class="w-20 border-gray-300 rounded text-sm" placeholder="Unit">
                                            </div>
                                        </td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" :aria-label="`Rate for line item ${idx + 1}`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="w-28 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][gst_rate]`" :aria-label="`GST rate for line item ${idx + 1}`" x-model.number="item.gst_rate" @change="recompute()" class="w-20 border-gray-300 rounded text-sm">
                                                @foreach (config('gst.rates') as $r)
                                                    <option value="{{ $r['value'] }}" title="{{ $r['label'] . ' · ' . $r['note'] }}">{{ (float) $r['value'] }}%</option>
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
                                                        ? 'text-gray-500 cursor-not-allowed'
                                                        : 'text-danger-500 hover:text-white hover:bg-danger-500'"
                                                    aria-label="Remove row">×</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 sm:px-6 py-3 border-t">
                        <button type="button" @click="addRow" class="inline-flex items-center justify-center min-h-[40px] px-3 bg-brand-50 hover:bg-brand-100 text-brand-800 rounded-md text-sm font-semibold">+ Add row</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 sm:p-6 border-t bg-gray-50">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="terms" value="Terms & conditions" />
                                <textarea id="terms" name="terms" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono text-xs"
                                          placeholder="1. Payment: 50% advance on order, balance against proforma before dispatch.&#10;2. Taxes: GST extra at applicable rates.&#10;3. Freight: At actuals, on customer's account.&#10;4. Warranty: 12 months from date of dispatch.&#10;5. Disputes: Subject to {{ $company->city ?? 'local' }} jurisdiction.">{{ old('terms', $quotation->terms ?? $company->default_terms) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">Put each numbered term on its own line.</p>
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes (printed below the terms)" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $quotation->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="md:pl-6 space-y-2 text-sm">
                            <div class="flex justify-between"><span>Items total</span><span class="font-mono" x-text="fmt(totals.gross)"></span></div>
                            {{-- One discount for the whole quote, applied before GST and
                                 spread across the lines (same as the invoice form). --}}
                            <div class="flex justify-between items-center gap-2">
                                <label for="quote_discount_input" class="flex items-center gap-1.5">
                                    Discount
                                    <span class="inline-flex rounded border border-gray-300 overflow-hidden text-xs font-semibold">
                                        <button type="button" @click="discountMode = 'amount'; recompute()" :class="discountMode === 'amount' ? 'bg-brand-700 text-white' : 'bg-white text-gray-600'" :aria-pressed="(discountMode === 'amount').toString()" aria-label="Discount in rupees" class="px-2.5 py-1.5">₹</button>
                                        <button type="button" @click="discountMode = 'percent'; recompute()" :class="discountMode === 'percent' ? 'bg-brand-700 text-white' : 'bg-white text-gray-600'" :aria-pressed="(discountMode === 'percent').toString()" aria-label="Discount in percent" class="px-2.5 py-1.5">%</button>
                                    </span>
                                </label>
                                <input id="quote_discount_input" type="number" step="any" min="0" inputmode="decimal"
                                       x-model.number="discountInput" @input="recompute()"
                                       class="w-32 border-gray-300 rounded text-sm text-right" placeholder="0">
                                <input type="hidden" name="quote_discount" :value="totals.discount">
                            </div>
                            <p x-show="discountMode === 'percent' && totals.discount > 0" x-cloak class="text-xs text-gray-500 text-right -mt-1">= ₹ <span x-text="fmt(totals.discount)"></span></p>
                            @error('quote_discount')<p class="text-xs text-danger-600 text-right">{{ $message }}</p>@enderror
                            <div class="flex justify-between border-t pt-2"><span>Taxable value</span><span class="font-mono" x-text="fmt(totals.subtotal)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span x-text="stateTaxLabel"></span><span class="font-mono" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono" x-text="fmt(totals.grandTotal)"></span></div>
                            <p class="pt-2 text-xs text-gray-500">GST shown is what the invoice will charge. A quotation itself is not reported in GST returns.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('quotations.index') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</a>
                    <x-primary-button>{{ $quotation->exists ? 'Save changes' : 'Save draft' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Inline "+ New customer" modal - adds without leaving the page so typed
         line items stay intact. --}}
    <x-quick-customer-modal :states="$states" />

    @push('scripts')
    <script>
        const UT_STATE_IDS = @json($utStateIds);
        function quotationForm(initialItems, customerStates, companyStateId, productIndex, customerHasGstin, companyHasGstin) {
            const productMap = {};
            (productIndex || []).forEach(p => { productMap[p.id] = p; });
            return {
                items: initialItems.map(i => ({product_id: null, ...i, amount: 0, tax: 0, total: 0})),
                customerId: @json(old('customer_id', $quotation->customer_id)),
                customerStates,
                customerHasGstin: customerHasGstin || {},
                companyStateId,
                companyHasGstin: !! companyHasGstin,
                productMap,
                totals: {gross: 0, discount: 0, subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                discountMode: 'amount',
                discountInput: @json($initialDiscount) || '',
                /**
                 * HSN/SAC is required only when either party is GST-registered
                 * (Rule 46(g)). For two unregistered parties, HSN is purely optional.
                 */
                get hsnRequired() {
                    if (this.companyHasGstin) return true;
                    if (this.customerId && this.customerHasGstin[this.customerId]) return true;
                    return false;
                },
                get isInterstate() {
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                // "SGST" or "UTGST" for the state share (mirrors Quotation::stateTaxLabel).
                get stateTaxLabel() {
                    const cs = this.customerId ? this.customerStates[this.customerId] : this.companyStateId;
                    return UT_STATE_IDS.includes(cs) ? 'UTGST' : 'SGST';
                },
                init() { this.recompute(); },
                /**
                 * Wired to @customer-added.window - pushes the new customer
                 * (saved by the inline modal) into the dropdown and selects it,
                 * so the quotation's typed line items aren't lost.
                 */
                addCustomer(c) {
                    if (!c || !c.id) return;
                    const select = document.getElementById('customer_id');
                    if (select) {
                        const option = document.createElement('option');
                        option.value = c.id;
                        option.textContent = c.name + (c.state_name ? ' · ' + c.state_name : '');
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

                    // Quote-level discount spread pro rata before tax
                    // (InvoiceCalculator::apportionDiscount on the server).
                    const grossOf = item => +((parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0)).toFixed(2);
                    const grossList = this.items.map(grossOf);
                    const totalGross = +grossList.reduce((a, b) => a + b, 0).toFixed(2);
                    const input = Math.max(0, parseFloat(this.discountInput) || 0);
                    let discount = this.discountMode === 'percent'
                        ? +(totalGross * Math.min(input, 100) / 100).toFixed(2)
                        : +input.toFixed(2);
                    discount = Math.min(discount, totalGross);
                    let lastIdx = -1;
                    grossList.forEach((g, i) => { if (g > 0) lastIdx = i; });
                    let allocated = 0;
                    this.items.forEach((item, i) => {
                        if (discount <= 0 || grossList[i] <= 0) { item.discount = 0; return; }
                        let share = i === lastIdx
                            ? +(discount - allocated).toFixed(2)
                            : +(discount * grossList[i] / totalGross).toFixed(2);
                        share = Math.min(share, grossList[i]);
                        item.discount = share;
                        allocated = +(allocated + share).toFixed(2);
                    });

                    this.items.forEach(item => {
                        const gst = parseFloat(item.gst_rate) || 0;
                        const gross = grossOf(item);
                        const disc = Math.max(0, Math.min(parseFloat(item.discount) || 0, gross));
                        const amount = +(gross - disc).toFixed(2);
                        let c = 0, s = 0, ig = 0;
                        if (gst > 0) {
                            if (inter) {
                                ig = +(amount * gst / 100).toFixed(2);
                            } else {
                                // Each head at half the rate, rounded on its own
                                // (mirrors InvoiceCalculator; keeps CGST = SGST).
                                c = +(amount * gst / 200).toFixed(2);
                                s = c;
                            }
                        }
                        item.amount = amount;
                        item.tax = +(c + s + ig).toFixed(2);
                        item.total = +(amount + item.tax).toFixed(2);
                        sub += amount; cgst += c; sgst += s; igst += ig;
                    });
                    const totalTax = +(cgst + sgst + igst).toFixed(2);
                    this.totals = {
                        gross: totalGross,
                        discount: +allocated.toFixed(2),
                        subtotal: +sub.toFixed(2),
                        cgst: +cgst.toFixed(2),
                        sgst: +sgst.toFixed(2),
                        igst: +igst.toFixed(2),
                        totalTax,
                        grandTotal: Math.round(sub + totalTax),
                    };
                },
                fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
    @endpush
</x-app-layout>
