<x-app-layout>
    @php $restricted = $restricted ?? false; @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $invoice->exists ? 'Edit ' . $invoice->displayNumber() : 'New invoice' }}
                @if ($restricted)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 uppercase font-bold tracking-wider">Limited edit</span>
                @elseif (! $invoice->exists && ! empty($templateLabel))
                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-brand-50 text-brand-700 font-medium">Using template: {{ $templateLabel }}</span>
                @endif
            </h2>
            @unless ($invoice->exists || $restricted)
                <a href="{{ route('invoices.templates') }}" class="text-sm text-brand-700 hover:text-brand-800 hover:underline self-start sm:self-auto inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v2H4V6zM4 10h16v8a2 2 0 01-2 2H6a2 2 0 01-2-2v-8z"/></svg>
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
            $oldItems = [['product_id' => null, 'description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'discount' => 0, 'gst_rate' => 18]];
        }
        $customerStateMap = $customers->mapWithKeys(fn ($c) => [$c->id => $c->state_id])->toJson();
        // Map customer id → bool "has GSTIN", used for the B2C > ₹2.5L warning.
        $customerHasGstinMap = $customers->mapWithKeys(fn ($c) => [$c->id => ! empty($c->gstin)])->toJson();
        $companyStateId = $company->state_id;
        // Lean customer index for the typeahead combobox — searchable on name,
        // phone, GSTIN, city, and state name (matches how Indian SMEs look up
        // customers — by phone or trade name, not by formal database lookup).
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
        // referenced by the current invoice rows — needed for the search box to
        // display "selected" names in edit mode. Everything else is fetched
        // on-demand via /products/search. Big speedup for shops with 1000+ items.
        $itemProductIds = collect($oldItems)->pluck('product_id')->filter()->unique()->values();
        $productIndex = $itemProductIds->isEmpty()
            ? collect([])
            : $company->products()
                ->whereIn('id', $itemProductIds)
                ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate']);
    @endphp

    <div class="py-10" x-data='invoiceForm(@json($oldItems), {{ $customerStateMap }}, {{ $companyStateId ?? 'null' }}, @json($productIndex), {{ $customerHasGstinMap }}, @json($customerIndex), {{ ! empty($company->gstin) ? 'true' : 'false' }})'
         @customer-added.window="addCustomer($event.detail)"
         {{-- Tally-style keyboard shortcuts. Listen at window level so they
              fire from anywhere on the page (combobox dropdowns, side panels
              etc.). The handler inspects $event.target so we don't hijack
              Ctrl+S while the user is typing in a textarea. --}}
         @keydown.window="onShortcut($event)">
        {{-- Wider container for the invoice form than the rest of the app —
             line-item table needs ~1200px to fit Product/Desc/HSN/Qty/Rate/Disc/GST/Amount
             without horizontal scroll on standard laptop widths. --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                        <div class="font-semibold">This invoice is issued — limited editing only.</div>
                        <div class="mt-0.5">You can update <strong>notes, terms, due date, and transporter details</strong>. Amounts, line items, customer, and invoice number are legally locked per GST rules. To change those, cancel this invoice and issue a credit note / revised invoice.</div>
                    </div>
                </div>
            @endif

            {{-- First-time user tip: shown only on a brand-new invoice (no invoice exists yet).
                 Dismissible via localStorage so it doesn't nag returning users. --}}
            @unless ($invoice->exists)
                <div x-data="{ show: !localStorage.getItem('hideFirstInvoiceTip') }" x-show="show" x-cloak
                     class="p-4 bg-brand-50 border border-brand-200 text-brand-900 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm flex-1">
                        <div class="font-semibold">New to invoicing? Here's all you need to do:</div>
                        <ol class="mt-1 list-decimal pl-5 space-y-0.5">
                            <li>Pick a <strong>customer</strong> (or click <em>+ New</em> to add one).</li>
                            <li>Fill <strong>at least one line item</strong> — description, HSN/SAC, qty, rate, GST%.</li>
                            <li>Click <strong>Create draft</strong> — it stays editable. <strong>Issue</strong> locks the invoice number and makes the PDF.</li>
                        </ol>
                        <div class="mt-1.5 text-xs text-brand-700">Transporter, e-way bill and ship-to fields are optional — open them only if needed.</div>
                    </div>
                    <button type="button" @click="localStorage.setItem('hideFirstInvoiceTip','1'); show=false" class="shrink-0 inline-flex items-center justify-center w-10 h-10 -m-2 text-brand-500 hover:text-brand-800 hover:bg-brand-100 rounded-lg text-2xl leading-none" aria-label="Dismiss tip">×</button>
                </div>
            @endunless

            {{-- Soft nudge when the user has zero customers. The form is still rendered;
                 the customer picker is empty, but the "+ New" button opens the inline
                 modal so the user never has to leave this page. --}}
            @if ($customers->isEmpty() && ! $restricted)
                <div class="p-4 bg-saffron-50 border border-saffron-200 text-saffron-900 rounded-lg flex flex-col sm:flex-row sm:items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-saffron-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <div class="flex-1 text-sm">
                        <span class="font-semibold">No customers yet.</span>
                        Click <strong>+ New</strong> next to the Customer field below — takes 10 seconds, no need to leave this page.
                    </div>
                    <button type="button" @click="$dispatch('open-quick-customer')" class="inline-flex items-center justify-center px-3 py-1.5 bg-saffron-600 hover:bg-saffron-700 text-white font-semibold rounded-md text-sm whitespace-nowrap">
                        + Add customer
                    </button>
                </div>
            @endif

            {{-- Soft nudge when the company has no state set. GST place-of-supply needs it
                 to compute CGST/SGST vs IGST correctly. Without it we fall back to IGST. --}}
            @if (! $company->state_id && ! $restricted)
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg flex flex-col sm:flex-row sm:items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/>
                    </svg>
                    <div class="flex-1 text-sm">
                        <span class="font-semibold">Your business state is not set.</span>
                        Until you set it, GST is computed as IGST (inter-state) by default. Set state to enable CGST/SGST for same-state customers.
                    </div>
                    <a href="{{ route('company.edit') }}" class="inline-flex items-center justify-center px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-md text-sm whitespace-nowrap">
                        Set business state
                    </a>
                </div>
            @endif

            <form id="invoice-form" method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6">
                @csrf
                @if ($invoice->exists) @method('PATCH') @endif

                <div class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="customer_search" value="Customer *" />
                            {{-- When the invoice is restricted (issued), show the locked customer as a plain
                                 read-only field. The combobox below is only used in editable mode. --}}
                            @if ($restricted)
                                @php $lockedCustomer = $customers->firstWhere('id', $invoice->customer_id); @endphp
                                <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                                <div class="mt-1 px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800">
                                    {{ $lockedCustomer?->name }}{{ $lockedCustomer?->state?->name ? ' — ' . $lockedCustomer->state->name : '' }}
                                </div>
                            @else
                                {{-- Combobox: type-to-search across name/phone/GSTIN/city/state.
                                     Sticky "+ Use new customer" option opens the quick-add modal with the
                                     typed text pre-filled. Keyboard nav: ↑↓ to walk, Enter to select,
                                     Esc to close. The hidden input submits customer_id to the server. --}}
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
                                                   placeholder="Type customer name, phone, or GSTIN…"
                                                   autocomplete="off"
                                                   class="block w-full border-gray-300 rounded-md shadow-sm pr-10">
                                            {{-- Clear button — only visible when a customer is selected. Lets
                                                 the user re-type without using the keyboard. --}}
                                            <button type="button"
                                                    x-show="customerId"
                                                    @click="clearCustomer()"
                                                    class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-700"
                                                    aria-label="Clear customer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            {{-- Dropdown --}}
                                            <div x-show="customerCombo.open" x-cloak
                                                 class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-72 overflow-y-auto">
                                                <template x-if="customerComboFiltered.length === 0 && !customerCombo.search.trim()">
                                                    <div class="px-3 py-2 text-sm text-gray-500">Start typing to find a customer…</div>
                                                </template>
                                                <template x-for="(c, idx) in customerComboFiltered" :key="c.id">
                                                    <button type="button"
                                                            @click="selectCustomer(c)"
                                                            @mouseenter="customerCombo.highlight = idx"
                                                            class="block w-full text-left px-3 py-2 text-sm hover:bg-brand-50 transition"
                                                            :class="customerCombo.highlight === idx ? 'bg-brand-50' : ''">
                                                        <div class="font-medium text-gray-900" x-text="c.name"></div>
                                                        <div class="text-xs text-gray-500">
                                                            <span x-text="c.state_name || 'No state set'"></span>
                                                            <template x-if="c.phone"><span> · <span x-text="c.phone"></span></span></template>
                                                            <template x-if="c.gstin"><span> · GSTIN <span class="font-mono" x-text="c.gstin"></span></span></template>
                                                        </div>
                                                    </button>
                                                </template>
                                                {{-- Sticky "Add new" footer — always shows when search has text --}}
                                                <button type="button"
                                                        x-show="customerCombo.search.trim()"
                                                        @click="quickAddCustomer(customerCombo.search.trim())"
                                                        class="block w-full text-left px-3 py-2 text-sm bg-saffron-50 hover:bg-saffron-100 text-saffron-900 font-semibold border-t border-saffron-200 sticky bottom-0">
                                                    + Use new customer: "<span x-text="customerCombo.search.trim()"></span>"
                                                </button>
                                            </div>
                                        </div>
                                        <button type="button" @click="quickAddCustomer('')"
                                                class="inline-flex items-center justify-center min-h-[40px] px-3 text-brand-700 hover:text-white hover:bg-brand-600 ring-1 ring-brand-200 rounded-md text-sm font-semibold whitespace-nowrap"
                                                title="Add a new customer without leaving this page">+ New</button>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500" x-show="customerId && selectedCustomerLabel">
                                        <span class="text-emerald-700 font-medium">✓ Selected:</span>
                                        <span x-text="selectedCustomerLabel"></span>
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div>
                            <x-input-label value="Invoice no." />
                            <div class="mt-1 py-2 font-mono text-sm">
                                @if ($invoice->exists && ! $invoice->isDraft())
                                    <span class="text-gray-900 font-semibold">{{ $invoice->invoice_number }}</span>
                                @else
                                    <span class="text-brand-700 font-semibold">{{ $previewNumber ?? $invoice->company?->nextInvoiceNumber() }}</span>
                                    <span class="block text-[10px] text-gray-500 uppercase tracking-wider font-sans">Auto-assigned when issued</span>
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
                            <label class="mt-3 flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="reverse_charge" value="1" x-model="reverseCharge" @change="recompute()"
                                       class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                                <span>
                                    <span class="font-medium">Reverse charge applicable</span>
                                    <span class="block text-xs text-gray-500">Section 9(3)/9(4) — recipient pays GST. CGST/SGST/IGST will be set to ₹0 on the invoice; the recipient self-assesses.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden {{ $restricted ? 'opacity-70' : '' }}">
                    <div class="px-6 py-4 border-b flex items-center justify-between gap-3 flex-wrap">
                        <h3 class="font-medium text-gray-900">Line items @if ($restricted)<span class="ml-2 text-xs text-amber-700 font-normal">🔒 Locked — amounts are immutable</span>@endif</h3>
                        <div class="flex items-center gap-3">
                            @if (! $restricted)
                                <span class="hidden md:inline text-xs text-gray-500">Tip: type a product name in the row — pick existing or save a new one inline.</span>
                                <button type="button" @click="addRow" class="inline-flex items-center justify-center min-h-[40px] px-3 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded-md text-sm font-semibold">+ Add row</button>
                            @endif
                        </div>
                    </div>
                    <fieldset @disabled($restricted) class="{{ $restricted ? 'pointer-events-none' : '' }}">

                    {{-- Mobile + tablet: stacked cards (one per row). Table view from lg up
                         (≥1024px) so iPads and small laptops still get the proper desktop
                         table, which fits comfortably from ~1024px wide. --}}
                    <div class="lg:hidden divide-y divide-gray-100">
                        {{-- Key by stable _uid (not idx) so Alpine doesn't reuse the
                             same DOM node — and the nested productRowCombobox's
                             captured idx — for a different logical row after a splice. --}}
                        <template x-for="(item, idx) in items" :key="item._uid">
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase font-bold tracking-wider text-gray-500">Item <span x-text="idx + 1"></span></span>
                                    @if (! $restricted)
                                        <button type="button" @click="removeRow(idx)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-red-600'"
                                                class="text-sm font-medium transition"
                                                aria-label="Remove row">Remove</button>
                                    @endif
                                </div>
                                @unless ($restricted)
                                    <div x-data="productRowCombobox(idx)" class="relative" @click.outside="combo.open = false; quickAdd.open = false; commitTypedText()">
                                        <label class="text-xs text-gray-500 font-semibold">Product (or type new)</label>
                                        <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                        <div class="relative">
                                            <input type="text"
                                                   x-ref="comboInput"
                                                   x-model="combo.search"
                                                   @focus="openCombo()"
                                                   @input.debounce.250ms="onInput()"
                                                   @keydown.arrow-down.prevent="moveHighlight(1)"
                                                   @keydown.arrow-up.prevent="moveHighlight(-1)"
                                                   @keydown.enter.prevent="enterKey()"
                                                   @keydown.escape="combo.open = false; quickAdd.open = false"
                                                   @blur="commitTypedText()"
                                                   placeholder="Type product name, SKU, or HSN…"
                                                   autocomplete="off"
                                                   class="mt-1 block w-full border-gray-300 rounded text-sm pr-8">
                                            <button type="button" x-show="item.product_id || combo.search" @click="clearRow()"
                                                    class="absolute inset-y-0 right-0 top-1 px-2 text-gray-400 hover:text-gray-700" aria-label="Clear product">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        @include('invoices._product-combobox-panel', ['mobile' => true])
                                    </div>
                                @endunless
                                <div>
                                    <label class="text-xs text-gray-500 font-semibold">Description <span class="text-gray-400 font-normal">(max 150)</span></label>
                                    {{-- Description is optional. If left blank and a product is picked,
                                         the server backfills it from the product's name. The user can also
                                         leave it blank entirely for a one-line custom charge. --}}
                                    <input :name="`items[${idx}][description]`" x-model="item.description" maxlength="150" placeholder="Optional — auto-fills from product if picked" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5">
                                            <label class="text-xs text-gray-500 font-semibold">
                                                HSN/SAC<span x-show="!hsnRequired" class="font-normal text-gray-400"> (optional)</span>
                                            </label>
                                            <a href="https://services.gst.gov.in/services/searchhsnsac"
                                               target="_blank" rel="noopener"
                                               onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
                                               class="text-brand-600 hover:text-brand-700"
                                               aria-label="Search HSN/SAC code on the official GST portal"
                                               title="Search HSN/SAC code on the official GST portal">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </a>
                                        </div>
                                        <input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" inputmode="numeric" maxlength="8" placeholder="e.g. 998314" class="mt-1 block w-full border-gray-300 rounded text-sm font-mono" :required="hsnRequired">
                                        <p class="mt-1 text-[10px]" :class="item.hsn_sac && item.hsn_sac.length > 0 && item.hsn_sac.length < 4 ? 'text-amber-700 font-semibold' : 'text-gray-400'">
                                            <template x-if="!item.hsn_sac || item.hsn_sac.length === 0">
                                                <span x-text="hsnRequired
                                                    ? 'Required. 4 digits if turnover < ₹5 Cr · 6 if > ₹5 Cr · 8 for exports.'
                                                    : 'Optional — neither you nor the customer is GST-registered.'"></span>
                                            </template>
                                            <template x-if="item.hsn_sac && item.hsn_sac.length > 0 && item.hsn_sac.length < 4">
                                                <span>⚠ HSN should be at least 4 digits per Rule 46(g).</span>
                                            </template>
                                            <template x-if="item.hsn_sac && item.hsn_sac.length >= 4">
                                                <span class="text-emerald-700">✓ <span x-text="item.hsn_sac.length"></span>-digit HSN — valid format.</span>
                                            </template>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Unit</label>
                                        <input :name="`items[${idx}][unit]`" x-model="item.unit" class="mt-1 block w-full border-gray-300 rounded text-sm" placeholder="NOS, KGS, HRS…">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Quantity</label>
                                        <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="1" min="1" inputmode="numeric" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Rate (₹)</label>
                                        <input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-semibold">Discount (₹)</label>
                                        <input :name="`items[${idx}][discount]`" x-model.number="item.discount" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="mt-1 block w-full border-gray-300 rounded text-sm text-right" placeholder="0.00">
                                        <p class="mt-0.5 text-[10px] text-gray-400">Section 15(3) — pre-tax</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-semibold">GST rate</label>
                                    {{-- Short labels (just "18%") so the cell stays compact.
                                         Full descriptive text and the regulatory note are kept
                                         in the option's title for tooltip-on-hover. --}}
                                    <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        @foreach (config('gst.rates') as $r)
                                            <option value="{{ $r['value'] }}" title="{{ $r['label'] . ' — ' . $r['note'] }}">{{ (float) $r['value'] }}%</option>
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
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    @unless ($restricted)
                                        <th class="px-3 py-2 text-left">Product</th>
                                    @endunless
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span>HSN/SAC</span>
                                            <a href="https://services.gst.gov.in/services/searchhsnsac"
                                               target="_blank" rel="noopener"
                                               onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
                                               class="text-brand-600 hover:text-brand-700"
                                               aria-label="Search HSN/SAC code on the official GST portal"
                                               title="Search HSN/SAC code on the official GST portal">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </a>
                                        </span>
                                    </th>
                                    <th class="px-3 py-2 text-left">Quantity</th>
                                    <th class="px-3 py-2 text-right">Rate (₹)</th>
                                    <th class="px-3 py-2 text-right" title="Pre-tax discount per Section 15(3) CGST">Disc (₹)</th>
                                    <th class="px-3 py-2 text-right">GST%</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Stable _uid keying — see mobile section above. --}}
                                <template x-for="(item, idx) in items" :key="`d-${item._uid}`">
                                    <tr class="border-t">
                                        @unless ($restricted)
                                            <td class="px-2 py-2 relative">
                                                <div x-data="productRowCombobox(idx)" class="relative w-48" @click.outside="combo.open = false; quickAdd.open = false; commitTypedText()">
                                                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
                                                    <div class="relative">
                                                        <input type="text"
                                                               x-ref="comboInput"
                                                               x-model="combo.search"
                                                               @focus="openCombo()"
                                                               @input.debounce.250ms="onInput()"
                                                               @keydown.arrow-down.prevent="moveHighlight(1)"
                                                               @keydown.arrow-up.prevent="moveHighlight(-1)"
                                                               @keydown.enter.prevent="enterKey()"
                                                               @keydown.escape="combo.open = false; quickAdd.open = false"
                                                               @blur="commitTypedText()"
                                                               placeholder="Type product or + new"
                                                               autocomplete="off"
                                                               class="w-full border-gray-300 rounded text-sm pr-7">
                                                        <button type="button" x-show="item.product_id || combo.search" @click="clearRow()"
                                                                class="absolute inset-y-0 right-0 px-2 text-gray-400 hover:text-gray-700" aria-label="Clear product">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                    @include('invoices._product-combobox-panel', ['mobile' => false])
                                                </div>
                                            </td>
                                        @endunless
                                        <td class="px-2 py-2"><input :name="`items[${idx}][description]`" x-model="item.description" maxlength="150" placeholder="Optional — auto-fills from product" class="w-full border-gray-300 rounded text-sm"></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][hsn_sac]`" x-model="item.hsn_sac" inputmode="numeric" maxlength="8" :placeholder="hsnRequired ? '998314' : 'Optional'" class="w-28 border-gray-300 rounded text-sm font-mono" :required="hsnRequired"></td>
                                        <td class="px-2 py-2">
                                            <div class="flex items-center gap-1">
                                                <input :name="`items[${idx}][quantity]`" x-model.number="item.quantity" @input="recompute()" type="number" step="1" min="1" inputmode="numeric" class="w-20 border-gray-300 rounded text-sm text-right" required>
                                                <input :name="`items[${idx}][unit]`" x-model="item.unit" class="w-20 border-gray-300 rounded text-sm" placeholder="unit">
                                            </div>
                                        </td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][rate]`" x-model.number="item.rate" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" class="w-28 border-gray-300 rounded text-sm text-right" required></td>
                                        <td class="px-2 py-2"><input :name="`items[${idx}][discount]`" x-model.number="item.discount" @input="recompute()" type="number" step="any" min="0" inputmode="decimal" placeholder="0.00" class="w-24 border-gray-300 rounded text-sm text-right"></td>
                                        <td class="px-2 py-2">
                                            {{-- Compact GST cell — just "18%". Full descriptive text + regulatory
                                                 note are in the option's `title` so a hover still tells the user
                                                 which slab they're picking. Was w-36 (144px) with long labels
                                                 like "18% · Standard rate" causing the table to overflow the
                                                 container at common laptop widths. --}}
                                            <select :name="`items[${idx}][gst_rate]`" x-model.number="item.gst_rate" @change="recompute()" class="w-20 border-gray-300 rounded text-sm">
                                                @foreach (config('gst.rates') as $r)
                                                    <option value="{{ $r['value'] }}" title="{{ $r['label'] . ' — ' . $r['note'] }}">{{ (float) $r['value'] }}%</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-sm font-medium" x-text="fmt(item.amount)"></td>
                                        <td class="px-2 py-2 text-right w-14">
                                            @if (! $restricted)
                                                {{-- Always render so the column doesn't collapse; disabled
                                                     when removing it would leave zero line items. --}}
                                                <button type="button" @click="removeRow(idx)"
                                                        :disabled="items.length <= 1"
                                                        :title="items.length <= 1 ? 'At least one line item is required' : 'Remove this row'"
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md ring-1 transition"
                                                        :class="items.length <= 1
                                                            ? 'text-gray-300 ring-gray-200 bg-gray-50 cursor-not-allowed'
                                                            : 'text-red-600 ring-red-200 bg-red-50 hover:text-white hover:bg-red-600 hover:ring-red-600'"
                                                        aria-label="Remove row">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            @endif
                                        </td>
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

                    @php
                        $shipToPrefilled = old('ship_to_name', $invoice->ship_to_name)
                            || old('ship_to_address_line1', $invoice->ship_to_address_line1)
                            || old('ship_to_city', $invoice->ship_to_city);
                    @endphp
                    <div class="border-t bg-gray-50" x-data="{ open: {{ $shipToPrefilled ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-100 transition"
                                :aria-expanded="open">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Ship to a different address?</div>
                                    <div class="text-xs text-gray-500">For goods delivered to a site that isn't the customer's billing address (warehouse, branch, project site…).</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak class="px-6 pb-6 pt-2 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <x-input-label for="ship_to_name" value="Consignee name" />
                                    <x-text-input id="ship_to_name" name="ship_to_name" type="text" class="mt-1 block w-full" :value="old('ship_to_name', $invoice->ship_to_name)" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="ship_to_address_line1" value="Delivery address line 1" />
                                    <x-text-input id="ship_to_address_line1" name="ship_to_address_line1" type="text" class="mt-1 block w-full" :value="old('ship_to_address_line1', $invoice->ship_to_address_line1)" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="ship_to_address_line2" value="Delivery address line 2" />
                                    <x-text-input id="ship_to_address_line2" name="ship_to_address_line2" type="text" class="mt-1 block w-full" :value="old('ship_to_address_line2', $invoice->ship_to_address_line2)" />
                                </div>
                                <div>
                                    <x-input-label for="ship_to_city" value="City" />
                                    <x-text-input id="ship_to_city" name="ship_to_city" type="text" class="mt-1 block w-full" :value="old('ship_to_city', $invoice->ship_to_city)" />
                                </div>
                                <div>
                                    <x-input-label for="ship_to_state_id" value="State" />
                                    <select id="ship_to_state_id" name="ship_to_state_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">— Select —</option>
                                        @foreach ($states as $s)
                                            <option value="{{ $s->id }}" @selected(old('ship_to_state_id', $invoice->ship_to_state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="ship_to_postal_code" value="PIN" />
                                    <x-text-input id="ship_to_postal_code" name="ship_to_postal_code" type="text" inputmode="numeric" class="mt-1 block w-full" :value="old('ship_to_postal_code', $invoice->ship_to_postal_code)" />
                                </div>
                                <div>
                                    <x-input-label for="ship_to_gstin" value="Consignee GSTIN (optional)" />
                                    <x-text-input id="ship_to_gstin" name="ship_to_gstin" type="text" class="mt-1 block w-full uppercase font-mono" maxlength="15" :value="old('ship_to_gstin', $invoice->ship_to_gstin)" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                If the consignee's state differs from the Bill-to state, GST is still determined by the <strong>place of supply</strong>
                                (the customer's state on record), not the ship-to state. This is correct per the IGST Act — use the Bill-to state for tax mode.
                            </p>
                        </div>
                    </div>

                    {{-- B2C > ₹2.5L warning (Rule 46(e) requires recipient name/address/state) --}}
                    <div x-show="showB2cWarning" x-cloak class="border-t bg-amber-50 px-6 py-3">
                        <div class="flex items-start gap-2 text-sm text-amber-900">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                            <div>
                                <div class="font-semibold">B2C invoice over ₹2.5 lakh — Rule 46(e) applies</div>
                                <div class="mt-0.5">The customer has no GSTIN and the invoice total exceeds ₹2,50,000. Per CGST Rule 46(e), you must include the recipient's <strong>name, full delivery address, and state</strong>. Double-check the customer record has those filled in.</div>
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
                            <div x-show="reverseCharge" class="p-2 rounded bg-amber-50 border border-amber-200 text-xs text-amber-900">
                                🛈 <strong>Reverse charge:</strong> CGST/SGST/IGST set to ₹0 on this invoice — recipient pays GST directly (Section 9(3)/9(4)).
                            </div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>CGST</span><span class="font-mono" x-text="fmt(totals.cgst)"></span></div>
                            <div class="flex justify-between" x-show="!isInterstate"><span>SGST</span><span class="font-mono" x-text="fmt(totals.sgst)"></span></div>
                            <div class="flex justify-between" x-show="isInterstate"><span>IGST</span><span class="font-mono" x-text="fmt(totals.igst)"></span></div>
                            <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono" x-text="fmt(totals.grandTotal)"></span></div>
                            <div class="flex justify-between items-center">
                                <label for="paid_amount">Paid amount</label>
                                <input id="paid_amount" name="paid_amount" type="number" step="any" min="0" value="{{ old('paid_amount', $invoice->paid_amount ?? 0) }}" x-ref="paidInput" @input="recompute()" class="w-32 border-gray-300 rounded text-sm text-right">
                            </div>
                            <div class="flex justify-between border-t pt-2"><span>Balance</span><span class="font-mono" x-text="fmt(balance)"></span></div>
                        </div>
                    </div>
                </div>

                {{-- Hidden field set by the sticky bottom-bar buttons so the controller
                     knows whether to redirect to the show page or auto-trigger PDF. --}}
                <input type="hidden" name="post_save_action" x-ref="postSaveAction" value="">

                {{-- Legacy bottom action row — kept for mobile (where the sticky bar is hidden)
                     and as a fallback in case sticky positioning is unsupported. --}}
                <div class="flex items-center justify-between lg:hidden">
                    <a href="{{ route('invoices.index') }}" class="text-gray-500 hover:underline">← Cancel</a>
                    {{-- Same submitForm() routing as the desktop sticky bar so
                         pending blur handlers flush before the form posts. --}}
                    <button type="button" @click="submitForm('')"
                            class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        {{ $restricted ? 'Save changes' : ($invoice->exists ? 'Save draft' : 'Create draft') }}
                    </button>
                </div>
            </form>

            {{-- Sticky bottom totals + save bar. Desktop only — gives users the
                 Grand Total + primary action visible at all times no matter how
                 far down the form they've scrolled. On mobile we already show
                 totals inline + the FAB. --}}
            @unless ($restricted)
            <div class="hidden lg:block fixed inset-x-0 bottom-0 z-20 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
                {{-- Tally-style keyboard shortcut strip — kept subtle so it doesn't
                     compete with the action buttons, but always visible for power
                     users (the #1 Vyapar user complaint is missing shortcuts). --}}
                <div class="max-w-7xl mx-auto px-6 py-1 text-[11px] text-gray-500 border-b border-gray-100 flex items-center gap-x-4 gap-y-0.5 flex-wrap">
                    <span class="font-semibold text-gray-700 uppercase tracking-wider text-[10px]">Shortcuts</span>
                    <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono text-[10px]">F2</kbd> add row</span>
                    <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono text-[10px]">Ctrl+S</kbd> save draft</span>
                    <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono text-[10px]">F9</kbd> save &amp; download PDF</span>
                    <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono text-[10px]">Esc</kbd> close dropdown</span>
                </div>
                <div class="max-w-7xl mx-auto px-6 py-3 flex items-center gap-4">
                    <div class="text-xs text-gray-500">
                        Subtotal <span class="font-mono text-gray-900" x-text="'₹ ' + fmt(totals.subtotal)"></span>
                        <span class="mx-1 text-gray-300">·</span>
                        <span x-show="!isInterstate">CGST <span class="font-mono text-gray-900" x-text="fmt(totals.cgst)"></span> · SGST <span class="font-mono text-gray-900" x-text="fmt(totals.sgst)"></span></span>
                        <span x-show="isInterstate">IGST <span class="font-mono text-gray-900" x-text="'₹ ' + fmt(totals.igst)"></span></span>
                    </div>
                    <div class="flex-1"></div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase font-bold tracking-wider text-gray-500">Grand total</div>
                        <div class="font-display text-2xl font-extrabold text-gray-900" x-text="'₹ ' + fmt(totals.grandTotal)"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- type="button" + @click="submitForm(...)" — routes through the
                             central submit so document.activeElement.blur() fires and any
                             pending @blur handlers (combobox commitTypedText etc.) flush
                             before the form posts. Was previously type="submit" with the
                             native form-submit racing the blur — pending typed combobox
                             text could be lost on click. --}}
                        <button type="button"
                                @click="submitForm('')"
                                class="px-4 py-2.5 ring-1 ring-gray-300 text-gray-700 hover:bg-gray-50 font-semibold rounded-lg text-sm transition">
                            {{ $invoice->exists ? 'Save draft' : 'Save as draft' }}
                        </button>
                        <button type="button"
                                @click="submitForm('pdf')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-saffron-600 hover:bg-saffron-700 text-white font-semibold rounded-lg text-sm shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h11l5 5v7a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            Save &amp; Download PDF
                        </button>
                    </div>
                </div>
            </div>
            {{-- Spacer so the sticky bar doesn't cover the last bit of content --}}
            <div class="hidden lg:block" style="height: 80px"></div>
            @endunless

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

    {{-- Inline "+ New customer" modal — opens when the dropdown's "+ New" is clicked.
         Self-contained: dispatches `customer-added` on success, the form listens
         and updates the dropdown without losing typed data. --}}
    <x-quick-customer-modal :states="$states" />

    @push('scripts')
    <script>
        function invoiceForm(initialItems, customerStates, companyStateId, productIndex, customerHasGstin, customerIndex, companyHasGstin) {
            const productMap = {};
            (productIndex || []).forEach(p => { productMap[p.id] = p; });
            // Coerce ids to strings for consistent comparison (HTML inputs are strings).
            const customers = (customerIndex || []).map(c => ({...c, id: String(c.id)}));
            // Generate a row-stable id so x-for keys survive splice() without
            // Alpine reusing the wrong DOM node — see "row drift" bug where
            // deleting row 1 left row 0's combobox showing the deleted row's
            // typed text. crypto.randomUUID exists in all modern browsers; the
            // Math.random fallback is good enough for purely-local keys.
            const newUid = () => (window.crypto?.randomUUID?.() || ('row-' + Math.random().toString(36).slice(2) + '-' + Date.now()));
            return {
                items: initialItems.map(i => ({product_id: null, _uid: newUid(), _prevProductName: null, ...i, amount: 0, tax: 0, total: 0})),
                _newUid: newUid,
                customerId: @json((string) old('customer_id', $invoice->customer_id ?? '')),
                customerStates,
                customerHasGstin: customerHasGstin || {},
                companyStateId,
                companyHasGstin: !! companyHasGstin,
                productMap,
                // Reactive customer list — combobox iterates this. Mutated by addCustomer()
                // when a new customer is created inline, so the dropdown updates without reload.
                customers,
                customerCombo: { search: '', open: false, highlight: 0 },
                reverseCharge: @json((bool) old('reverse_charge', $invoice->reverse_charge ?? false)),
                totals: {subtotal: 0, cgst: 0, sgst: 0, igst: 0, totalTax: 0, grandTotal: 0},
                balance: 0,
                get isInterstate() {
                    if (!this.customerId || !this.companyStateId) return false;
                    const cs = this.customerStates[this.customerId];
                    return cs && cs !== this.companyStateId;
                },
                get showB2cWarning() {
                    if (!this.customerId) return false;
                    // Shown only when customer has NO GSTIN and grand total > 2.5 lakh
                    return !this.customerHasGstin[this.customerId] && this.totals.grandTotal > 250000;
                },
                /**
                 * HSN/SAC is legally required only when at least one party is
                 * GST-registered (Rule 46(g)). For supplier-not-registered AND
                 * customer-not-registered transactions (effectively non-GST
                 * cash sales), HSN is optional — making users type it adds
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
                 * phone, GSTIN, city, and state name — matches how Indian SMEs look
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
                        // No matches but text typed — quick-add it.
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
                 * and selects it — without losing any typed line items.
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
                 * Keyboard shortcuts (Tally-style — common in Indian SME billing apps).
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
                        // Skip if focus is in a textarea — Ctrl+S in a textarea
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
                 * First we force the active element to blur — this triggers any
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
                    if (descIsJunk || descIsOldProductName) {
                        row.description = p.name;
                    }
                    row._prevProductName = p.name; // remember for the next swap
                    row.hsn_sac = p.hsn_sac;
                    row.unit = p.unit;
                    row.rate = parseFloat(p.rate) || 0;
                    row.gst_rate = parseFloat(p.gst_rate) || 0;
                    this.recompute();
                },
                recompute() {
                    const inter = this.isInterstate;
                    const rcm = this.reverseCharge;
                    let sub = 0, cgst = 0, sgst = 0, igst = 0;
                    this.items.forEach(item => {
                        const qty = parseFloat(item.quantity) || 0;
                        const rate = parseFloat(item.rate) || 0;
                        const gst = parseFloat(item.gst_rate) || 0;
                        const gross = +(qty * rate).toFixed(2);
                        // Pre-tax discount per Section 15(3) — clamp to gross
                        const disc = Math.max(0, Math.min(parseFloat(item.discount) || 0, gross));
                        const amount = +(gross - disc).toFixed(2);
                        let c = 0, s = 0, ig = 0;
                        // Section 9(3)/9(4) RCM: supplier collects no tax; recipient self-assesses.
                        if (gst > 0 && !rcm) {
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

        /**
         * Per-row product combobox component. Lives inside an x-data="invoiceForm(...)"
         * scope. Uses `this.$root` to call the parent's pickProduct() so existing GST
         * logic (rate/HSN/unit propagation + recompute) keeps working unchanged.
         *
         * Talks to two endpoints:
         *   - GET  /products/search?q=...     → typeahead results
         *   - POST /products/quick-create     → inline "+ Save as new product"
         *
         * Designed for Indian SMEs who often want "Cement bag — 500 — 18%" saved
         * in 5 seconds without leaving the invoice.
         */
        function productRowCombobox(idx) {
            return {
                idx,
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
                    // scope into JS *methods* — inheritance only applies in
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
                        // Loud failure — if you see this in DevTools, the combobox can't
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
                    // touch item.description here — display only.
                    this.$nextTick(() => {
                        if (!this.parent) return;
                        const item = this.parent.items?.[this.idx];
                        if (!item) return;
                        if (item.product_id && this.parent.productMap?.[item.product_id]) {
                            this.combo.search = this.parent.productMap[item.product_id].name;
                        } else if (item.description) {
                            // Custom-typed line item — mirror the saved description into
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
                    // the selected product, drop the selection — they're choosing again.
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
                    // Token-based race-condition guard — if a newer fetch starts before
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
                        // Silent fail — combobox just shows no matches.
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
                        // No matches — open quick-add panel with typed text as the name.
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
                    // For custom-typed rows the description IS the row's data —
                    // clearing it on × would silently destroy what the user typed.
                    if (item && hadProduct) {
                        item.description = '';
                    }
                },

                /**
                 * Mirror typed-but-not-picked text into the line description.
                 *
                 * Why this exists: a user can type "Cement bag" into the product
                 * combobox without ever clicking "+ Save as new product" or picking
                 * an existing match. Without this method, the typed text only lives
                 * in `combo.search` (combobox-local state) and is LOST on submit —
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
                        if (typeof this.parent.recompute === 'function') {
                            this.parent.recompute();
                        }
                    }
                },

                openQuickAdd() {
                    // Seed the form with the search text + any guesses from the current
                    // invoice row. Pre-filling row's rate/gst_rate/hsn lets users save
                    // a new product by simply typing the name — fields are already filled.
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
                            // (captured in init()) — Alpine v3 nested scope doesn't merge
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
                        this.quickAdd.error = 'Network error — check your connection.';
                    } finally {
                        this.quickAdd.submitting = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
