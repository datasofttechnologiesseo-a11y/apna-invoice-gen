<x-app-layout :title="$invoice->exists ? 'Edit invoice' : 'New invoice'">
    @php $restricted = $restricted ?? false; $isSample = ! $invoice->exists && request()->boolean('sample'); @endphp
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Invoices', 'href' => route('invoices.index')],
            ['label' => $invoice->exists ? $invoice->displayNumber() : 'New invoice'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight flex flex-wrap items-center gap-2">
                {{ $invoice->exists ? 'Edit ' . $invoice->displayNumber() : 'New invoice' }}
                @if ($restricted)
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-accent-50 text-accent-800 ring-1 ring-inset ring-accent-200 font-medium">Limited edit</span>
                @elseif ($isSample)
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200 font-medium">Sample, not saved yet</span>
                @elseif (! $invoice->exists && ! empty($templateLabel))
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-brand-50 text-brand-800 ring-1 ring-inset ring-brand-200 font-medium">Template: {{ $templateLabel }}</span>
                @endif
            </h1>
            @unless ($invoice->exists || $restricted)
                <a href="{{ route('invoices.templates') }}" class="inline-flex items-center gap-1 min-h-[40px] text-sm text-brand-700 hover:text-brand-800 hover:underline self-start sm:self-auto">
                    Browse templates
                </a>
            @endunless
        </div>
    </x-slot>

    @php
        $existingItems = $invoice->items->map(fn ($i) => [
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
            if (! $invoice->exists && request()->boolean('sample')) {
                // One-click "Try a sample invoice": prefill demo lines so a new
                // user instantly sees a populated invoice with live GST totals.
                // Nothing is saved until they hit Save.
                $oldItems = [
                    ['product_id' => null, 'description' => 'Consulting services (sample)', 'hsn_sac' => '998311', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 1500, 'discount' => 0, 'gst_rate' => 18],
                    ['product_id' => null, 'description' => 'Website design (sample)', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 25000, 'discount' => 0, 'gst_rate' => 18],
                ];
            } else {
                $oldItems = [['product_id' => null, 'description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'discount' => 0, 'gst_rate' => 18]];
            }
        }
        $isSample = ! $invoice->exists && request()->boolean('sample');
        // Invoice-level discount box: a re-shown form keeps what was typed; an
        // existing draft opens with the sum of its saved line discounts.
        $initialDiscount = (float) old('invoice_discount', collect($oldItems)->sum(fn ($i) => (float) ($i['discount'] ?? 0)));
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        // Map customer id → bool "has GSTIN", used for the B2C > ₹2.5L warning.
        $customerHasGstinMap = $customers->mapWithKeys(fn ($c) => [$c->id => ! empty($c->gstin)])->toJson();
        $companyStateId = $company->state_id;
        // Union Territories without a legislature levy UTGST instead of SGST;
        // the form label follows the customer's state the same way the PDF does.
        $utStateIds = \App\Models\State::where('is_union_territory', true)->pluck('id')->values();
        // GST place-of-supply 96: a customer abroad - billed as an export.
        $foreignStateId = \App\Models\State::where('gst_code', '96')->value('id');
        // Lean customer index for the typeahead combobox - searchable on name,
        // phone, GSTIN, city, and state name (matches how Indian SMEs look up
        // customers - by phone or trade name, not by formal database lookup).
        $customerIndex = $customers->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'state_name' => $c->state?->name,
            'state_id' => $c->state_id,
            'phone' => $c->phone,
            'gstin' => $c->gstin,
            'city' => $c->city,
        ])->values();
        // With the typeahead combobox, we no longer pre-render every product into
        // the DOM. Only seed productMap with the products that are actually
        // referenced by the current invoice rows - needed for the search box to
        // display "selected" names in edit mode. Everything else is fetched
        // on-demand via /products/search. Big speedup for shops with 1000+ items.
        $itemProductIds = collect($oldItems)->pluck('product_id')->filter()->unique()->values();
        $productIndex = $itemProductIds->isEmpty()
            ? collect([])
            : $company->products()
                ->whereIn('id', $itemProductIds)
                ->withSum('stockMovements', 'quantity')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'hsn_sac' => $p->hsn_sac, 'unit' => $p->unit,
                    'rate' => $p->rate, 'gst_rate' => $p->gst_rate,
                    'stock' => $p->track_stock ? $p->stockOnHand() : null,
                ])->values();

        // Map failed line-item validation to { rowIndex: { field: message } } so
        // the form can highlight the exact rows/fields that need fixing after a
        // server-side validation error, instead of just listing them in a banner.
        $itemErrors = [];
        foreach ($errors->keys() as $key) {
            if (preg_match('/^items\.(\d+)\.(\w+)$/', $key, $m)) {
                $itemErrors[(int) $m[1]][$m[2]] = $errors->first($key);
            }
        }

        // "More options" holds what most invoices never touch. It opens by
        // itself when any of it is filled in or has an error, so nothing set
        // there is ever hidden from the person editing.
        $moreFields = ['supply_type', 'export_port_code', 'shipping_bill_number', 'shipping_bill_date', 'reverse_charge', 'style',
            'transporter_name', 'transporter_id', 'vehicle_number', 'transport_mode', 'eway_bill_number',
            'ship_to_name', 'ship_to_address_line1', 'ship_to_address_line2', 'ship_to_city', 'ship_to_state_id', 'ship_to_postal_code', 'ship_to_gstin'];
        $transporterPrefilled = old('transporter_name', $invoice->transporter_name)
            || old('transporter_id', $invoice->transporter_id)
            || old('vehicle_number', $invoice->vehicle_number)
            || old('transport_mode', $invoice->transport_mode)
            || old('eway_bill_number', $invoice->eway_bill_number);
        $shipToPrefilled = old('ship_to_name', $invoice->ship_to_name)
            || old('ship_to_address_line1', $invoice->ship_to_address_line1)
            || old('ship_to_address_line2', $invoice->ship_to_address_line2)
            || old('ship_to_city', $invoice->ship_to_city)
            || old('ship_to_state_id', $invoice->ship_to_state_id)
            || old('ship_to_postal_code', $invoice->ship_to_postal_code)
            || old('ship_to_gstin', $invoice->ship_to_gstin);
        $moreOpen = $transporterPrefilled || $shipToPrefilled
            || old('supply_type', $invoice->supply_type ?: 'regular') !== 'regular'
            || (bool) old('reverse_charge', $invoice->reverse_charge ?? false)
            || old('style', $invoice->style ?: 'classic') !== 'classic'
            || $errors->hasAny($moreFields);

        $lblClass = 'block text-sm font-medium text-gray-700';
        $field = 'mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600';
        $cell = 'border-gray-300 rounded-md text-sm focus:border-brand-600 focus:ring-brand-600';
    @endphp

    <div class="py-6 sm:py-8" x-data='invoiceForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }}, @json($productIndex), {{ $customerHasGstinMap }}, @json($customerIndex), {{ ! empty($company->gstin) ? 'true' : 'false' }}, @json((object) $itemErrors))'
         data-restricted="{{ $restricted ? '1' : '0' }}"
         @customer-added.window="addCustomer($event.detail)"
         {{-- power-user keyboard shortcuts. Listen at window level so they
              fire from anywhere on the page (combobox dropdowns, side panels
              etc.). The handler inspects $event.target so we don't hijack
              Ctrl+S while the user is typing in a textarea. --}}
         @keydown.window="onShortcut($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            @if ($errors->any())
                <div class="p-4 bg-danger-50 border border-danger-200 text-danger-800 rounded-xl" role="alert">
                    <div class="font-semibold">Correct the following before saving:</div>
                    <ul class="list-disc pl-6 mt-1 text-sm">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    @if (! empty($itemErrors))
                        <div class="mt-1.5 text-xs">The affected {{ count($itemErrors) === 1 ? 'row is' : 'rows are' }} highlighted below.</div>
                    @endif
                </div>
            @endif

            @if ($restricted)
                <div class="p-4 bg-accent-50 border border-accent-200 text-accent-900 rounded-xl text-sm">
                    <div class="font-semibold">Issued invoice. Only some fields can be changed.</div>
                    <div class="mt-0.5">You can change the notes, terms, due date and transport details. Amounts, items, customer and invoice number are locked under GST rules: issue a credit note, or cancel this invoice and issue a new one.</div>
                </div>
            @endif

            {{-- First-time tip: one line, only on a new invoice, dismissible. --}}
            @unless ($invoice->exists)
                <div x-data="{ show: !localStorage.getItem('hideFirstInvoiceTip') }" x-show="show" x-cloak
                     class="px-4 py-2 bg-brand-50 border border-brand-200 text-brand-900 rounded-xl flex items-center gap-3 text-sm">
                    <p class="flex-1">Pick the customer, add the items, then save the draft. Issuing it assigns the invoice number and locks the amounts.</p>
                    <button type="button" @click="localStorage.setItem('hideFirstInvoiceTip','1'); show=false" class="shrink-0 inline-flex items-center justify-center w-10 h-10 -my-1 -mr-2 text-brand-800 hover:bg-brand-100 rounded-md text-xl leading-none" aria-label="Dismiss tip">×</button>
                </div>
            @endunless

            @if ($customers->isEmpty() && ! $restricted)
                <div class="p-4 bg-accent-50 border border-accent-200 text-accent-900 rounded-xl flex flex-col sm:flex-row sm:items-center gap-3 text-sm">
                    <div class="flex-1"><span class="font-semibold">No customers yet.</span> Add one here without leaving the invoice.</div>
                    <button type="button" @click="$dispatch('open-quick-customer')" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 whitespace-nowrap">New customer</button>
                </div>
            @endif

            {{-- Without a business state the invoice cannot tell a same-state sale
                 from an inter-state one; a taxed invoice cannot be issued (see
                 InvoiceController). --}}
            @if (! $company->state_id && ! $restricted)
                <div class="p-4 bg-accent-50 border border-accent-200 text-accent-900 rounded-xl flex flex-col sm:flex-row sm:items-center gap-3 text-sm">
                    <div class="flex-1"><span class="font-semibold">Your business state is not set.</span> Every sale is taxed as intra-state until it is, and a GST invoice cannot be issued.</div>
                    <a href="{{ route('company.edit') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 whitespace-nowrap">Set business state</a>
                </div>
            @endif

            <form id="invoice-form" method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-5">
                @csrf
                @if ($invoice->exists) @method('PATCH') @endif
                <input type="hidden" name="currency" value="INR">

                {{-- Who and when. Everything rarely used is under More options. --}}
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 lg:gap-5">
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="customer_search" class="{{ $lblClass }}">Customer *</label>
                            @if ($restricted)
                                @php $lockedCustomer = $customers->firstWhere('id', $invoice->customer_id); @endphp
                                <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                                <div id="customer_search" class="mt-1 px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800">
                                    {{ $lockedCustomer?->name }}{{ $lockedCustomer?->state?->name ? ' · ' . $lockedCustomer->state->name : '' }}
                                </div>
                            @else
                                {{-- Combobox: type to search name/phone/GSTIN/city/state. ↑↓ walk,
                                     Enter picks, Esc closes. The hidden input submits customer_id. --}}
                                <div class="relative mt-1" @click.outside="customerCombo.open = false">
                                    <input type="hidden" name="customer_id" :value="customerId || ''" required>
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <input id="customer_search"
                                                   type="text"
                                                   x-model="customerCombo.search"
                                                   @focus="openCustomerCombo()"
                                                   @input="onCustomerComboInput()"
                                                   @keydown.arrow-down.prevent="customerComboMove(1)"
                                                   @keydown.arrow-up.prevent="customerComboMove(-1)"
                                                   @keydown.enter.prevent="customerComboEnter()"
                                                   @keydown.escape="customerCombo.open = false"
                                                   placeholder="Search by name, phone or GSTIN…"
                                                   autocomplete="off"
                                                   aria-describedby="customer_id-error"
                                                   class="block w-full h-10 border-gray-300 rounded-md shadow-sm pr-10 focus:border-brand-600 focus:ring-brand-600">
                                            <button type="button"
                                                    x-show="customerId"
                                                    @click="clearCustomer()"
                                                    class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700"
                                                    aria-label="Clear customer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <div x-show="customerCombo.open" x-cloak
                                                 class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-72 overflow-y-auto">
                                                <template x-if="customerComboFiltered.length === 0 && !customerCombo.search.trim()">
                                                    <div class="px-3 py-2 text-sm text-gray-500">Type to search customers</div>
                                                </template>
                                                <template x-for="(c, n) in customerComboFiltered" :key="c.id">
                                                    <button type="button"
                                                            @click="selectCustomer(c)"
                                                            @mouseenter="customerCombo.highlight = n"
                                                            class="block w-full text-left px-3 py-2 text-sm hover:bg-brand-50 transition"
                                                            :class="customerCombo.highlight === n ? 'bg-brand-50' : ''">
                                                        <div class="font-medium text-gray-900" x-text="c.name"></div>
                                                        <div class="text-xs text-gray-600">
                                                            <span x-text="c.state_name || 'No state set'"></span>
                                                            <template x-if="c.phone"><span> · <span x-text="c.phone"></span></span></template>
                                                            <template x-if="c.gstin"><span> · GSTIN <span class="font-mono" x-text="c.gstin"></span></span></template>
                                                        </div>
                                                    </button>
                                                </template>
                                                <button type="button"
                                                        x-show="customerCombo.search.trim()"
                                                        @click="quickAddCustomer(customerCombo.search.trim())"
                                                        class="block w-full text-left px-3 py-2 text-sm bg-brand-50 hover:bg-brand-100 text-brand-900 font-semibold border-t border-brand-200 sticky bottom-0">
                                                    + New customer "<span x-text="customerCombo.search.trim()"></span>"
                                                </button>
                                            </div>
                                        </div>
                                        <button type="button" @click="quickAddCustomer('')"
                                                class="inline-flex items-center justify-center h-10 px-3 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 whitespace-nowrap"
                                                title="Add a new customer">+ New</button>
                                    </div>
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('customer_id')" for="customer_id" class="mt-1" />
                            {{-- Tax treatment follows the two states (and the supply type). --}}
                            <p class="mt-1 text-xs text-gray-600" x-show="customerId" x-cloak>
                                <span x-text="selectedCustomerLabel"></span>
                                <span class="text-gray-500" aria-hidden="true"> · </span>
                                <span class="font-medium text-gray-800" x-text="taxModeLabel"></span>
                            </p>
                            <p x-show="customerAbroad" x-cloak class="mt-1 text-xs text-accent-800">Customer is outside India, so this is billed as an export. Change it under More options.</p>
                        </div>

                        <div>
                            @if ($invoice->exists && ! $invoice->isDraft())
                                <span class="{{ $lblClass }}">Invoice no.</span>
                                <div class="mt-1 py-2 font-mono text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</div>
                            @else
                                {{-- Blank = numbered at issue; a number typed here continues
                                     the series (a firm moving over mid-year). Duplicates are refused. --}}
                                <label for="invoice_number" class="{{ $lblClass }}">Invoice no.</label>
                                <input id="invoice_number" name="invoice_number" type="text" maxlength="40"
                                       value="{{ old('invoice_number', $invoice->invoice_number) }}"
                                       placeholder="{{ $previewNumber ?? $company->nextInvoiceNumber() }}"
                                       aria-describedby="invoice_number-hint invoice_number-error"
                                       class="{{ $field }} font-mono">
                                <p id="invoice_number-hint" class="mt-1 text-xs text-gray-500">Leave blank to number it when issued.</p>
                                <x-input-error :messages="$errors->get('invoice_number')" for="invoice_number" class="mt-1" />
                            @endif
                        </div>

                        <div>
                            <label for="invoice_date" class="{{ $lblClass }}">Invoice date *</label>
                            <input id="invoice_date" name="invoice_date" type="date" required @disabled($restricted)
                                   value="{{ old('invoice_date', $invoice->invoice_date?->toDateString() ?? now()->toDateString()) }}"
                                   aria-describedby="invoice_date-error"
                                   class="{{ $field }} {{ $restricted ? 'bg-gray-100 cursor-not-allowed' : '' }}">
                            <x-input-error :messages="$errors->get('invoice_date')" for="invoice_date" class="mt-1" />
                        </div>

                        <div>
                            <label for="due_date" class="{{ $lblClass }}">Due date</label>
                            <input id="due_date" name="due_date" type="date"
                                   value="{{ old('due_date', $invoice->due_date?->toDateString()) }}"
                                   aria-describedby="due_date-error"
                                   class="{{ $field }}">
                            <x-input-error :messages="$errors->get('due_date')" for="due_date" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- Items --}}
                <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden {{ $restricted ? 'opacity-80' : '' }}">
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                        <h2 class="font-semibold text-gray-900">Items @if ($restricted)<span class="ml-2 text-xs text-accent-800 font-normal">Locked after issue</span>@endif</h2>
                        @unless ($restricted)
                            <span class="hidden lg:inline text-xs text-gray-600">Type in the item box to pick a saved item or add a new one.</span>
                        @endunless
                    </div>
                    <fieldset @disabled($restricted) class="{{ $restricted ? 'pointer-events-none' : '' }}">

                    {{-- Phone and tablet: one card per row. The table takes over from lg (1024px). --}}
                    <div class="lg:hidden divide-y divide-gray-100">
                        {{-- Key by the stable _uid (not idx) so Alpine never reuses a row's
                             DOM node, and its product search, for a different row after a splice. --}}
                        <template x-for="(item, idx) in items" :key="item._uid">
                            <div class="p-4 space-y-3 transition-colors" :data-row-index="idx"
                                 @input="clearRowError(idx)" @change="clearRowError(idx)"
                                 :class="rowHasError(idx) && 'bg-danger-50 ring-1 ring-danger-300 rounded-lg'">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-700">Item <span x-text="idx + 1"></span></span>
                                    @if (! $restricted)
                                        <button type="button" @click="removeRow(idx)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'text-gray-600 cursor-not-allowed' : 'text-danger-700'"
                                                class="inline-flex items-center min-h-[40px] px-2 -mr-2 text-sm font-medium transition"
                                                aria-label="Remove row">Remove</button>
                                    @endif
                                </div>
                                @unless ($restricted)
                                    <div x-data="productRowCombobox(idx, item._uid)" class="relative" @click.outside="combo.open = false; quickAdd.open = false; commitTypedText()">
                                        <label class="text-sm font-medium text-gray-700">Item (search or type a new one)</label>
                                        <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                        <div class="relative">
                                            <input type="text"
                                                   x-ref="comboInput"
                                                   :aria-label="`Product or service for line item ${idx + 1}`"
                                                   x-model="combo.search"
                                                   @focus="openCombo()"
                                                   @input.debounce.250ms="onInput()"
                                                   @keydown.arrow-down.prevent="moveHighlight(1)"
                                                   @keydown.arrow-up.prevent="moveHighlight(-1)"
                                                   @keydown.enter.prevent="enterKey()"
                                                   @keydown.escape="combo.open = false; quickAdd.open = false"
                                                   @blur="commitTypedText()"
                                                   placeholder="Search by name, SKU or HSN…"
                                                   autocomplete="off"
                                                   class="mt-1 block w-full h-10 {{ $cell }} pr-9">
                                            <button type="button" x-show="item.product_id || combo.search" @click="clearRow()"
                                                    class="absolute inset-y-0 right-0 top-1 px-3 text-gray-500 hover:text-gray-700" aria-label="Clear product">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        @include('invoices._product-combobox-panel', ['mobile' => true])
                                    </div>
                                @endunless
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Description <span class="text-gray-500 font-normal">(max 150)</span></label>
                                    {{-- Optional. Blank with a product picked: the server fills it from the product's name. --}}
                                    <input :name="`items[${idx}][description]`" :aria-label="`Item description for line item ${idx + 1}`" x-model="item.description" maxlength="150" placeholder="Optional · filled from the item" class="mt-1 block w-full h-10 {{ $cell }}">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5">
                                            <label class="text-sm font-medium text-gray-700">
                                                HSN/SAC<span x-show="!hsnRequired" class="font-normal text-gray-500"> (optional)</span>
                                            </label>
                                            @include('partials.hsn-search-link')
                                        </div>
                                        <input :name="`items[${idx}][hsn_sac]`" :aria-label="`HSN or SAC code for line item ${idx + 1}`" x-model="item.hsn_sac" inputmode="numeric" maxlength="8" placeholder="e.g. 998314" class="mt-1 block w-full h-10 {{ $cell }} font-mono" :required="hsnRequired">
                                        <p class="mt-1 text-xs" :class="item.hsn_sac && item.hsn_sac.length > 0 && item.hsn_sac.length < 4 ? 'text-accent-800 font-semibold' : 'text-gray-500'">
                                            <template x-if="!item.hsn_sac || item.hsn_sac.length === 0">
                                                <span x-text="hsnRequired ? '4 digits up to ₹5 Cr turnover, 6 above, 8 for exports.' : 'Optional: neither party is GST-registered.'"></span>
                                            </template>
                                            <template x-if="item.hsn_sac && item.hsn_sac.length > 0 && item.hsn_sac.length < 4">
                                                <span>Enter at least 4 digits (Rule 46(g)).</span>
                                            </template>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Unit</label>
                                        <input :name="`items[${idx}][unit]`" aria-label="Unit" x-model="item.unit" class="mt-1 block w-full h-10 {{ $cell }}" placeholder="NOS, KGS, HRS…">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Quantity</label>
                                        <input :name="`items[${idx}][quantity]`" aria-label="Quantity" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="mt-1 block w-full h-10 {{ $cell }} text-right" required>
                                        <p x-show="stockShort(item)" x-cloak class="mt-0.5 text-xs font-semibold text-accent-800" x-text="'Only ' + (+item._stock) + ' in stock'"></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Rate (₹)</label>
                                        <input :name="`items[${idx}][rate]`" aria-label="Rate" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full h-10 {{ $cell }} text-right" required>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-700">GST rate</span>
                                    {{-- The live slabs as one-tap pills; rarer rates under "Other". The
                                         hidden input is the form value. --}}
                                    @php
                                        // GST 2.0 (22 Sep 2025): the live slabs are 0, 5, 18 and 40.
                                        // 12% and 28% stay reachable under "Other" for backdated
                                        // invoices and the brick scheme.
                                        $commonRates = [0, 5, 18, 40];
                                        $rareRates = collect(config('gst.rates'))->whereNotIn('value', $commonRates)->values();
                                    @endphp
                                    <input type="hidden" :name="`items[${idx}][gst_rate]`" :aria-label="`GST rate for line item ${idx + 1}`" :value="item.gst_rate">
                                    <div class="mt-1 grid grid-cols-4 gap-1 p-1 bg-gray-100 rounded-lg">
                                        @foreach ($commonRates as $r)
                                            <button type="button"
                                                    @click="item.gst_rate = {{ $r }}; recompute()"
                                                    :class="(parseFloat(item.gst_rate) === {{ $r }}) ? 'bg-brand-700 text-white shadow' : 'text-gray-800 hover:bg-white/70'"
                                                    :aria-pressed="(parseFloat(item.gst_rate) === {{ $r }}).toString()"
                                                    class="inline-flex items-center justify-center min-h-[40px] text-sm font-semibold rounded transition tabular-nums">
                                                {{ $r }}%
                                            </button>
                                        @endforeach
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2 text-xs text-gray-600">
                                        <span>Other rates:</span>
                                        <select @change="item.gst_rate = parseFloat($event.target.value); recompute()"
                                                aria-label="Other GST rate"
                                                class="flex-1 h-10 border-gray-300 rounded-md text-sm">
                                            <option value="">Other</option>
                                            @foreach ($rareRates as $r)
                                                <option value="{{ $r['value'] }}"
                                                        :selected="parseFloat(item.gst_rate) === {{ $r['value'] }}"
                                                        title="{{ $r['label'] . ' · ' . $r['note'] }}">
                                                    {{ (float) $r['value'] }}% · {{ Str::after($r['label'], '· ') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-between pt-2 border-t text-sm">
                                    <span class="text-gray-600">Line amount <span x-show="item.tax > 0" class="text-xs">(incl. GST)</span></span>
                                    <span class="text-right">
                                        <span class="block font-mono font-semibold text-gray-900 tabular-nums" x-text="'₹' + fmt(item.total)"></span>
                                        <span x-show="item.tax > 0" class="block font-mono text-xs text-gray-600 tabular-nums" x-text="'Taxable ₹' + fmt(item.amount)"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Desktop table, sized to fit 1024px with the sidebar open: the item
                         cell stacks the search, description and HSN/SAC; quantity sits over
                         its unit. --}}
                    <div class="hidden lg:block">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left">
                                        <span class="inline-flex items-center gap-1.5">
                                            Item, description, HSN/SAC
                                            @include('partials.hsn-search-link')
                                        </span>
                                    </th>
                                    <th class="px-1.5 py-2 text-right">Quantity</th>
                                    <th class="px-1.5 py-2 text-right">Rate (₹)</th>
                                    <th class="px-1.5 py-2 text-right">GST</th>
                                    <th class="px-3 py-2 text-right">Amount <span class="font-normal normal-case">(incl. GST)</span></th>
                                    @unless ($restricted)<th class="px-1.5 py-2"><span class="sr-only">Remove</span></th>@endunless
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Stable _uid keying - see the cards above. --}}
                                <template x-for="(item, idx) in items" :key="`d-${item._uid}`">
                                    <tr class="border-t border-gray-100 align-top transition-colors" :data-row-index="idx"
                                        @input="clearRowError(idx)" @change="clearRowError(idx)"
                                        :class="rowHasError(idx) && 'bg-danger-50'">
                                        <td class="px-3 py-2 w-full">
                                            @unless ($restricted)
                                                <div x-data="productRowCombobox(idx, item._uid)" class="relative" @click.outside="combo.open = false; quickAdd.open = false; commitTypedText()">
                                                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                                    <div class="relative">
                                                        <input type="text"
                                                               x-ref="comboInput"
                                                               :aria-label="`Product or service for line item ${idx + 1}`"
                                                               x-model="combo.search"
                                                               @focus="openCombo()"
                                                               @input.debounce.250ms="onInput()"
                                                               @keydown.arrow-down.prevent="moveHighlight(1)"
                                                               @keydown.arrow-up.prevent="moveHighlight(-1)"
                                                               @keydown.enter.prevent="enterKey()"
                                                               @keydown.escape="combo.open = false; quickAdd.open = false"
                                                               @blur="commitTypedText()"
                                                               placeholder="Search or add an item"
                                                               autocomplete="off"
                                                               class="w-full h-9 {{ $cell }} pr-8">
                                                        <button type="button" x-show="item.product_id || combo.search" @click="clearRow()"
                                                                class="absolute inset-y-0 right-0 px-2.5 text-gray-500 hover:text-gray-700" aria-label="Clear product">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                    @include('invoices._product-combobox-panel', ['mobile' => false])
                                                </div>
                                            @endunless
                                            <div class="{{ $restricted ? '' : 'mt-1.5' }} grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_7rem] gap-1.5">
                                                <input :name="`items[${idx}][description]`" :aria-label="`Item description for line item ${idx + 1}`" x-model="item.description" maxlength="150" placeholder="Description (optional)" class="w-full h-9 {{ $cell }}">
                                                <input :name="`items[${idx}][hsn_sac]`" :aria-label="`HSN or SAC code for line item ${idx + 1}`" x-model="item.hsn_sac" inputmode="numeric" maxlength="8" :placeholder="hsnRequired ? 'HSN/SAC' : 'HSN/SAC (optional)'" class="w-full xl:w-28 h-9 rounded-md text-sm font-mono focus:border-brand-600 focus:ring-brand-600" :class="fieldHasError(idx, 'hsn_sac') ? 'border-danger-400 ring-1 ring-danger-300' : 'border-gray-300'" :required="hsnRequired">
                                            </div>
                                        </td>
                                        <td class="px-1.5 py-2">
                                            <input :name="`items[${idx}][quantity]`" aria-label="Quantity" x-model.number="item.quantity" @input="recompute()" type="number" step="any" min="0.001" inputmode="decimal" class="w-20 h-9 rounded-md text-sm text-right focus:border-brand-600 focus:ring-brand-600" :class="fieldHasError(idx, 'quantity') ? 'border-danger-400 ring-1 ring-danger-300' : 'border-gray-300'" required>
                                            <input :name="`items[${idx}][unit]`" aria-label="Unit" x-model="item.unit" class="mt-1.5 w-20 h-9 {{ $cell }} text-right" placeholder="Unit">
                                            <p x-show="stockShort(item)" x-cloak class="mt-0.5 w-20 text-xs font-semibold text-accent-800" x-text="'Only ' + (+item._stock) + ' in stock'"></p>
                                        </td>
                                        <td class="px-1.5 py-2"><input :name="`items[${idx}][rate]`" aria-label="Rate" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="w-24 h-9 rounded-md text-sm text-right focus:border-brand-600 focus:ring-brand-600" :class="fieldHasError(idx, 'rate') ? 'border-danger-400 ring-1 ring-danger-300' : 'border-gray-300'" required></td>
                                        <td class="px-1.5 py-2">
                                            {{-- "18%" only; the full slab name and note are in each option's title. --}}
                                            <select :name="`items[${idx}][gst_rate]`" aria-label="GST rate" x-model.number="item.gst_rate" @change="recompute()" class="w-20 h-9 {{ $cell }}">
                                                @foreach (config('gst.rates') as $r)
                                                    <option value="{{ $r['value'] }}" title="{{ $r['label'] . ' · ' . $r['note'] }}">{{ (float) $r['value'] }}%</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-sm whitespace-nowrap tabular-nums">
                                            <div class="font-medium leading-9" x-text="fmt(item.total)"></div>
                                            <div x-show="item.tax > 0" class="text-xs text-gray-600" x-text="'Taxable ' + fmt(item.amount)"></div>
                                        </td>
                                        @unless ($restricted)
                                            <td class="px-1.5 py-2 text-right">
                                                <button type="button" @click="removeRow(idx)"
                                                        :disabled="items.length <= 1"
                                                        :title="items.length <= 1 ? 'At least one line item is required' : 'Remove this row'"
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md transition"
                                                        :class="items.length <= 1 ? 'text-gray-400 cursor-not-allowed' : 'text-gray-600 hover:text-danger-700 hover:bg-danger-50'"
                                                        aria-label="Remove row">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </td>
                                        @endunless
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    </fieldset>

                    {{-- After the rows, so Tab goes row by row and then here. --}}
                    @unless ($restricted)
                        <div class="px-4 sm:px-6 py-3 border-t border-gray-100 flex items-center gap-3">
                            <button type="button" @click="addRow()" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">+ Add row</button>
                            <span class="hidden lg:inline text-xs text-gray-500">or press F2</span>
                        </div>
                    @endunless

                    {{-- B2C taxable value ≥ ₹50,000 (Rule 46(e) needs name, address and state) --}}
                    <div x-show="showB2cWarning" x-cloak class="border-t bg-accent-50 px-4 sm:px-6 py-3 text-sm text-accent-900">
                        <div class="font-semibold">B2C invoice, taxable value ₹50,000 or more</div>
                        <div class="mt-0.5">Under Rule 46(e) the invoice must show the customer's name, full address, delivery address and state with code. Check that the customer record has them.</div>
                    </div>

                    {{-- Totals first in the tab order (they follow the items), shown on
                         the right; terms and notes on the left. --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 sm:p-6 border-t bg-gray-50">
                        <div class="md:order-2 md:pl-6 space-y-2 text-sm">
                            <div class="flex justify-between"><span>Items total</span><span class="font-mono tabular-nums" x-text="fmt(totals.gross)"></span></div>
                            {{-- One discount for the whole invoice, applied BEFORE GST and
                                 spread over the lines by value (s.15(3)(a)). --}}
                            <div class="flex justify-between items-center gap-2">
                                <label for="invoice_discount_input" class="flex items-center gap-1.5">
                                    Discount
                                    <span class="inline-flex rounded-md border border-gray-300 overflow-hidden text-xs font-semibold">
                                        <button type="button" @click="discountMode = 'amount'; recompute()" :class="discountMode === 'amount' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700'" :aria-pressed="(discountMode === 'amount').toString()" aria-label="Discount in rupees" class="px-3 py-2">₹</button>
                                        <button type="button" @click="discountMode = 'percent'; recompute()" :class="discountMode === 'percent' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700'" :aria-pressed="(discountMode === 'percent').toString()" aria-label="Discount in percent" class="px-3 py-2">%</button>
                                    </span>
                                </label>
                                <input id="invoice_discount_input" type="number" step="any" min="0" inputmode="decimal"
                                       x-model.number="discountInput" @input="recompute()"
                                       :max="discountMode === 'percent' ? 100 : null"
                                       aria-describedby="invoice_discount-error"
                                       class="w-32 h-10 {{ $cell }} text-right" placeholder="0">
                                <input type="hidden" name="invoice_discount" :value="totals.discount">
                            </div>
                            <p x-show="discountMode === 'percent' && totals.discount > 0" x-cloak class="text-xs text-gray-600 text-right -mt-1">= ₹<span x-text="fmt(totals.discount)"></span></p>
                            <x-input-error :messages="$errors->get('invoice_discount')" for="invoice_discount" class="text-right" />
                            <div class="flex justify-between border-t pt-2"><span>Taxable value</span><span class="font-mono tabular-nums" x-text="fmt(totals.subtotal)"></span></div>
                            <div x-show="reverseCharge" class="p-2 rounded-md bg-accent-50 border border-accent-200 text-xs text-accent-900">
                                <strong>Reverse charge:</strong> CGST/SGST/IGST are ₹0 on this invoice. The recipient pays the GST (Section 9(3)/9(4)).
                            </div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono tabular-nums" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span x-text="stateTaxLabel"></span><span class="font-mono tabular-nums" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono tabular-nums" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(totals.grandTotal)"></span></div>
                            <div class="flex justify-between items-center">
                                <label for="paid_amount">Paid amount</label>
                                <input inputmode="decimal" id="paid_amount" name="paid_amount" type="number" step="any" min="0" value="{{ plain_amount(old('paid_amount', $invoice->paid_amount ?? 0)) }}" x-ref="paidInput" @input="recompute()" @disabled($restricted) aria-describedby="paid_amount-error" class="w-32 h-10 {{ $cell }} text-right">
                            </div>
                            <x-input-error :messages="$errors->get('paid_amount')" for="paid_amount" class="text-right" />
                            <p x-show="paidExceedsTotal" x-cloak class="text-xs text-danger-700 text-right">Paid amount is more than the grand total.</p>
                            <div class="flex justify-between border-t pt-2"><span>Balance</span><span class="font-mono tabular-nums" x-text="fmt(balance)"></span></div>
                        </div>

                        <div class="md:order-1 space-y-4">
                            <div>
                                <label for="terms" class="{{ $lblClass }}">Terms &amp; conditions</label>
                                <textarea id="terms" name="terms" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">{{ old('terms', $invoice->terms ?? $company->default_terms) }}</textarea>
                                <x-input-error :messages="$errors->get('terms')" for="terms" class="mt-1" />
                            </div>
                            <div>
                                <label for="notes" class="{{ $lblClass }}">Notes (printed below the terms)</label>
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">{{ old('notes', $invoice->notes) }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" for="notes" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- More options: what most invoices never need. Opens by itself when
                     any of it is filled in or has an error. Field names unchanged. --}}
                <div x-data="{ open: {{ $moreOpen ? 'true' : 'false' }} }" class="bg-white rounded-xl ring-1 ring-gray-200">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="more-options"
                            class="w-full flex items-center justify-between gap-3 px-4 sm:px-6 py-4 text-left hover:bg-gray-50 rounded-xl">
                        <span>
                            <span class="block font-semibold text-gray-900">More options</span>
                            <span class="block text-xs text-gray-600">Export or SEZ, reverse charge, PDF style, transport and e-way bill, ship-to address</span>
                        </span>
                        <svg class="w-5 h-5 text-gray-500 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="more-options" x-show="open" x-cloak class="border-t border-gray-100 px-4 sm:px-6 py-5 space-y-8">

                        {{-- Supply type: regular, or zero-rated export / SEZ (s.16 IGST Act). --}}
                        <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="supply_type" class="{{ $lblClass }}">Supply type</label>
                                <select id="supply_type" name="supply_type" x-model="supplyType" @change="if (supplyType !== 'regular') reverseCharge = false; recompute()" @disabled($restricted)
                                        aria-describedby="supply_type-error" class="{{ $field }}">
                                    @foreach (\App\Models\Invoice::SUPPLY_TYPES as $value => $typeLabel)
                                        <option value="{{ $value }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('supply_type')" for="supply_type" class="mt-1" />
                                <p x-show="isZeroRated" x-cloak class="mt-1 text-xs text-gray-600">
                                    Zero-rated: always IGST.
                                    <span x-show="underLut">
                                        No tax is charged under LUT/bond.
                                        @if ($company->lut_number)
                                            LUT no. {{ $company->lut_number }}.
                                        @else
                                            Add your LUT number in <a href="{{ route('companies.edit', $company) }}" class="underline">Company settings</a> to print it on the invoice.
                                        @endif
                                    </span>
                                    <span x-show="!underLut">IGST is charged and can be claimed as a refund.</span>
                                </p>
                            </div>
                            <div class="flex items-start">
                                <label x-show="!isZeroRated" class="mt-0 md:mt-7 flex items-start gap-2 text-sm text-gray-700 min-h-[40px]">
                                    <input type="checkbox" name="reverse_charge" value="1" x-model="reverseCharge" @change="recompute()" @disabled($restricted)
                                           class="mt-0.5 w-5 h-5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                    <span>
                                        <span class="font-medium">Reverse charge applies</span>
                                        <span class="block text-xs text-gray-600">Section 9(3)/9(4). The tax shows as ₹0 and the recipient pays the GST.</span>
                                    </span>
                                </label>
                            </div>
                            <div x-show="isExport" x-cloak class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="export_port_code" class="{{ $lblClass }}">Port code</label>
                                    <input id="export_port_code" name="export_port_code" type="text" maxlength="10" placeholder="e.g. INNSA1" @disabled($restricted)
                                           value="{{ old('export_port_code', $invoice->export_port_code) }}" aria-describedby="export_port_code-error"
                                           class="{{ $field }} uppercase">
                                    <x-input-error :messages="$errors->get('export_port_code')" for="export_port_code" class="mt-1" />
                                </div>
                                <div>
                                    <label for="shipping_bill_number" class="{{ $lblClass }}">Shipping bill no.</label>
                                    <input id="shipping_bill_number" name="shipping_bill_number" type="text" maxlength="20" @disabled($restricted)
                                           value="{{ old('shipping_bill_number', $invoice->shipping_bill_number) }}" aria-describedby="shipping_bill_number-error"
                                           class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('shipping_bill_number')" for="shipping_bill_number" class="mt-1" />
                                </div>
                                <div>
                                    <label for="shipping_bill_date" class="{{ $lblClass }}">Shipping bill date</label>
                                    <input id="shipping_bill_date" name="shipping_bill_date" type="date" @disabled($restricted)
                                           value="{{ old('shipping_bill_date', $invoice->shipping_bill_date?->toDateString()) }}" aria-describedby="shipping_bill_date-error"
                                           class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('shipping_bill_date')" for="shipping_bill_date" class="mt-1" />
                                </div>
                                <p class="sm:col-span-3 text-xs text-gray-600">Optional until the shipping bill is filed. Reported in GSTR-1 Table 6A.</p>
                            </div>
                        </section>

                        <section>
                            <label for="style" class="{{ $lblClass }}">PDF style</label>
                            <select id="style" name="style" @disabled($restricted) aria-describedby="style-hint style-error"
                                    class="{{ $field }} md:max-w-xs {{ $restricted ? 'bg-gray-100 cursor-not-allowed' : '' }}">
                                @foreach (config('invoice_styles') as $key => $styleDef)
                                    <option value="{{ $key }}" @selected(old('style', $invoice->style ?: 'classic') === $key)>{{ $styleDef['label'] }}</option>
                                @endforeach
                            </select>
                            <p id="style-hint" class="mt-1 text-xs text-gray-600">Colours of the colour PDF. The GST layout is the same in every style.</p>
                            <x-input-error :messages="$errors->get('style')" for="style" class="mt-1" />
                        </section>

                        <section>
                            <h3 class="font-semibold text-gray-900">Transport details</h3>
                            <p class="text-xs text-gray-600">For goods sent by transport and the e-way bill. Still editable after issue.</p>
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="transporter_name" class="{{ $lblClass }}">Transporter name</label>
                                    <input id="transporter_name" name="transporter_name" type="text" value="{{ old('transporter_name', $invoice->transporter_name) }}" aria-describedby="transporter_name-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('transporter_name')" for="transporter_name" class="mt-1" />
                                </div>
                                <div>
                                    <label for="transporter_id" class="{{ $lblClass }}">Transporter ID / GSTIN</label>
                                    <input id="transporter_id" name="transporter_id" type="text" value="{{ old('transporter_id', $invoice->transporter_id) }}" aria-describedby="transporter_id-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('transporter_id')" for="transporter_id" class="mt-1" />
                                </div>
                                <div>
                                    <label for="vehicle_number" class="{{ $lblClass }}">Vehicle number</label>
                                    <input id="vehicle_number" name="vehicle_number" type="text" value="{{ old('vehicle_number', $invoice->vehicle_number) }}" placeholder="e.g. MH12AB1234" aria-describedby="vehicle_number-error" class="{{ $field }} uppercase">
                                    <x-input-error :messages="$errors->get('vehicle_number')" for="vehicle_number" class="mt-1" />
                                </div>
                                <div>
                                    <label for="transport_mode" class="{{ $lblClass }}">Mode</label>
                                    <select id="transport_mode" name="transport_mode" aria-describedby="transport_mode-error" class="{{ $field }}">
                                        <option value="">Select</option>
                                        @foreach (['Road', 'Rail', 'Air', 'Ship'] as $m)
                                            <option value="{{ $m }}" @selected(old('transport_mode', $invoice->transport_mode) === $m)>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('transport_mode')" for="transport_mode" class="mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="eway_bill_number" class="{{ $lblClass }}">E-way bill number</label>
                                    <input id="eway_bill_number" name="eway_bill_number" type="text" value="{{ old('eway_bill_number', $invoice->eway_bill_number) }}" aria-describedby="eway_bill_number-error" class="{{ $field }} font-mono">
                                    <x-input-error :messages="$errors->get('eway_bill_number')" for="eway_bill_number" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="font-semibold text-gray-900">Ship-to address</h3>
                            <p class="text-xs text-gray-600">For delivery to an address other than the billing address. GST follows the place of supply (the bill-to customer's state), not the ship-to state.</p>
                            <fieldset @disabled($restricted) class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label for="ship_to_name" class="{{ $lblClass }}">Consignee name</label>
                                    <input id="ship_to_name" name="ship_to_name" type="text" value="{{ old('ship_to_name', $invoice->ship_to_name) }}" aria-describedby="ship_to_name-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('ship_to_name')" for="ship_to_name" class="mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="ship_to_address_line1" class="{{ $lblClass }}">Delivery address line 1</label>
                                    <input id="ship_to_address_line1" name="ship_to_address_line1" type="text" value="{{ old('ship_to_address_line1', $invoice->ship_to_address_line1) }}" aria-describedby="ship_to_address_line1-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('ship_to_address_line1')" for="ship_to_address_line1" class="mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="ship_to_address_line2" class="{{ $lblClass }}">Delivery address line 2</label>
                                    <input id="ship_to_address_line2" name="ship_to_address_line2" type="text" value="{{ old('ship_to_address_line2', $invoice->ship_to_address_line2) }}" aria-describedby="ship_to_address_line2-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('ship_to_address_line2')" for="ship_to_address_line2" class="mt-1" />
                                </div>
                                <div>
                                    <label for="ship_to_city" class="{{ $lblClass }}">City</label>
                                    <input id="ship_to_city" name="ship_to_city" type="text" value="{{ old('ship_to_city', $invoice->ship_to_city) }}" aria-describedby="ship_to_city-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('ship_to_city')" for="ship_to_city" class="mt-1" />
                                </div>
                                <div>
                                    <label for="ship_to_state_id" class="{{ $lblClass }}">State</label>
                                    <select id="ship_to_state_id" name="ship_to_state_id" aria-describedby="ship_to_state_id-error" class="{{ $field }}">
                                        <option value="">Select</option>
                                        @foreach ($states as $s)
                                            <option value="{{ $s->id }}" @selected(old('ship_to_state_id', $invoice->ship_to_state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('ship_to_state_id')" for="ship_to_state_id" class="mt-1" />
                                </div>
                                <div>
                                    <label for="ship_to_postal_code" class="{{ $lblClass }}">PIN code</label>
                                    <input id="ship_to_postal_code" name="ship_to_postal_code" type="text" inputmode="numeric" maxlength="10" autocomplete="off" value="{{ old('ship_to_postal_code', $invoice->ship_to_postal_code) }}" placeholder="6 digits" aria-describedby="ship_to_postal_code-error" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('ship_to_postal_code')" for="ship_to_postal_code" class="mt-1" />
                                </div>
                                <div>
                                    <label for="ship_to_gstin" class="{{ $lblClass }}">Consignee GSTIN (optional)</label>
                                    <input id="ship_to_gstin" name="ship_to_gstin" type="text" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" value="{{ old('ship_to_gstin', $invoice->ship_to_gstin) }}" aria-describedby="ship_to_gstin-error" class="{{ $field }} uppercase font-mono">
                                    <x-input-error :messages="$errors->get('ship_to_gstin')" for="ship_to_gstin" class="mt-1" />
                                </div>
                            </fieldset>
                        </section>
                    </div>
                </div>

                {{-- Hidden field set by the save buttons so the controller knows
                     whether to open the invoice or also download the PDF. --}}
                <input type="hidden" name="post_save_action" x-ref="postSaveAction" value="">

                {{-- In-page actions. On a phone the sticky bar below carries Save draft;
                     an issued invoice (limited edit) saves from here on every screen. --}}
                <div class="flex flex-wrap items-center justify-end gap-2 {{ $restricted ? '' : 'lg:hidden' }}">
                    <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</a>
                    @if ($restricted)
                        <button type="button" @click="submitForm('')" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white shadow-sm">Save changes</button>
                    @else
                        <button type="button" @click="submitForm('pdf')" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Save and download PDF</button>
                    @endif
                </div>
            </form>

            {{-- Desktop: totals and the save buttons stay in view while scrolling.
                 One row, so it covers as little of the form as possible. --}}
            @unless ($restricted)
            <div class="bottom-action-bar hidden lg:block fixed inset-x-0 bottom-0 z-20 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]"
                 :class="(typeof collapsed !== 'undefined' && collapsed) ? 'lg:pl-16' : 'lg:pl-60'">
                <div class="max-w-7xl mx-auto px-8 py-2.5 flex items-center gap-4">
                    <div class="min-w-0 text-xs text-gray-600">
                        <div class="whitespace-nowrap">
                            Taxable <span class="font-mono text-gray-900" x-text="'₹' + fmt(totals.subtotal)"></span>
                            <span class="mx-1 text-gray-400" aria-hidden="true">·</span>
                            <span x-show="!isInterstate">CGST <span class="font-mono text-gray-900" x-text="fmt(totals.cgst)"></span> · <span x-text="stateTaxLabel"></span> <span class="font-mono text-gray-900" x-text="fmt(totals.sgst)"></span></span>
                            <span x-show="isInterstate">IGST <span class="font-mono text-gray-900" x-text="'₹' + fmt(totals.igst)"></span></span>
                        </div>
                        <div class="hidden xl:block text-gray-500 mt-0.5">
                            <kbd class="font-mono text-gray-700">F2</kbd> add row · <kbd class="font-mono text-gray-700">Ctrl+S</kbd> save draft · <kbd class="font-mono text-gray-700">F9</kbd> save and download PDF
                        </div>
                    </div>
                    <div class="flex-1"></div>
                    <div class="text-right">
                        <div class="text-xs text-gray-600">Grand total</div>
                        <div class="font-display text-xl font-extrabold text-gray-900 tabular-nums leading-tight" x-text="'₹' + fmt(totals.grandTotal)"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- type="button" + submitForm(): blurs first so pending @blur
                             handlers (the item search's commitTypedText) run before posting. --}}
                        <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="inline-flex items-center justify-center h-10 px-3 rounded-md text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="button" @click="submitForm('')"
                                class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition">
                            Save draft
                        </button>
                        <button type="button" @click="submitForm('pdf')"
                                class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white shadow-sm transition">
                            Save and download PDF
                        </button>
                    </div>
                </div>
            </div>
            {{-- Room under the form so the bar never covers its last part. --}}
            <div class="hidden lg:block h-20" aria-hidden="true"></div>

            {{-- Phone and tablet: the total and Save draft always in reach. --}}
            <div class="lg:hidden fixed inset-x-0 bottom-0 z-20 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] px-4 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-600">Grand total</div>
                    <div class="font-bold text-lg text-gray-900 tabular-nums leading-tight truncate" x-text="'₹' + fmt(totals.grandTotal)"></div>
                </div>
                <button type="button" @click="submitForm('')"
                        class="inline-flex items-center justify-center h-11 px-5 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white shadow-sm">
                    Save draft
                </button>
            </div>
            <div class="lg:hidden h-20" aria-hidden="true"></div>
            @endunless

            @if ($invoice->exists && $invoice->isEditable() && ! $restricted)
                <div class="p-4 sm:p-5 bg-white ring-1 ring-gray-200 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="text-sm">
                        <div class="font-semibold text-gray-900">Delete this draft</div>
                        <div class="text-gray-600">The draft and its line items are deleted permanently.</div>
                    </div>
                    <x-confirm-form
                        :action="route('invoices.destroy', $invoice)"
                        method="DELETE"
                        title="Delete this draft?"
                        message="The draft and its line items will be deleted. This cannot be undone."
                        confirm-label="Delete draft"
                        confirm-class="bg-danger-600 hover:bg-danger-700"
                        tone="danger">
                        <button type="button" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-danger-300 text-danger-700 hover:bg-danger-50">Delete draft</button>
                    </x-confirm-form>
                </div>
            @endif
        </div>
    </div>

    {{-- Inline "+ New customer" modal - opens when the dropdown's "+ New" is clicked.
         Self-contained: dispatches `customer-added` on success, the form listens
         and updates the dropdown without losing typed data. --}}
    <x-quick-customer-modal :states="$states" />

    @push('scripts')
    <script>
        const UT_STATE_IDS = @json($utStateIds);
        const FOREIGN_STATE_ID = @json($foreignStateId);
        const HAS_LUT = @json((bool) $company->lut_number);
        function invoiceForm(initialItems, customerStates, companyStateId, productIndex, customerHasGstin, customerIndex, companyHasGstin, itemErrors) {
            const productMap = {};
            (productIndex || []).forEach(p => { productMap[p.id] = p; });
            // Coerce ids to strings for consistent comparison (HTML inputs are strings).
            const customers = (customerIndex || []).map(c => ({...c, id: String(c.id)}));
            // Generate a row-stable id so x-for keys survive splice() without
            // Alpine reusing the wrong DOM node - see "row drift" bug where
            // deleting row 1 left row 0's combobox showing the deleted row's
            // typed text. crypto.randomUUID exists in all modern browsers; the
            // Math.random fallback is good enough for purely-local keys.
            const newUid = () => (window.crypto?.randomUUID?.() || ('row-' + Math.random().toString(36).slice(2) + '-' + Date.now()));
            return {
                items: initialItems.map(i => ({product_id: null, _uid: newUid(), _prevProductName: null, ...i,
                    _stock: (i.product_id && productMap[i.product_id]) ? (productMap[i.product_id].stock ?? null) : null,
                    amount: 0, tax: 0, total: 0})),
                // More on the line than is on the shelf: warn, never block - the
                // purchase may simply not be entered yet.
                stockShort(item) {
                    return item._stock !== null && item._stock !== undefined && (parseFloat(item.quantity) || 0) > +item._stock;
                },
                _newUid: newUid,
                // Per-row validation errors from the last server submit, keyed by
                // the row's original index: { "2": { hsn_sac: "Row 3: …" } }.
                itemErrors: itemErrors || {},
                rowHasError(idx) {
                    const e = this.itemErrors[idx];
                    return !!e && Object.keys(e).length > 0;
                },
                fieldHasError(idx, field) {
                    return !!(this.itemErrors[idx] && this.itemErrors[idx][field]);
                },
                // Clear a row's error highlight as soon as the user edits it, so
                // stale red doesn't linger while they're fixing the line.
                clearRowError(idx) {
                    if (this.itemErrors[idx]) this.itemErrors = { ...this.itemErrors, [idx]: {} };
                },
                customerId: @json((string) old('customer_id', $invoice->customer_id ?? '')),
                customerStates,
                customerHasGstin: customerHasGstin || {},
                companyStateId,
                companyHasGstin: !! companyHasGstin,
                productMap,
                // Reactive customer list - combobox iterates this. Mutated by addCustomer()
                // when a new customer is created inline, so the dropdown updates without reload.
                customers,
                customerCombo: { search: '', open: false, highlight: 0 },
                reverseCharge: @json((bool) old('reverse_charge', $invoice->reverse_charge ?? false)),
                // regular | export_igst | export_lut | sez_igst | sez_lut
                supplyType: @json(old('supply_type', $invoice->supply_type ?: 'regular')),
                get isZeroRated() { return this.supplyType !== 'regular'; },
                get isExport() { return this.supplyType.startsWith('export_'); },
                get underLut() { return this.supplyType.endsWith('_lut'); },
                // A customer whose state is "Foreign Country" on a regular invoice.
                get customerAbroad() {
                    return !!this.customerId && !!FOREIGN_STATE_ID && this.customerStates[this.customerId] == FOREIGN_STATE_ID;
                },
                totals: {gross: 0, discount: 0, subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                // Invoice-level discount: typed as ₹ or %, stored as ₹. An existing
                // draft opens with the sum of its saved line discounts.
                discountMode: 'amount',
                discountInput: @json($initialDiscount) || '',
                paidExceedsTotal: false,
                balance: 0,
                get isInterstate() {
                    // Exports and SEZ supplies are inter-state by law (s.7(5) IGST Act).
                    if (this.supplyType !== 'regular') return true;
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                // "SGST" or "UTGST" for the state share (mirrors Invoice::stateTaxLabel).
                get stateTaxLabel() {
                    const cs = this.customerId ? this.customerStates[this.customerId] : this.companyStateId;
                    return UT_STATE_IDS.includes(cs) ? 'UTGST' : 'SGST';
                },
                // One line under the customer: how this invoice is taxed.
                get taxModeLabel() {
                    if (this.supplyType !== 'regular') {
                        const what = this.isExport ? 'Export' : 'Supply to SEZ';
                        return what + (this.underLut ? ' under LUT, no IGST' : ' with IGST');
                    }
                    if (this.reverseCharge) return 'Reverse charge: recipient pays the GST';
                    return this.isInterstate ? 'Inter-state: IGST' : 'Intra-state: CGST + ' + this.stateTaxLabel;
                },
                get showB2cWarning() {
                    if (!this.customerId) return false;
                    // Shown only when the customer has NO GSTIN and the taxable value
                    // is ₹50,000 or more - Rule 46(e) is keyed on taxable value.
                    return !this.customerHasGstin[this.customerId] && this.totals.subtotal >= 50000;
                },
                /**
                 * HSN/SAC is legally required only when at least one party is
                 * GST-registered (Rule 46(g)). For supplier-not-registered AND
                 * customer-not-registered transactions (effectively non-GST
                 * cash sales), HSN is optional - making users type it adds
                 * friction with no compliance value. Reactive so it updates the
                 * moment a B2B customer is picked.
                 */
                get hsnRequired() {
                    if (this.companyHasGstin) return true;
                    if (this.customerId && this.customerHasGstin[this.customerId]) return true;
                    return false;
                },
                /**
                 * Filtered customer list shown in the dropdown. Searches across name,
                 * phone, GSTIN, city, and state name - matches how Indian SMEs look
                 * up customers (often by phone number or trade name). Case-insensitive.
                 * Empty search returns the first 20 entries so the list isn't huge.
                 */
                get customerComboFiltered() {
                    const q = this.customerCombo.search.trim().toLowerCase();
                    if (!q) return this.customers.slice(0, 20);
                    return this.customers.filter(c => {
                        return (c.name || '').toLowerCase().includes(q)
                            || (c.phone || '').toLowerCase().includes(q)
                            || (c.gstin || '').toLowerCase().includes(q)
                            || (c.city || '').toLowerCase().includes(q)
                            || (c.state_name || '').toLowerCase().includes(q);
                    }).slice(0, 50);
                },
                /** Label shown under the combobox when a customer is selected. */
                get selectedCustomerLabel() {
                    if (!this.customerId) return '';
                    const c = this.customers.find(x => x.id === this.customerId);
                    if (!c) return '';
                    const parts = [c.name];
                    if (c.state_name) parts.push(c.state_name);
                    if (c.gstin) parts.push('GSTIN ' + c.gstin);
                    return parts.join(' · ');
                },
                init() {
                    // Seed the search box with the selected customer's name on edit.
                    if (this.customerId) {
                        const c = this.customers.find(x => x.id === this.customerId);
                        if (c) this.customerCombo.search = c.name;
                    }
                    this.recompute();

                    // Picking a customer abroad switches a regular invoice to an
                    // export (under LUT when one is on file), and back again.
                    this.$watch('customerId', () => {
                        if (this.customerAbroad && this.supplyType === 'regular') {
                            this.supplyType = HAS_LUT ? 'export_lut' : 'export_igst';
                        } else if (!this.customerAbroad && this.isExport) {
                            this.supplyType = 'regular';
                        }
                        this.recompute();
                    });

                    // After a server validation error, scroll the first flagged
                    // row into view so the user isn't hunting through a long list.
                    const firstBad = Object.keys(this.itemErrors)
                        .map(Number).filter(i => this.rowHasError(i)).sort((a, b) => a - b)[0];
                    if (firstBad !== undefined) {
                        this.$nextTick(() => {
                            const el = this.$root.querySelector(`[data-row-index="${firstBad}"]`);
                            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        });
                    }
                },
                openCustomerCombo() {
                    this.customerCombo.open = true;
                    this.customerCombo.highlight = 0;
                },
                onCustomerComboInput() {
                    this.customerCombo.open = true;
                    this.customerCombo.highlight = 0;
                    // If the user starts typing fresh text that no longer matches
                    // the selected customer, treat it as a re-selection in progress.
                    if (this.customerId) {
                        const c = this.customers.find(x => x.id === this.customerId);
                        if (c && c.name !== this.customerCombo.search) {
                            this.customerId = '';
                            this.recompute();
                        }
                    }
                },
                customerComboMove(direction) {
                    this.customerCombo.open = true;
                    const len = this.customerComboFiltered.length;
                    if (len === 0) return;
                    this.customerCombo.highlight = (this.customerCombo.highlight + direction + len) % len;
                },
                customerComboEnter() {
                    const list = this.customerComboFiltered;
                    if (list.length > 0 && this.customerCombo.highlight >= 0 && this.customerCombo.highlight < list.length) {
                        this.selectCustomer(list[this.customerCombo.highlight]);
                    } else if (this.customerCombo.search.trim()) {
                        // No matches but text typed - quick-add it.
                        this.quickAddCustomer(this.customerCombo.search.trim());
                    }
                },
                selectCustomer(c) {
                    this.customerId = String(c.id);
                    this.customerCombo.search = c.name;
                    this.customerCombo.open = false;
                    this.recompute();
                },
                clearCustomer() {
                    this.customerId = '';
                    this.customerCombo.search = '';
                    this.customerCombo.open = false;
                    this.recompute();
                },
                /**
                 * Open the quick-add modal with the typed text pre-filled as the
                 * customer name. Saves the user from re-typing what they already typed.
                 */
                quickAddCustomer(prefillName) {
                    this.$dispatch('open-quick-customer', { name: prefillName || '' });
                },
                /**
                 * Called by the @customer-added.window listener after the inline
                 * "Quick add customer" modal saves a new customer. Pushes the new
                 * customer into the reactive list, updates the place-of-supply maps,
                 * and selects it - without losing any typed line items.
                 */
                addCustomer(c) {
                    if (!c || !c.id) return;
                    const id = String(c.id);
                    this.customers.push({
                        id,
                        name: c.name,
                        state_name: c.state_name,
                        state_id: c.state_id,
                        phone: c.phone,
                        gstin: c.gstin,
                        city: c.city || null,
                    });
                    this.customerStates[id] = c.state_id;
                    this.customerHasGstin[id] = !!c.has_gstin;
                    this.customerId = id;
                    this.customerCombo.search = c.name;
                    this.customerCombo.open = false;
                    this.recompute();
                },
                addRow() {
                    // Stamp a fresh _uid so x-for keying is stable across splice()s.
                    this.items.push({_uid: this._newUid(), _prevProductName: null, product_id: null, description: '', hsn_sac: '', quantity: 1, unit: '', rate: 0, discount: 0, gst_rate: 18, amount: 0, tax: 0, total: 0});
                },

                /**
                 * Keyboard shortcuts (power-user - common in Indian SME billing apps).
                 *   F2          → add a new row
                 *   F9          → save & download PDF
                 *   Ctrl+S      → save draft  (overrides browser save-as)
                 *   Cmd+S       → same on macOS
                 *
                 * We deliberately do NOT hijack Esc (lets users dismiss the combobox)
                 * or the function keys F1/F5 (browser refresh / help) so the page
                 * stays predictable. Shortcuts work even while a combobox is open
                 * because they're at window level.
                 */
                onShortcut(e) {
                    // Restricted (issued) invoices: only soft fields editable. Don't
                    // accidentally re-issue PDFs via F9. Let the existing buttons handle it.
                    if (this.$root?.dataset?.restricted === '1') return;
                    const tag = (e.target?.tagName || '').toLowerCase();
                    const isTextarea = tag === 'textarea';
                    const key = e.key;

                    if (key === 'F2') {
                        e.preventDefault();
                        this.addRow();
                        return;
                    }
                    if (key === 'F9') {
                        e.preventDefault();
                        this.submitForm('pdf');
                        return;
                    }
                    if ((e.ctrlKey || e.metaKey) && (key === 's' || key === 'S')) {
                        // Skip if focus is in a textarea - Ctrl+S in a textarea
                        // is harmless but some users use it for autosave plugins.
                        if (isTextarea) return;
                        e.preventDefault();
                        this.submitForm('');
                    }
                },

                /**
                 * Programmatic form submit used by shortcuts AND by the sticky
                 * bar buttons. Sets the hidden post_save_action and calls
                 * requestSubmit() so HTML5 validation still runs (required fields,
                 * GSTIN format, etc.) and is identical to a click on the button.
                 *
                 * First we force the active element to blur - this triggers any
                 * pending @blur handlers, most importantly the combobox's
                 * commitTypedText() which mirrors typed-but-not-picked product
                 * names into the line description. Without this, Ctrl+S / F9
                 * would submit before that mirror runs.
                 */
                submitForm(action) {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    if (this.$refs.postSaveAction) {
                        this.$refs.postSaveAction.value = action;
                    }
                    const form = document.getElementById('invoice-form');
                    if (form) form.requestSubmit();
                },
                removeRow(i) {
                    if (this.items.length > 1) this.items.splice(i, 1);
                    this.recompute();
                },
                pickProduct(idx, productId) {
                    const row = this.items[idx];
                    if (!productId) {
                        row.product_id = null;
                        row._prevProductName = null;
                        this.recompute();
                        return;
                    }
                    const p = this.productMap[productId];
                    if (!p) return;
                    row.product_id = p.id;
                    // Description preservation rule:
                    //  • blank / "0" / junk           → fill with product name
                    //  • equals previous product name → user hadn't customised it
                    //    (we're swapping products) → fill with new product name
                    //  • anything else                → user typed a real note ("Phase 1",
                    //    "delivered to gate 3"); KEEP it. Picking a product now only
                    //    sets HSN/rate/GST without destroying the note.
                    const curDesc = (row.description ?? '').toString().trim();
                    const descIsJunk = curDesc === '' || /^0+(\.0+)?$/.test(curDesc);
                    const descIsOldProductName = row._prevProductName && curDesc === row._prevProductName;
                    // Text the search box copied in on blur ("Alm") is a search,
                    // not a note the user wrote: the picked item's name replaces it.
                    const descIsSearchText = row._typedDesc && curDesc === row._typedDesc;
                    if (descIsJunk || descIsOldProductName || descIsSearchText) {
                        row.description = p.name;
                    }
                    row._prevProductName = p.name; // remember for the next swap
                    row._typedDesc = null;
                    row.hsn_sac = p.hsn_sac;
                    row._stock = (p.stock ?? null);
                    row.unit = p.unit;
                    row.rate = parseFloat(p.rate) || 0;
                    row.gst_rate = parseFloat(p.gst_rate) || 0;
                    this.recompute();
                },
                recompute() {
                    const inter = this.isInterstate;
                    // Reverse charge, or zero-rated under a LUT: no tax collected.
                    const rcm = this.reverseCharge || this.supplyType.endsWith('_lut');
                    let sub = 0, cgst = 0, sgst = 0, igst = 0;

                    // Invoice-level discount, spread across the lines pro rata to
                    // their value before tax - the same split as
                    // InvoiceCalculator::apportionDiscount on the server.
                    // Round half up to the paisa like PHP's round(); toFixed(2)
                    // rounds 1.005 down, so the preview could differ by a paisa.
                    const r2 = v => Math.round((v + Number.EPSILON) * 100) / 100;
                    // Quantity and rate as they are stored (3 and 2 decimals).
                    const grossOf = item => r2(
                        (Math.round(((parseFloat(item.quantity) || 0) + Number.EPSILON) * 1000) / 1000)
                        * r2(parseFloat(item.rate) || 0)
                    );
                    const grossList = this.items.map(grossOf);
                    const totalGross = r2(grossList.reduce((a, b) => a + b, 0));
                    const input = Math.max(0, parseFloat(this.discountInput) || 0);
                    let discount = this.discountMode === 'percent'
                        ? r2(totalGross * Math.min(input, 100) / 100)
                        : r2(input);
                    discount = Math.min(discount, totalGross);
                    // Pro rata, then the rounding remainder paisa by paisa to the
                    // lines with room, largest first: the same as the server.
                    const shares = grossList.map(g => (discount > 0 && g > 0) ? Math.min(g, r2(discount * g / totalGross)) : 0);
                    let left = Math.round((discount - shares.reduce((a, b) => a + b, 0)) * 100);
                    const order = grossList.map((g, i) => i).sort((a, b) => grossList[b] - grossList[a]);
                    for (let guard = 0; left !== 0 && guard < 10000; guard++) {
                        let moved = false;
                        for (const i of order) {
                            if (left > 0 && shares[i] + 0.01 <= grossList[i] + 0.0001) { shares[i] = r2(shares[i] + 0.01); left--; moved = true; }
                            else if (left < 0 && shares[i] >= 0.01) { shares[i] = r2(shares[i] - 0.01); left++; moved = true; }
                            if (left === 0) break;
                        }
                        if (!moved) break;
                    }
                    this.items.forEach((item, i) => { item.discount = shares[i]; });
                    const allocated = r2(shares.reduce((a, b) => a + b, 0));

                    this.items.forEach(item => {
                        const gst = parseFloat(item.gst_rate) || 0;
                        const gross = grossOf(item);
                        const disc = Math.max(0, Math.min(parseFloat(item.discount) || 0, gross));
                        const amount = r2(gross - disc);
                        let c = 0, s = 0, ig = 0;
                        // Section 9(3)/9(4) RCM: supplier collects no tax; recipient self-assesses.
                        if (gst > 0 && !rcm) {
                            if (inter) {
                                ig = r2(amount * gst / 100);
                            } else {
                                // Each head at half the rate, rounded on its own
                                // (mirrors InvoiceCalculator; keeps CGST = SGST).
                                c = r2(amount * gst / 200);
                                s = c;
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
                        gross: totalGross,
                        discount: +allocated.toFixed(2),
                        subtotal: +sub.toFixed(2),
                        cgst: +cgst.toFixed(2),
                        sgst: +sgst.toFixed(2),
                        igst: +igst.toFixed(2),
                        totalTax,
                        grandTotal: grand,
                    };
                    const paid = parseFloat(this.$refs.paidInput?.value || 0) || 0;
                    // Balance never shows below zero on the form; an advance larger
                    // than the bill is flagged separately (paidExceedsTotal).
                    this.balance = +(grand - paid).toFixed(2);
                    this.paidExceedsTotal = paid > grand + 0.001;
                },
                fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }

        /**
         * Per-row product combobox component. Lives inside an x-data="invoiceForm(...)"
         * scope. Uses `this.$root` to call the parent's pickProduct() so existing GST
         * logic (rate/HSN/unit propagation + recompute) keeps working unchanged.
         *
         * Talks to two endpoints:
         *   - GET  /products/search?q=...     → typeahead results
         *   - POST /products/quick-create     → inline "+ Save as new product"
         *
         * Designed for Indian SMEs who often want "Cement bag - 500 - 18%" saved
         * in 5 seconds without leaving the invoice.
         */
        function productRowCombobox(idx, uid) {
            return {
                _idx0: idx,
                uid: uid ?? null,
                // The row's CURRENT position. The component lives as long as its
                // row (keyed by _uid), so after a row above is removed the index
                // it was created with is stale: find the row by its uid instead,
                // or picks and the product_id input land on the wrong line.
                get idx() {
                    const items = this.parent?.items;
                    if (this.uid && items) {
                        const i = items.findIndex(it => it._uid === this.uid);
                        if (i >= 0) return i;
                    }
                    return this._idx0;
                },
                combo: { search: '', open: false, highlight: 0, results: [], loading: false, lastFetch: null },
                quickAdd: { open: false, name: '', rate: '', gst_rate: 18, hsn_sac: '', unit: 'NOS', submitting: false, error: '', errors: {} },
                // Position of the teleported dropdown (recomputed on open, scroll, resize).
                // Why: the dropdown is teleported out to <body> to escape the table's
                // overflow-x-auto clip. Fixed positioning means we must track the
                // input's viewport coords ourselves.
                panel: { top: 0, left: 0, width: 0 },
                _reposition: null, // bound rAF handler so we can add/remove cleanly
                parent: null, // explicit ref to the parent invoiceForm Alpine scope

                init() {
                    // Alpine v3 caveat: nested x-data does NOT inherit parent
                    // scope into JS *methods* - inheritance only applies in
                    // directive expressions (x-text, x-show, etc.). So we grab
                    // an explicit reference to the parent invoiceForm scope on
                    // mount via Alpine.$data() walked up the DOM tree. Every
                    // cross-boundary call (this.parent.pickProduct, .items,
                    // .productMap) goes through this ref.
                    const outerEl = this.$el.parentElement?.closest('[x-data]');
                    this.parent = (outerEl && window.Alpine && typeof window.Alpine.$data === 'function')
                        ? window.Alpine.$data(outerEl)
                        : null;
                    if (!this.parent || typeof this.parent.pickProduct !== 'function') {
                        // Loud failure - if you see this in DevTools, the combobox can't
                        // reach the invoice form. Common cause: outer Alpine root was
                        // removed or restructured. Picks will be no-ops until fixed.
                        console.error('[invoice-form] productRowCombobox could not reach parent invoiceForm scope. Picks will not propagate to row data.', { idx: this.idx, outerEl });
                    }

                    // Seed the combobox input on edit/refresh so the row's history is
                    // visible at a glance. Two paths:
                    //   1. Linked to a saved product → show the product's name
                    //   2. Custom line (no product_id) but description has text → show that
                    //
                    // Path 2 is what makes "I typed Cement bag last week as a one-off"
                    // reappear on edit instead of looking like a blank field. We don't
                    // touch item.description here - display only.
                    this.$nextTick(() => {
                        if (!this.parent) return;
                        const item = this.parent.items?.[this.idx];
                        if (!item) return;
                        if (item.product_id && this.parent.productMap?.[item.product_id]) {
                            this.combo.search = this.parent.productMap[item.product_id].name;
                        } else if (item.description) {
                            // Custom-typed line item - mirror the saved description into
                            // the combobox text so the user sees what they originally entered.
                            this.combo.search = item.description;
                        }
                    });

                    // Reposition the floating panel when the page/table scrolls or the
                    // window resizes. We attach on open and detach on close to avoid
                    // running the listener for closed rows. Capture phase catches
                    // scrolls inside the table's overflow-x-auto wrapper.
                    this._reposition = () => requestAnimationFrame(() => this.updatePanelPosition());
                    this.$watch('combo.open', (open) => {
                        if (open) {
                            this.updatePanelPosition();
                            window.addEventListener('scroll', this._reposition, true);
                            window.addEventListener('resize', this._reposition);
                        } else {
                            window.removeEventListener('scroll', this._reposition, true);
                            window.removeEventListener('resize', this._reposition);
                        }
                    });
                },

                updatePanelPosition() {
                    const input = this.$refs.comboInput;
                    if (!input) return;
                    const r = input.getBoundingClientRect();
                    // 4px gap below the input. Width matches the input on desktop;
                    // on mobile the input itself is full-width so this still feels right.
                    this.panel.top = r.bottom + 4;
                    this.panel.left = r.left;
                    // Minimum width 280px so the inline "Add new" form has room to breathe
                    // on the narrow desktop "Product" column (w-48 = 192px).
                    this.panel.width = Math.max(r.width, 280);
                },

                openCombo() {
                    this.combo.open = true;
                    this.combo.highlight = 0;
                    // If the box is empty, show the most recent products so users
                    // who haven't typed yet still get a list to click.
                    if (!this.combo.search.trim() && this.combo.results.length === 0) {
                        this.fetchResults('');
                    }
                },

                onInput() {
                    this.combo.open = true;
                    this.combo.highlight = 0;
                    this.quickAdd.open = false;
                    // If the user is editing and the search text no longer matches
                    // the selected product, drop the selection - they're choosing again.
                    const item = this.parent?.items?.[this.idx];
                    if (item && item.product_id) {
                        const cur = this.parent.productMap?.[item.product_id];
                        if (cur && cur.name !== this.combo.search) {
                            item.product_id = null;
                        }
                    }
                    this.fetchResults(this.combo.search.trim());
                },

                async fetchResults(q) {
                    // Token-based race-condition guard - if a newer fetch starts before
                    // this one finishes, the old result is discarded on arrival.
                    const token = Symbol('fetch');
                    this.combo.lastFetch = token;
                    this.combo.loading = true;
                    try {
                        const url = new URL('{{ route("products.search") }}', window.location.origin);
                        if (q) url.searchParams.set('q', q);
                        const res = await fetch(url.toString(), {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (this.combo.lastFetch !== token) return;
                        if (res.ok) {
                            const data = await res.json();
                            this.combo.results = data || [];
                            // Cache results into the parent invoiceForm's productMap
                            // so pickProduct() can find them by id when the user clicks.
                            if (this.parent?.productMap) {
                                data.forEach(p => { this.parent.productMap[p.id] = p; });
                            }
                        }
                    } catch (e) {
                        // Silent fail - combobox just shows no matches.
                    } finally {
                        if (this.combo.lastFetch === token) this.combo.loading = false;
                    }
                },

                moveHighlight(direction) {
                    this.combo.open = true;
                    const len = this.combo.results.length;
                    if (len === 0) return;
                    this.combo.highlight = (this.combo.highlight + direction + len) % len;
                },

                enterKey() {
                    if (this.quickAdd.open) {
                        this.submitQuickAdd();
                        return;
                    }
                    const list = this.combo.results;
                    if (list.length > 0 && this.combo.highlight >= 0 && this.combo.highlight < list.length) {
                        this.pick(list[this.combo.highlight]);
                    } else if (this.combo.search.trim()) {
                        // No matches - open quick-add panel with typed text as the name.
                        this.openQuickAdd();
                    }
                },

                pick(p) {
                    this.combo.search = p.name;
                    this.combo.open = false;
                    this.quickAdd.open = false;
                    // Delegate to parent's pickProduct() so all the existing logic
                    // (description, HSN, unit, rate, GST, recompute) runs exactly
                    // as before. We use the explicit parent ref captured in init().
                    if (this.parent && typeof this.parent.pickProduct === 'function') {
                        this.parent.pickProduct(this.idx, p.id);
                    }
                },

                clearRow() {
                    this.combo.search = '';
                    this.combo.results = [];
                    this.combo.open = false;
                    this.quickAdd.open = false;
                    const item = this.parent?.items?.[this.idx];
                    const hadProduct = !! item?.product_id;
                    if (this.parent && typeof this.parent.pickProduct === 'function') {
                        this.parent.pickProduct(this.idx, ''); // empty string → clears product_id, keeps row as custom
                    }
                    // Only wipe the description when we WERE clearing a linked
                    // product (so the lingering product-name text gets removed).
                    // For custom-typed rows the description IS the row's data -
                    // clearing it on × would silently destroy what the user typed.
                    // Clearing a linked item also clears what it filled in, so
                    // the next pick or a typed line never carries its HSN and rate.
                    if (item && hadProduct) {
                        item.description = '';
                        item.hsn_sac = '';
                        item.rate = 0;
                        item._stock = null;
                        this.parent?.recompute?.();
                    }
                },

                /**
                 * Mirror typed-but-not-picked text into the line description.
                 *
                 * Why this exists: a user can type "Cement bag" into the product
                 * combobox without ever clicking "+ Save as new product" or picking
                 * an existing match. Without this method, the typed text only lives
                 * in `combo.search` (combobox-local state) and is LOST on submit -
                 * the saved invoice ends up with a blank description column.
                 *
                 * Strategy: on blur / click-outside, if the user typed something
                 * but didn't actually pick a product AND the description field is
                 * still blank (or just the legacy "0" junk), copy the typed text
                 * into description. They still get a clean line on the PDF.
                 *
                 * We never overwrite a real description the user typed manually.
                 */
                commitTypedText() {
                    const item = this.parent?.items?.[this.idx];
                    if (!item) return;
                    const typed = (this.combo.search || '').toString().trim();
                    const desc = (item.description || '').toString().trim();
                    const descIsJunk = desc === '' || /^0+(\.0+)?$/.test(desc);
                    if (!item.product_id && typed && descIsJunk) {
                        item.description = typed;
                        item._typedDesc = typed;
                        if (typeof this.parent.recompute === 'function') {
                            this.parent.recompute();
                        }
                    }
                },

                openQuickAdd() {
                    // Seed the form with the search text + any guesses from the current
                    // invoice row. Pre-filling row's rate/gst_rate/hsn lets users save
                    // a new product by simply typing the name - fields are already filled.
                    const item = this.parent?.items?.[this.idx];
                    this.quickAdd.name = this.combo.search.trim();
                    this.quickAdd.rate = item && parseFloat(item.rate) > 0 ? item.rate : '';
                    this.quickAdd.gst_rate = item?.gst_rate ?? 18;
                    this.quickAdd.hsn_sac = item?.hsn_sac || '';
                    this.quickAdd.unit = item?.unit || 'NOS';
                    this.quickAdd.error = '';
                    this.quickAdd.errors = {};
                    this.quickAdd.open = true;
                },

                async submitQuickAdd() {
                    if (this.quickAdd.submitting) return;
                    this.quickAdd.submitting = true;
                    this.quickAdd.error = '';
                    this.quickAdd.errors = {};
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const res = await fetch('{{ route("products.quick-create") }}', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                name: this.quickAdd.name,
                                rate: this.quickAdd.rate,
                                gst_rate: this.quickAdd.gst_rate,
                                hsn_sac: this.quickAdd.hsn_sac || null,
                                unit: this.quickAdd.unit || null,
                            }),
                        });
                        if (res.status === 201) {
                            const p = await res.json();
                            // Cache so pickProduct() finds it. Use the parent's productMap
                            // (captured in init()) - Alpine v3 nested scope doesn't merge
                            // into JS method context.
                            if (this.parent?.productMap) {
                                this.parent.productMap[p.id] = p;
                            }
                            this.pick(p);
                            return;
                        }
                        if (res.status === 422) {
                            const body = await res.json();
                            const errs = body.errors || {};
                            const firstErrors = {};
                            for (const [field, messages] of Object.entries(errs)) {
                                firstErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
                            }
                            this.quickAdd.errors = firstErrors;
                            return;
                        }
                        this.quickAdd.error = 'Could not save (status ' + res.status + '). Try again.';
                    } catch (e) {
                        this.quickAdd.error = 'Network error. Check your connection and try again.';
                    } finally {
                        this.quickAdd.submitting = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
