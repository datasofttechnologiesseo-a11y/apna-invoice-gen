<x-app-layout :title="$isEdit ? 'Edit purchase invoice' : 'New purchase invoice'">
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
            // Category follows the lines on a new bill until it is picked by hand.
            'category' => (string) old('expense_category', $bill->expense_category ?? 'misc'),
            'autoCategory' => ! $isEdit && array_key_exists('purchases', $categories),
            // Errors on a line ("items.2.rate") are shown under that line's field.
            'lineErrors' => collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'items.'))->map(fn ($m) => $m[0]),
        ];
        $commonRates = [0, 5, 18, 40];
        $rareRates = collect(config('gst.rates'))->whereNotIn('value', $commonRates)->values();
        $hasItems = $products->isNotEmpty();
        // One set of inputs per line: a stacked card on phones, two rows on
        // tablets and small laptops, a single table row from 1280px.
        $lineGrid = $hasItems
            ? 'xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_5.5rem_4.5rem_4.5rem_6.5rem_5.5rem_7rem_2.5rem]'
            : 'xl:grid-cols-[minmax(0,2fr)_5.5rem_4.5rem_4.5rem_6.5rem_5.5rem_7rem_2.5rem]';
        $label = 'block text-xs font-medium text-gray-600 mb-1 xl:sr-only';
        $field = 'block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600';
        $crumbs = $isEdit
            ? [['label' => 'Purchase invoices', 'href' => route('purchases.index')], ['label' => $bill->bill_number, 'href' => route('purchases.show', $bill)], ['label' => 'Edit']]
            : [['label' => 'Purchase invoices', 'href' => route('purchases.index')], ['label' => 'New purchase invoice']];
    @endphp

    <x-slot name="header">
        <x-breadcrumbs :items="$crumbs" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $isEdit ? 'Edit purchase invoice ' . $bill->bill_number : 'New purchase invoice' }}</h1>
    </x-slot>

    <div class="py-6 sm:py-8"
         x-data='purchaseBillForm(@json($oldItems), @json($vendorStates), {{ $company->state_id ?? 'null' }}, @json($utStateIds), @json($initial), @json(route('vendors.store')), @json(csrf_token()), @json($products))'>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $isEdit ? route('purchases.update', $bill) : route('purchases.store') }}" class="space-y-6">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                @if ($errors->any())
                    <div class="p-4 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">
                        <span class="font-semibold">Check the fields marked in red.</span>
                        @if ($errors->has('items'))<span class="block mt-1">{{ $errors->first('items') }}</span>@endif
                    </div>
                @endif
                @if (session('error'))
                    <div class="p-3 bg-danger-50 border border-danger-200 text-danger-800 rounded-lg text-sm" role="alert">{{ session('error') }}</div>
                @endif

                {{-- 1. Vendor + bill identity --}}
                <div class="bg-white p-4 sm:p-6 rounded-xl ring-1 ring-gray-200 shadow-sm">
                    <div class="grid grid-cols-2 sm:grid-cols-6 xl:grid-cols-12 gap-4 sm:gap-5">
                        <div class="col-span-2 sm:col-span-6 xl:col-span-5">
                            <div class="flex items-center justify-between gap-2">
                                <x-input-label for="vendor_id" value="Vendor *" />
                                <button type="button" @click="quickAdd.open = !quickAdd.open" class="inline-flex items-center min-h-[32px] text-xs font-semibold text-brand-700 hover:underline">+ New vendor</button>
                            </div>
                            <select id="vendor_id" name="vendor_id" x-model="vendorId" required
                                    @error('vendor_id') aria-invalid="true" aria-describedby="vendor_id-error" @enderror
                                    class="mt-1 {{ $field }}">
                                <option value="">Select vendor</option>
                                <template x-for="v in vendorList" :key="v.id">
                                    <option :value="String(v.id)" x-text="v.label" :selected="String(v.id) === vendorId"></option>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('vendor_id')" for="vendor_id" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-500" x-show="vendorId">
                                Tax: <strong class="font-semibold text-gray-700" x-text="taxModeLabel"></strong>
                                <span x-show="!interstateOverridden">(from the vendor's state)</span>
                            </p>

                            {{-- Quick-add: name, GSTIN, state - enough for a bill. Address can follow in Vendors. --}}
                            <div x-show="quickAdd.open" x-cloak class="mt-3 p-3 rounded-lg bg-brand-50 border border-brand-200 space-y-2">
                                <div class="text-sm font-semibold text-brand-900">New vendor</div>
                                <input type="text" x-model="quickAdd.name" placeholder="Vendor name *" aria-label="New vendor name" aria-required="true" maxlength="160" autocomplete="off" class="{{ $field }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <input type="text" x-model="quickAdd.gstin" placeholder="GSTIN (optional)" aria-label="New vendor GSTIN (optional)" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" class="{{ $field }} font-mono uppercase">
                                    <select x-model="quickAdd.state_id" aria-label="New vendor state" class="{{ $field }}">
                                        <option value="">State</option>
                                        @foreach ($states as $st)
                                            <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->gst_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p x-show="quickAdd.error" x-text="quickAdd.error" role="alert" class="text-xs text-danger-700"></p>
                                <div class="flex gap-2">
                                    <button type="button" @click="saveVendor()" :disabled="quickAdd.saving" class="inline-flex items-center h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md disabled:opacity-50">Save vendor</button>
                                    <button type="button" @click="quickAdd.open = false" class="inline-flex items-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Cancel</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-2 sm:col-span-2 xl:col-span-3">
                            <x-input-label for="bill_number" value="Vendor's invoice no. *" />
                            <x-text-input id="bill_number" name="bill_number" type="text" class="mt-1 block w-full font-mono" :value="old('bill_number', $bill->bill_number)" maxlength="40" required placeholder="As printed on their invoice" />
                            <x-input-error :messages="$errors->get('bill_number')" for="bill_number" class="mt-1" />
                        </div>
                        <div class="col-span-1 sm:col-span-2 xl:col-span-2">
                            <x-input-label for="bill_date" value="Invoice date *" />
                            <x-text-input id="bill_date" name="bill_date" type="date" class="mt-1 block w-full min-w-0" :value="old('bill_date', optional($bill->bill_date)->toDateString() ?? now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('bill_date')" for="bill_date" class="mt-1" />
                        </div>
                        <div class="col-span-1 sm:col-span-2 xl:col-span-2">
                            <x-input-label for="due_date" value="Due date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full min-w-0" :value="old('due_date', optional($bill->due_date)->toDateString())" />
                            <x-input-error :messages="$errors->get('due_date')" for="due_date" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- 2. Lines --}}
                <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm">
                    <div class="px-4 sm:px-6 pt-5 pb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-900">Items on the invoice *</h2>
                            @if ($hasItems)
                                <p class="mt-0.5 text-xs text-gray-500">Pick a saved item on each line so its stock and purchase price update.</p>
                            @endif
                        </div>
                        <button type="button" @click="addRow()" class="inline-flex items-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">+ Add line</button>
                    </div>

                    {{-- Column headings, wide screens only (below that every field has its own label). --}}
                    <div class="hidden xl:grid {{ $lineGrid }} gap-x-3 px-6 py-2 bg-gray-50 border-y border-gray-100 text-xs font-semibold text-gray-600" aria-hidden="true">
                        @if ($hasItems)<div>Item</div>@endif
                        <div>Description *</div>
                        <div>HSN/SAC</div>
                        <div class="text-right">Qty *</div>
                        <div>Unit</div>
                        <div class="text-right">Rate (₹) *</div>
                        <div>GST</div>
                        <div class="text-right">Amount</div>
                        <div></div>
                    </div>

                    <div class="divide-y divide-gray-100 border-t border-gray-100 xl:border-t-0">
                        <template x-for="(item, idx) in items" :key="item._uid">
                            <div class="px-4 sm:px-6 py-4 xl:py-3">
                                <div class="flex items-center justify-between mb-2 xl:hidden">
                                    <span class="text-sm font-semibold text-gray-700">Line <span x-text="idx + 1"></span></span>
                                    <button type="button" @click="removeRow(idx)" x-show="items.length > 1" class="inline-flex items-center min-h-[40px] px-2 -mr-2 text-sm font-medium text-danger-600 hover:text-danger-800">Remove</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-12 {{ $lineGrid }} gap-3 items-start">
                                    @if ($hasItems)
                                        {{-- Item first: type to search the saved items. Picking one fills
                                             the line; for an item that tracks stock the purchase brings it in. --}}
                                        <div class="col-span-2 sm:col-span-6 xl:col-span-1 relative" @click.outside="item._open = false">
                                            <label :for="`line-${item._uid}-item`" class="{{ $label }}">Item</label>
                                            <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                                            <div class="relative">
                                                <input type="text" :id="`line-${item._uid}-item`" x-model="item._q" autocomplete="off"
                                                       role="combobox" aria-autocomplete="list" :aria-expanded="item._open ? 'true' : 'false'" :aria-controls="`line-${item._uid}-list`"
                                                       :aria-label="`Item for line ${idx + 1}`"
                                                       @focus="openItems(item)" @click="openItems(item)" @input="searchItems(item)"
                                                       @keydown.arrow-down.prevent="moveHighlight(item, 1)" @keydown.arrow-up.prevent="moveHighlight(item, -1)"
                                                       @keydown.enter.prevent="pickHighlighted(item)" @keydown.escape="item._open = false"
                                                       @blur="setTimeout(() => leaveItem(item), 150)"
                                                       placeholder="Search name, SKU or HSN"
                                                       class="{{ $field }} pr-9" :class="item.product_id ? 'bg-brand-50/60' : ''">
                                                <button type="button" x-show="item.product_id || item._q" x-cloak @click="clearItem(item)"
                                                        class="absolute inset-y-0 right-0 w-9 inline-flex items-center justify-center text-gray-500 hover:text-gray-800" :aria-label="`Clear the item on line ${idx + 1}`">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                                <ul x-show="item._open" x-cloak :id="`line-${item._uid}-list`" role="listbox"
                                                    class="absolute z-30 mt-1 w-full min-w-[16rem] max-h-64 overflow-y-auto bg-white rounded-md shadow-lg ring-1 ring-gray-200 py-1">
                                                    <template x-for="(p, i) in matches(item)" :key="p.id">
                                                        <li role="option" :aria-selected="i === item._hi ? 'true' : 'false'">
                                                            <button type="button" @mousedown.prevent="pick(item, p)" @mouseenter="item._hi = i"
                                                                    class="block w-full text-left px-3 py-2 min-h-[40px]" :class="i === item._hi ? 'bg-brand-50' : 'hover:bg-gray-50'">
                                                                <span class="block text-sm text-gray-900" x-text="p.name"></span>
                                                                <span class="block text-xs text-gray-500" x-text="itemMeta(p)"></span>
                                                            </button>
                                                        </li>
                                                    </template>
                                                    <li x-show="matches(item).length === 0" class="px-3 py-2 text-sm text-gray-500">No saved item matches. Type the description instead.</li>
                                                </ul>
                                            </div>
                                            <p x-show="!item.product_id && hasTracked" class="mt-1 text-xs text-accent-800">Pick the item to update stock</p>
                                            <p x-show="isTracked(item)" x-cloak class="mt-1 text-xs font-medium text-brand-700">Adds to stock</p>
                                        </div>
                                    @endif

                                    <div class="col-span-2 {{ $hasItems ? 'sm:col-span-6' : 'sm:col-span-12' }} xl:col-span-1">
                                        <label :for="`line-${item._uid}-desc`" class="{{ $label }}">Description *</label>
                                        <input :id="`line-${item._uid}-desc`" :name="`items[${idx}][description]`" x-model="item.description" maxlength="255" required
                                               @input="clearLineError(idx, 'description')" :aria-invalid="lineError(idx, 'description') ? 'true' : null"
                                               class="{{ $field }}" :class="lineError(idx, 'description') && 'border-danger-500'">
                                        <p x-show="lineError(idx, 'description')" x-text="lineError(idx, 'description')" class="mt-1 text-xs text-danger-600"></p>
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1">
                                        <label :for="`line-${item._uid}-hsn`" class="{{ $label }}">HSN/SAC</label>
                                        <input :id="`line-${item._uid}-hsn`" :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" maxlength="10" inputmode="numeric"
                                               @input="clearLineError(idx, 'hsn_sac')" class="{{ $field }} font-mono">
                                        <p x-show="lineError(idx, 'hsn_sac')" x-text="lineError(idx, 'hsn_sac')" class="mt-1 text-xs text-danger-600"></p>
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1">
                                        <label :for="`line-${item._uid}-qty`" class="{{ $label }}">Qty *</label>
                                        <input :id="`line-${item._uid}-qty`" :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute(); clearLineError(idx, 'quantity')"
                                               type="number" step="any" min="0.001" inputmode="decimal" required class="{{ $field }} text-right tabular-nums">
                                        <p x-show="lineError(idx, 'quantity')" x-text="lineError(idx, 'quantity')" class="mt-1 text-xs text-danger-600"></p>
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1">
                                        <label :for="`line-${item._uid}-unit`" class="{{ $label }}">Unit</label>
                                        <input :id="`line-${item._uid}-unit`" :name="`items[${idx}][unit]`" x-model="item.unit" maxlength="20" placeholder="NOS" class="{{ $field }}">
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1">
                                        <label :for="`line-${item._uid}-rate`" class="{{ $label }}">Rate (₹) *</label>
                                        <input :id="`line-${item._uid}-rate`" :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute(); clearLineError(idx, 'rate')"
                                               type="number" step="any" min="0" inputmode="decimal" required class="{{ $field }} text-right tabular-nums">
                                        <p x-show="lineError(idx, 'rate')" x-text="lineError(idx, 'rate')" class="mt-1 text-xs text-danger-600"></p>
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1">
                                        <label :for="`line-${item._uid}-gst`" class="{{ $label }}">GST</label>
                                        <select :id="`line-${item._uid}-gst`" :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="{{ $field }} pr-7">
                                            @foreach ($commonRates as $r)<option value="{{ $r }}">{{ $r }}%</option>@endforeach
                                            @foreach ($rareRates as $r)<option value="{{ $r['value'] }}">{{ rtrim(rtrim(number_format($r['value'], 2), '0'), '.') }}%{{ in_array((float) $r['value'], [12.0, 28.0], true) ? ' (legacy)' : '' }}</option>@endforeach
                                        </select>
                                        <p x-show="lineError(idx, 'gst_rate')" x-text="lineError(idx, 'gst_rate')" class="mt-1 text-xs text-danger-600"></p>
                                    </div>

                                    <div class="col-span-1 sm:col-span-2 xl:col-span-1 text-right tabular-nums">
                                        <span class="block text-xs font-medium text-gray-600 mb-1 xl:sr-only">Amount</span>
                                        <div class="min-h-[40px] xl:min-h-[38px] flex flex-col justify-center">
                                            <span class="font-mono font-semibold text-gray-900" x-text="'₹' + fmt(item.total)"></span>
                                            <span class="text-xs text-gray-500" x-show="item.tax > 0" x-text="'Taxable ₹' + fmt(item.amount)"></span>
                                        </div>
                                    </div>

                                    <div class="hidden xl:flex justify-end">
                                        <button type="button" @click="removeRow(idx)" x-show="items.length > 1"
                                                class="w-9 h-9 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-danger-700 hover:bg-danger-50" :aria-label="`Remove line ${idx + 1}`" title="Remove line">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="px-4 sm:px-6 py-3 border-t border-gray-100">
                        <button type="button" @click="addRow()" class="inline-flex items-center min-h-[40px] text-sm font-semibold text-brand-700 hover:underline">+ Add line</button>
                    </div>
                </div>

                {{-- 3. Tax treatment + totals --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-4 sm:p-6 rounded-xl ring-1 ring-gray-200 shadow-sm space-y-4">
                        <h2 class="font-semibold text-gray-900">Tax treatment</h2>

                        <div>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="hidden" name="is_interstate" :value="isInterstate ? 1 : 0" x-bind:disabled="!interstateOverridden">
                                <input type="checkbox" x-model="interstateOverridden" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                <span><span class="font-medium text-gray-800">Set tax type manually</span>
                                    <span class="block text-xs text-gray-500">Normally set by the vendor's state.</span></span>
                            </label>
                            <div x-show="interstateOverridden" x-cloak class="mt-2 ml-6 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <label class="inline-flex items-center gap-1.5 min-h-[32px]"><input type="radio" :checked="!manualInterstate" @change="manualInterstate = false; recompute()" class="border-gray-300 text-brand-700"> CGST + <span x-text="stateTaxLabel"></span></label>
                                <label class="inline-flex items-center gap-1.5 min-h-[32px]"><input type="radio" :checked="manualInterstate" @change="manualInterstate = true; recompute()" class="border-gray-300 text-brand-700"> IGST</label>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="reverse_charge" value="1" x-model="reverseCharge" @change="recompute()" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                <span><span class="font-medium text-gray-800">Reverse charge (GST payable by you)</span>
                                    <span class="block text-xs text-gray-500">The vendor charges no GST. You pay it in GSTR-3B.</span></span>
                            </label>
                            <details class="ml-6 mt-1 text-xs text-gray-600">
                                <summary class="inline-flex items-center min-h-[28px] cursor-pointer font-semibold text-brand-700">Why?</summary>
                                <p class="mt-1">Section 9(3) and 9(4) services, such as GTA, legal and security services, import of services, or commercial rent from an unregistered owner. Report the tax in GSTR-3B 3.1(d) and claim ITC if eligible.</p>
                            </details>
                        </div>

                        <div>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="itc_eligible" value="1" @checked(old('itc_eligible', $bill->exists ? $bill->itc_eligible : true)) class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                <span><span class="font-medium text-gray-800">Input tax credit claimable</span>
                                    <span class="block text-xs text-gray-500">Untick for blocked credit. The GST is then added to cost.</span></span>
                            </label>
                            <details class="ml-6 mt-1 text-xs text-gray-600">
                                <summary class="inline-flex items-center min-h-[28px] cursor-pointer font-semibold text-brand-700">Why?</summary>
                                <p class="mt-1">Section 17(5) blocks credit on motor vehicles, staff food and catering, and goods for personal use.</p>
                            </details>
                        </div>

                        <div>
                            <x-input-label for="expense_category" value="Expense category (for the P&L)" />
                            <select id="expense_category" name="expense_category" x-model="category" @change="categoryTouched = true"
                                    @error('expense_category') aria-invalid="true" aria-describedby="expense_category-error" @enderror
                                    class="mt-1 {{ $field }}">
                                @foreach ($categories as $key => $cat)
                                    <option value="{{ $key }}" @selected($initial['category'] === $key)>{{ $cat['label'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('expense_category')" for="expense_category" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 {{ $field }}">{{ old('notes', $bill->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" for="notes" class="mt-1" />
                        </div>
                    </div>

                    <div class="bg-white p-4 sm:p-6 rounded-xl ring-1 ring-gray-200 shadow-sm">
                        <h2 class="font-semibold text-gray-900 mb-3">Totals</h2>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between"><dt class="text-gray-600">Items total</dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.gross)"></dd></div>
                            {{-- One discount for the whole bill, applied before GST and spread
                                 across the lines (same as sales invoices). --}}
                            <div class="flex justify-between items-center gap-2">
                                <dt class="text-gray-600 flex items-center gap-1.5">Discount
                                    <span class="inline-flex rounded-md ring-1 ring-gray-300 overflow-hidden text-xs font-semibold" role="group" aria-label="Discount in rupees or percent">
                                        <button type="button" @click="discountMode = 'amount'; recompute()" :class="discountMode === 'amount' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700'" class="min-w-[32px] min-h-[32px] px-2" :aria-pressed="discountMode === 'amount' ? 'true' : 'false'">₹</button>
                                        <button type="button" @click="discountMode = 'percent'; recompute()" :class="discountMode === 'percent' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700'" class="min-w-[32px] min-h-[32px] px-2" :aria-pressed="discountMode === 'percent' ? 'true' : 'false'">%</button>
                                    </span>
                                </dt>
                                <dd><input type="number" step="any" min="0" inputmode="decimal" x-model.number="discountInput" @input="recompute()" aria-label="Discount"
                                           @error('bill_discount') aria-invalid="true" aria-describedby="bill_discount-error" @enderror
                                           class="w-28 border-gray-300 rounded-md text-sm text-right tabular-nums" placeholder="0">
                                    <input type="hidden" name="bill_discount" :value="totals.discount"></dd>
                            </div>
                            <x-input-error :messages="$errors->get('bill_discount')" for="bill_discount" class="text-right" />
                            <div class="flex justify-between border-t border-gray-100 pt-1.5"><dt class="text-gray-600">Taxable value</dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.subtotal)"></dd></div>
                            <template x-if="!isInterstate">
                                <div class="space-y-1.5">
                                    <div class="flex justify-between"><dt class="text-gray-600">CGST</dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.cgst)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-600" x-text="stateTaxLabel"></dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.sgst)"></dd></div>
                                </div>
                            </template>
                            <template x-if="isInterstate">
                                <div class="flex justify-between"><dt class="text-gray-600">IGST</dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.igst)"></dd></div>
                            </template>
                            <div class="flex justify-between"><dt class="text-gray-600">Round off</dt><dd class="font-mono tabular-nums" x-text="'₹' + fmt(totals.roundOff)"></dd></div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 mt-2 text-base"><dt class="font-bold text-gray-900">Payable to vendor</dt><dd class="font-mono tabular-nums font-extrabold text-gray-900" x-text="'₹' + fmt(totals.grandTotal)"></dd></div>
                        </dl>
                        <p x-show="reverseCharge && totals.tax > 0" x-cloak class="mt-3 text-xs text-accent-800 bg-accent-50 border border-accent-200 rounded-md px-2 py-1.5">
                            Reverse charge: <strong x-text="'₹' + fmt(totals.tax)"></strong> GST is payable by you to the government, not the vendor, in GSTR-3B 3.1(d).
                        </p>

                        {{-- 4. Paid now - most small bills are settled on the spot. --}}
                        <div class="mt-5 pt-4 border-t border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Paid already?</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="paid_now" value="Amount paid (₹)" />
                                    <input inputmode="decimal" id="paid_now" name="paid_now" type="number" step="any" min="0" x-model.number="paidNow"
                                           @error('paid_now') aria-invalid="true" aria-describedby="paid_now-error" @enderror
                                           class="mt-1 {{ $field }} text-right tabular-nums" placeholder="0">
                                    <button type="button" @click="paidNow = totals.grandTotal" class="mt-1 inline-flex items-center min-h-[32px] text-xs font-semibold text-brand-700 hover:underline">Paid in full</button>
                                    <x-input-error :messages="$errors->get('paid_now')" for="paid_now" />
                                </div>
                                <div>
                                    <x-input-label for="payment_method" value="Method" />
                                    <select id="payment_method" name="payment_method" class="mt-1 {{ $field }}">
                                        @foreach ($methods as $key => $m)
                                            <option value="{{ $key }}" @selected(old('payment_method', 'neft') === $key)>{{ $m['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('payment_method')" for="payment_method" class="mt-1" />
                                </div>
                                <div x-show="paidNow > 0" x-cloak>
                                    <x-input-label for="paid_on" value="Paid on" />
                                    <input id="paid_on" name="paid_on" type="date" value="{{ old('paid_on', now()->toDateString()) }}"
                                           @error('paid_on') aria-invalid="true" aria-describedby="paid_on-error" @enderror
                                           class="mt-1 {{ $field }} min-w-0">
                                    <x-input-error :messages="$errors->get('paid_on')" for="paid_on" class="mt-1" />
                                </div>
                                <div x-show="paidNow > 0" x-cloak>
                                    <x-input-label for="reference_number" value="Reference / UTR" />
                                    <input id="reference_number" name="reference_number" type="text" value="{{ old('reference_number', $bill->reference_number) }}" maxlength="80" class="mt-1 {{ $field }}">
                                    <x-input-error :messages="$errors->get('reference_number')" for="reference_number" class="mt-1" />
                                </div>
                            </div>
                            @if ($isEdit)
                                <p class="mt-2 text-xs text-gray-500">Manage recorded payments on the purchase invoice page.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <a href="{{ $isEdit ? route('purchases.show', $bill) : route('purchases.index') }}" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Cancel</a>
                    <x-primary-button class="h-10">{{ $isEdit ? 'Save changes' : 'Record purchase invoice' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function purchaseBillForm(initialItems, vendorStates, companyStateId, utStateIds, initial, vendorStoreUrl, csrf, products) {
            products = products || [];
            let uid = 0;
            const nameOf = (id, fallback) => (products.find(p => p.id === id) || {}).name || fallback || '';
            const line = (i) => {
                const productId = String(i.product_id || '');
                return { discount: 0, gst_rate: 18, ...i, product_id: productId, amount: 0, tax: 0, total: 0,
                    _uid: ++uid, _q: productId ? nameOf(productId, i.product_name) : '', _open: false, _hi: 0 };
            };
            return {
                items: initialItems.map(line),
                products,
                hasTracked: products.some(p => p.track),
                lineErrors: initial.lineErrors || {},
                category: initial.category || 'misc',
                categoryTouched: false,
                isTracked(item) { const p = this.products.find(x => x.id === item.product_id); return !!(p && p.track); },
                // --- Item picker: type to filter the saved items by name, SKU or HSN.
                matches(item) {
                    const q = (item._q || '').trim().toLowerCase();
                    const picked = item.product_id && q === nameOf(item.product_id, item.product_name).toLowerCase();
                    const list = (!q || picked) ? this.products
                        : this.products.filter(p => p.name.toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q) || (p.hsn_sac || '').toLowerCase().includes(q));
                    return list.slice(0, 50);
                },
                itemMeta(p) {
                    return [p.sku, p.hsn_sac ? 'HSN ' + p.hsn_sac : '', p.unit, p.track ? 'Tracks stock' : ''].filter(Boolean).join(' · ');
                },
                openItems(item) { item._open = true; },
                searchItems(item) {
                    item._open = true; item._hi = 0;
                    // Typing over a picked item unlinks it; picking again links it.
                    if (item.product_id && item._q !== nameOf(item.product_id, item.product_name)) { item.product_id = ''; this.autoCategory(); }
                },
                moveHighlight(item, dir) {
                    item._open = true;
                    const n = this.matches(item).length;
                    if (n) item._hi = (item._hi + dir + n) % n;
                },
                pickHighlighted(item) { const p = this.matches(item)[item._hi]; if (p && item._open) this.pick(item, p); },
                leaveItem(item) {
                    item._open = false;
                    // A name typed with no saved item becomes the description.
                    if (!item.product_id && item._q && !item.description) item.description = item._q.trim();
                },
                clearItem(item) { item.product_id = ''; item._q = ''; item._open = false; this.autoCategory(); },
                // Fill the line from the item. A value typed by hand stays; one that
                // came from the previously picked item is replaced.
                pick(item, p) {
                    const prev = this.products.find(x => x.id === item.product_id) || {};
                    const fromPrev = (v, k) => !v || (prev[k] !== undefined && String(v) === String(prev[k] || ''));
                    if (fromPrev(item.description, 'name')) item.description = p.name;
                    if (fromPrev(item.hsn_sac, 'hsn_sac')) item.hsn_sac = p.hsn_sac || '';
                    if (fromPrev(item.unit, 'unit')) item.unit = p.unit || '';
                    if ((!(parseFloat(item.rate) > 0) || (prev.rate > 0 && parseFloat(item.rate) === prev.rate)) && p.rate > 0) item.rate = p.rate;
                    item.gst_rate = p.gst_rate;
                    item.product_id = p.id;
                    item._q = p.name;
                    item._open = false;
                    delete this.lineErrors[`items.${this.items.indexOf(item)}.description`];
                    this.autoCategory();
                    this.recompute();
                },
                // A new bill with goods items on it is a purchase of stock, until
                // the category is chosen by hand.
                autoCategory() {
                    if (!initial.autoCategory || this.categoryTouched || !['misc', 'purchases'].includes(this.category)) return;
                    this.category = this.items.some(i => i.product_id) ? 'purchases' : 'misc';
                },
                lineError(idx, field) { return this.lineErrors[`items.${idx}.${field}`] || ''; },
                clearLineError(idx, field) { if (this.lineErrors[`items.${idx}.${field}`]) delete this.lineErrors[`items.${idx}.${field}`]; },
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
                addRow() { this.items.push(line({ product_id: '', description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, discount: 0, gst_rate: 18 })); },
                removeRow(i) { if (this.items.length > 1) this.items.splice(i, 1); this.autoCategory(); this.recompute(); },
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
