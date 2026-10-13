<x-app-layout title="Invoice">
    @php
        $payments = $invoice->payments;
        $isDraft = $invoice->isDraft();
        $isCancelled = $invoice->isCancelled();
        $isIssued = ! $isDraft && ! $isCancelled;
        $hasBalance = $isIssued && (float) $invoice->balance > 0;
        $canCreditNote = $isIssued && (float) $invoice->grand_total > (float) $invoice->credited_amount;

        // One look for every button on the page (design standard).
        $btnPrimary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white shadow-sm transition';
        $btnSecondary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition';
        $menuItem = 'flex w-full items-center gap-2 min-h-[40px] px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-50 rounded-md';
        $card = 'bg-white rounded-xl ring-1 ring-gray-200';
    @endphp

    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Invoices', 'href' => route('invoices.index')],
            ['label' => $invoice->displayNumber()],
        ]" />
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight flex flex-wrap items-center gap-2">
                @if ($isDraft)
                    <span class="text-gray-600">Draft #{{ $invoice->id }}</span>
                @else
                    <span>{{ $invoice->documentTitle() }} {{ $invoice->invoice_number }}</span>
                @endif
                <x-status-badge :status="$invoice->status" :label="$invoice->statusLabel()" />
            </h1>

            {{-- One primary action, one secondary, the rest under More. --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($isDraft)
                    <x-confirm-form
                        :action="route('invoices.finalize', $invoice)"
                        method="POST"
                        title="Issue this invoice?"
                        message="This assigns the next invoice number and locks the amounts, line items and customer. Notes, terms, due date and transporter details stay editable."
                        confirm-label="Issue invoice"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="{{ $btnPrimary }}">Issue invoice</button>
                    </x-confirm-form>
                @elseif ($hasBalance)
                    <a href="#record-payment"
                       @click="$nextTick(() => document.getElementById('pay-amount')?.focus({ preventScroll: true }))"
                       class="{{ $btnPrimary }}">Record payment</a>
                @endif

                {{-- Download PDF: pick the Rule 48 copies to include. --}}
                <div class="relative" x-data="{
                        open: false,
                        types: { recipient: true, supplier: false, transporter: false },
                        colour: false,
                        base: '{{ route('invoices.pdf', $invoice) }}',
                        get selected() { return Object.keys(this.types).filter(k => this.types[k]); },
                        download() {
                            if (!this.selected.length) return;
                            window.location = this.base + '?copyTypes=' + this.selected.join(',') + (this.colour ? '&color=1' : '');
                            this.open = false;
                        }
                    }" @keydown.escape="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="{{ $btnSecondary }}">
                        Download PDF
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 lg:left-auto lg:right-0 z-30 mt-1 w-72 max-w-[calc(100vw-2rem)] bg-white ring-1 ring-gray-200 rounded-xl shadow-lg p-3 text-sm">
                        <div class="font-semibold text-gray-900 mb-1">Copies to include</div>
                        <label class="flex items-start gap-2 py-1.5 cursor-pointer">
                            <input type="checkbox" x-model="types.recipient" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-900">Customer</span><br><span class="text-xs text-gray-600">Original for Recipient</span></span>
                        </label>
                        <label class="flex items-start gap-2 py-1.5 cursor-pointer">
                            <input type="checkbox" x-model="types.supplier" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-900">Supplier</span><br><span class="text-xs text-gray-600">{{ $invoice->defaultCopyCount() === 2 ? 'Duplicate for Supplier' : 'Triplicate for Supplier' }}</span></span>
                        </label>
                        {{-- Rule 48(2): services need no transporter copy. --}}
                        @if ($invoice->defaultCopyCount() === 3)
                        <label class="flex items-start gap-2 py-1.5 cursor-pointer">
                            <input type="checkbox" x-model="types.transporter" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-900">Delivery</span><br><span class="text-xs text-gray-600">Duplicate for Transporter</span></span>
                        </label>
                        @endif
                        <label class="flex items-start gap-2 pt-2 mt-1 border-t border-gray-100 cursor-pointer">
                            <input type="checkbox" x-model="colour" class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            <span><span class="font-medium text-gray-900">Colour version</span><br><span class="text-xs text-gray-600">Uses your template colours and more ink.</span></span>
                        </label>
                        <button type="button" @click="download()" :disabled="!selected.length" class="mt-2 w-full {{ $btnPrimary }} disabled:opacity-40 disabled:cursor-not-allowed">Download</button>
                    </div>
                </div>

                {{-- Everything else. --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" class="{{ $btnSecondary }}">
                        More
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 lg:left-auto lg:right-0 z-30 mt-1 w-64 max-w-[calc(100vw-2rem)] bg-white ring-1 ring-gray-200 rounded-xl shadow-lg p-1.5">
                        @if ($invoice->isSoftEditable())
                            <a href="{{ route('invoices.edit', $invoice) }}" class="{{ $menuItem }}"
                               title="{{ $invoice->isEditable() ? 'Edit the draft' : 'Notes, terms, due date and transporter details. Amounts are locked.' }}">
                                {{ $invoice->isEditable() ? 'Edit' : 'Edit details' }}
                            </a>
                        @endif
                        {{-- Duplicate into a fresh editable draft: repeat billing. --}}
                        <form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">
                            @csrf
                            <button type="submit" class="{{ $menuItem }}" title="Copy to a new draft with the same customer and line items">Duplicate</button>
                        </form>
                        <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="{{ $menuItem }}">Print</a>
                        <a href="{{ route('invoices.print', ['invoice' => $invoice, 'format' => 'thermal']) }}" target="_blank" rel="noopener"
                           class="{{ $menuItem }}" title="80 mm receipt for counter printers">Thermal print (80 mm)</a>
                        @if ($canCreditNote)
                            @if ($invoice->isCreditNoteWindowClosed())
                                <span class="{{ $menuItem }} text-gray-500 cursor-not-allowed hover:bg-transparent"
                                      title="Section 34(2) deadline passed on {{ $invoice->creditNoteDeadline()->format('d M Y') }}. Issue a commercial refund instead.">
                                    Issue credit note
                                    <span class="ml-auto text-xs">Closed</span>
                                </span>
                            @else
                                <a href="{{ route('credit-notes.create', $invoice) }}" class="{{ $menuItem }}"
                                   title="For returns, post-sale discounts and rate corrections. Section 34(2) deadline: {{ $invoice->creditNoteDeadline()->format('d M Y') }}">
                                    Issue credit note
                                </a>
                            @endif
                        @endif
                        @if ($invoice->canBeCancelled() && \App\Support\Team::can('invoices.cancel'))
                            <div class="my-1 border-t border-gray-100"></div>
                            <button type="button" @click="open = false; document.getElementById('cancel-invoice-modal').showModal()"
                                    class="{{ $menuItem }} text-danger-700 hover:bg-danger-50">Cancel invoice</button>
                        @endif
                        {{-- Only a draft can be deleted. An issued number stays on
                             record, cancelled or not (GSTR-1 Table 13). --}}
                        @if ($invoice->canBeDeleted() && \App\Support\Team::can('invoices.destroy'))
                            <div class="my-1 border-t border-gray-100"></div>
                            <x-confirm-form
                                :action="route('invoices.destroy', $invoice)"
                                method="DELETE"
                                title="Delete this draft?"
                                message="The draft and its line items will be deleted. This cannot be undone."
                                confirm-label="Delete draft"
                                confirm-class="bg-danger-600 hover:bg-danger-700"
                                tone="danger">
                                <button type="button" class="{{ $menuItem }} text-danger-700 hover:bg-danger-50">Delete draft</button>
                            </x-confirm-form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    @if ($invoice->canBeCancelled())
        <dialog id="cancel-invoice-modal" class="rounded-xl shadow-2xl p-0 backdrop:bg-black/40 w-[calc(100vw-1.5rem)] max-w-lg max-h-[calc(100vh-3rem)]">
            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="p-6 space-y-4 max-h-[calc(100vh-3rem)] overflow-y-auto">
                @csrf
                <div>
                    <h2 class="font-display font-bold text-lg text-gray-900">Cancel this invoice?</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        <strong class="font-mono">{{ $invoice->invoice_number }}</strong> stays on record for GST audit,
                        but no further payments can be recorded against it. The number is not reused.
                    </p>
                </div>
                <div>
                    <label for="cancellation_reason" class="block text-sm font-medium text-gray-700">Reason *</label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                              aria-describedby="cancellation_reason-error"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                              placeholder="e.g. Wrong amount, replaced by INV-0145">{{ old('cancellation_reason') }}</textarea>
                    <x-input-error :messages="$errors->get('cancellation_reason')" for="cancellation_reason" class="mt-1" />
                </div>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="document.getElementById('cancel-invoice-modal').close()" class="{{ $btnSecondary }}">Keep invoice</button>
                    <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-danger-600 hover:bg-danger-700 text-white">Cancel invoice</button>
                </div>
            </form>
        </dialog>
        @error('cancellation_reason')
            @push('scripts')
            <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('cancel-invoice-modal')?.showModal());</script>
            @endpush
        @enderror
    @endif

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />
            @if ($errors->any() && ! $errors->has('cancellation_reason'))
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            {{-- First issued invoice: one short line, shown once (server gate:
                 this is the user's only issued invoice; client gate: localStorage). --}}
            @if (! empty($isFirstFinalized) && $isFirstFinalized)
                <div x-data="{ show: !localStorage.getItem('hideFirstInvoiceCelebration') }" x-show="show" x-cloak
                     class="flex items-start gap-3 p-4 bg-brand-50 border border-brand-200 rounded-xl text-sm text-brand-900">
                    <div class="flex-1">
                        <div class="font-semibold">Your first invoice is issued.</div>
                        <div class="mt-0.5">Share it with the customer and record the payment when it arrives. It is included in GSTR-1 for this month.</div>
                    </div>
                    <button type="button"
                            @click="localStorage.setItem('hideFirstInvoiceCelebration','1'); show=false"
                            class="shrink-0 inline-flex items-center justify-center w-10 h-10 -m-2 text-brand-800 hover:bg-brand-100 rounded-md text-xl leading-none"
                            aria-label="Dismiss">×</button>
                </div>
            @endif

            @if ($isCancelled)
                <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 text-sm">
                    <div class="font-semibold text-danger-800">This invoice is cancelled. It is not payable.</div>
                    <div class="text-danger-700 mt-1">
                        Cancelled on {{ $invoice->cancelled_at?->format('d M Y, h:i A') }}.
                        @if ($invoice->cancellation_reason)
                            Reason: <span class="italic">"{{ $invoice->cancellation_reason }}"</span>
                        @endif
                    </div>
                    <div class="text-danger-700 mt-1">The number stays on record for GST and is not reused.</div>
                </div>
            @endif

            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-6 lg:items-start">
                {{-- The document comes first, on every screen size. --}}
                <div class="min-w-0 {{ $card }} overflow-hidden">
                    @include('invoices.partials.document', ['invoice' => $invoice, 'amountInWords' => $amountInWords])
                </div>

                {{-- Right rail: what to do with the invoice. Below the document on a phone. --}}
                <aside class="mt-6 lg:mt-0 space-y-4" aria-label="Invoice actions">
                    @if ($isDraft)
                        <div class="{{ $card }} p-4 text-sm">
                            <h2 class="font-semibold text-gray-900">Not issued yet</h2>
                            <p class="mt-1 text-gray-600">Issue it to assign the invoice number and lock the amounts. Until then you can still change anything.</p>
                            <a href="{{ route('invoices.edit', $invoice) }}" class="mt-3 w-full {{ $btnSecondary }}">Edit draft</a>
                        </div>
                    @endif

                    @if ($hasBalance)
                        <form id="record-payment" method="POST" action="{{ route('invoices.payments', $invoice) }}" class="{{ $card }} p-4 space-y-3 scroll-mt-24">
                            @csrf
                            <div class="flex items-baseline justify-between gap-2">
                                <h2 class="font-semibold text-gray-900">Record payment</h2>
                                <div class="text-sm text-gray-600">Balance <span class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($invoice->balance) }}</span></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="pay-amount" class="block text-sm font-medium text-gray-700">Amount (₹) *</label>
                                    <input inputmode="decimal" id="pay-amount" type="number" name="amount" step="any" min="0.01" max="{{ $invoice->balance }}" value="{{ plain_amount(old('amount', request('pay_amount') ? min((float) request('pay_amount'), (float) $invoice->balance) : $invoice->balance)) }}" required aria-describedby="amount-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-right font-mono">
                                    <x-input-error :messages="$errors->get('amount')" for="amount" class="mt-1" />
                                </div>
                                <div>
                                    <label for="pay-method" class="block text-sm font-medium text-gray-700">Method *</label>
                                    <select id="pay-method" name="method" required class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                        @foreach (config('payment_methods.methods') as $code => $m)
                                            <option value="{{ $code }}" @selected(old('method', request('pay_method', 'upi')) === $code)>{{ $m['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="pay-received-at" class="block text-sm font-medium text-gray-700">Date received *</label>
                                    <input id="pay-received-at" type="date" name="received_at" value="{{ old('received_at', request('pay_date', now()->toDateString())) }}" max="{{ now()->toDateString() }}" required aria-describedby="received_at-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                    <x-input-error :messages="$errors->get('received_at')" for="received_at" class="mt-1" />
                                </div>
                                <div>
                                    <label for="pay-reference-number" class="block text-sm font-medium text-gray-700">Reference</label>
                                    <input id="pay-reference-number" type="text" name="reference_number" value="{{ old('reference_number', mb_substr((string) request('pay_ref', ''), 0, 80)) }}" maxlength="80" placeholder="UPI txn / cheque no." class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm font-mono text-sm">
                                </div>
                            </div>

                            {{-- TDS: collapsed by default. Corporate customers often deduct
                                 TDS under Sections 194-x before paying a service invoice. --}}
                            <details x-data="{ open: {{ old('tds_amount') ? 'true' : 'false' }} }" :open="open" @toggle="open = $event.target.open" class="rounded-md border border-gray-200 p-3">
                                <summary class="cursor-pointer text-sm font-medium text-gray-800 select-none">
                                    TDS deducted by customer <span class="text-xs font-normal text-gray-500">(optional)</span>
                                </summary>
                                <div class="mt-3 space-y-3" x-data="{
                                    amt: {{ (float) old('tds_amount', 0) }},
                                    rate: {{ (float) old('tds_rate', 0) }},
                                    section: @js((string) old('tds_section', '')),
                                    // TDS is on the value before GST when the invoice shows GST
                                    // separately (CBDT Circular 23/2017; GST TDS by s.51's own
                                    // Explanation). The payment carries GST in the invoice's own
                                    // proportion, so the base is amount x taxable value / total.
                                    // No GST charged (bill of supply, reverse charge): the amount.
                                    taxable: {{ (float) $invoice->total_tax > 0 ? (float) $invoice->subtotal : (float) $invoice->grand_total }},
                                    grand: {{ (float) $invoice->grand_total }},
                                    // Section → standard rate (Income Tax Act, AY 2024-25 onwards).
                                    sectionRates: {
                                        '194A': 10, '194B': 30, '194C_indiv': 1, '194C_other': 2,
                                        '194D': 5, '194H': 5, '194I_land': 10, '194I_plant': 2,
                                        '194-IA': 1, '194-IB': 5, '194J_prof': 10, '194J_tech': 2,
                                        '194O': 1, '194Q': 0.1, '51': 2,
                                    },
                                    sectionThresholds: {
                                        '194A': 'Threshold ₹5,000/yr (₹40,000 for banks)',
                                        '194B': 'Threshold ₹10,000',
                                        '194C_indiv': 'Threshold ₹30,000 single / ₹1,00,000 aggregate',
                                        '194C_other': 'Threshold ₹30,000 single / ₹1,00,000 aggregate',
                                        '194D': 'Threshold ₹15,000/yr',
                                        '194H': 'Threshold ₹15,000/yr',
                                        '194I_land': 'Threshold ₹2,40,000/yr',
                                        '194I_plant': 'Threshold ₹2,40,000/yr',
                                        '194-IA': 'Property value > ₹50 lakh',
                                        '194-IB': 'Rent > ₹50,000/month (individual/HUF)',
                                        '194J_prof': 'Threshold ₹30,000/yr',
                                        '194J_tech': 'Threshold ₹30,000/yr',
                                        '194O': 'E-commerce participants',
                                        '194Q': 'Aggregate purchases > ₹50 lakh/yr',
                                        '51': 'GST TDS by govt / PSU on contracts > ₹2.5 lakh',
                                    },
                                    recalcFromRate() {
                                        const grossAmt = parseFloat(document.querySelector('input[name=amount]')?.value || 0);
                                        const base = this.grand > 0 ? grossAmt * this.taxable / this.grand : grossAmt;
                                        this.amt = +(base * (parseFloat(this.rate) || 0) / 100).toFixed(2);
                                    },
                                    applySection() {
                                        if (this.sectionRates[this.section] !== undefined) {
                                            this.rate = this.sectionRates[this.section];
                                            this.recalcFromRate();
                                        }
                                    },
                                }">
                                    <div>
                                        <label for="pay-tds-section" class="block text-sm font-medium text-gray-700">TDS section</label>
                                        <select id="pay-tds-section" name="tds_section" x-model="section" @change="applySection()"
                                                class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                            <option value="">None</option>
                                            <optgroup label="Common (services / contracts)">
                                                <option value="194C_indiv">194C · Contractor (Indiv/HUF) · 1%</option>
                                                <option value="194C_other">194C · Contractor (Co/Firm) · 2%</option>
                                                <option value="194J_prof">194J · Professional fees · 10%</option>
                                                <option value="194J_tech">194J · Technical services · 2%</option>
                                                <option value="194H">194H · Commission / brokerage · 5%</option>
                                            </optgroup>
                                            <optgroup label="Rent &amp; property">
                                                <option value="194I_plant">194I · Rent (plant/machinery) · 2%</option>
                                                <option value="194I_land">194I · Rent (land/building) · 10%</option>
                                                <option value="194-IB">194-IB · Rent by Indiv/HUF (&gt;₹50k/mo) · 5%</option>
                                                <option value="194-IA">194-IA · Sale of property (&gt;₹50L) · 1%</option>
                                            </optgroup>
                                            <optgroup label="Goods &amp; e-commerce">
                                                <option value="194Q">194Q · Purchase of goods (&gt;₹50L) · 0.1%</option>
                                                <option value="194O">194O · E-commerce participants · 1%</option>
                                            </optgroup>
                                            <optgroup label="Other">
                                                <option value="194A">194A · Interest (other than securities) · 10%</option>
                                                <option value="194D">194D · Insurance commission · 5%</option>
                                                <option value="194B">194B · Lottery / games · 30%</option>
                                                <option value="51">Section 51 · GST TDS (govt) · 2%</option>
                                                <option value="other">Other</option>
                                            </optgroup>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1" x-show="section && section !== 'other' && sectionThresholds[section]" x-text="sectionThresholds[section]"></p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label for="pay-tds-rate" class="block text-sm font-medium text-gray-700">TDS rate (%)</label>
                                            <input inputmode="decimal" id="pay-tds-rate" type="number" name="tds_rate" step="0.01" min="0" max="30" x-model="rate" @input="recalcFromRate()"
                                                   placeholder="e.g. 10" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm text-right">
                                        </div>
                                        <div>
                                            <label for="pay-tds-amount" class="block text-sm font-medium text-gray-700">TDS amount (₹)</label>
                                            <input inputmode="decimal" id="pay-tds-amount" type="number" name="tds_amount" step="any" min="0" x-model="amt"
                                                   placeholder="0" aria-describedby="tds_amount-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm text-right">
                                        </div>
                                    </div>
                                    <x-input-error :messages="$errors->get('tds_amount')" for="tds_amount" />
                                    <p class="text-xs text-gray-500">@if ((float) $invoice->total_tax > 0)Suggested on the value before GST. @endif Section 206AA: 20% if no PAN is furnished. The invoice balance reduces by the gross amount; TDS is kept for Form 26AS.</p>
                                </div>
                            </details>

                            <div>
                                <label for="pay-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <input id="pay-notes" type="text" name="notes" maxlength="500" value="{{ old('notes') }}" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <button type="submit" class="w-full {{ $btnPrimary }}">Record payment</button>
                        </form>
                    @endif

                    @if ($isIssued)
                        @include('invoices.partials.share-panel', ['invoice' => $invoice])
                        @if ((float) $invoice->balance > 0)
                            @include('invoices.partials.reminder-panel', ['invoice' => $invoice])
                        @endif
                        @include('invoices.partials.credit-notes', ['invoice' => $invoice])
                    @endif

                    @if ($payments->isNotEmpty())
                        <div class="{{ $card }} overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-baseline justify-between gap-2">
                                <h2 class="font-semibold text-gray-900">Receipts</h2>
                                <div class="text-sm text-gray-600">Received <span class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($payments->sum('amount')) }}</span></div>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @foreach ($payments as $p)
                                    <li class="px-4 py-3 text-sm">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="font-mono font-semibold text-gray-900">{{ $p->receipt_number }}</span>
                                            <span class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($p->amount) }}</span>
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-600">
                                            {{ $p->received_at?->format('d M Y') }} · {{ $p->methodLabel() }}@if ($p->reference_number) · <span class="font-mono">{{ $p->reference_number }}</span>@endif
                                        </div>
                                        @if ((float) $p->tds_amount > 0)
                                            <div class="mt-0.5 text-xs text-gray-600" title="TDS deducted at source">
                                                Includes TDS {{ $p->tds_section }} ₹{{ inr($p->tds_amount) }} · Net to bank ₹{{ inr($p->netReceived()) }}
                                            </div>
                                        @endif
                                        <div class="mt-1 flex items-center gap-4">
                                            <a href="{{ route('payments.receipt', $p) }}" class="inline-flex items-center min-h-[40px] text-brand-700 font-medium hover:underline">Receipt PDF</a>
                                            <x-confirm-form
                                                :action="route('payments.destroy', $p)"
                                                method="DELETE"
                                                title="Reverse this payment?"
                                                message="Receipt {{ $p->receipt_number }} (₹{{ inr($p->amount) }}) will be removed and the invoice balance restored. The receipt number stays reserved in the audit log."
                                                confirm-label="Reverse payment"
                                                confirm-class="bg-danger-600 hover:bg-danger-700"
                                                tone="warning">
                                                <button type="button" class="inline-flex items-center min-h-[40px] text-danger-700 hover:underline">Reverse</button>
                                            </x-confirm-form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @include('invoices.partials.activity-timeline', ['invoice' => $invoice])
                </aside>
            </div>
        </div>
    </div>

    {{-- One-click "Save & Download PDF" support: when the edit form sets
         post_save_action=pdf, the controller redirects here with ?download=1.
         A hidden anchor is clicked once on load, triggering the browser's
         native download flow without navigating away. --}}
    @if (request('download'))
        @push('scripts')
        <script>
            (function() {
                const url = @json(route('invoices.pdf', $invoice));
                const a = document.createElement('a');
                a.href = url;
                a.rel = 'noopener';
                // Tiny delay so the page paints first.
                setTimeout(() => { a.click(); }, 250);
            })();
        </script>
        @endpush
    @endif
    {{-- Review invite after every second issued invoice (see InvoiceController::finalize). --}}
    <x-review-invite :show="(bool) session('review_invite')" :delay="1800" />
</x-app-layout>
