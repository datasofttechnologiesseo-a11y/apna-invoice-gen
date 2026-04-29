<x-app-layout>
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Invoices', 'href' => route('invoices.index')],
            ['label' => $invoice->isDraft() ? 'Draft #' . $invoice->id : $invoice->invoice_number],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight flex flex-wrap items-center gap-2">
                @if ($invoice->isDraft())
                    <span class="text-gray-500">Draft #{{ $invoice->id }}</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 uppercase font-bold tracking-wider">Not yet issued</span>
                @elseif ($invoice->isCancelled())
                    <span>Invoice {{ $invoice->invoice_number }}</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700 uppercase font-bold tracking-wider">Cancelled</span>
                @else
                    <span>Invoice {{ $invoice->invoice_number }}</span>
                    @if ((float) $invoice->balance <= 0)
                        <span class="text-xs px-2 py-0.5 rounded bg-money-100 text-money-800 uppercase font-bold tracking-wider">Paid</span>
                    @elseif ((float) $invoice->paid_amount > 0)
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 uppercase font-bold tracking-wider">Partially paid</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded bg-brand-100 text-brand-800 uppercase font-bold tracking-wider">Issued</span>
                    @endif
                @endif
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                @if ($invoice->isSoftEditable())
                    <a href="{{ route('invoices.edit', $invoice) }}" class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded text-sm hover:bg-gray-300" title="{{ $invoice->isEditable() ? 'Edit draft' : 'Edit notes, terms, due date, transporter (amounts are locked)' }}">Edit</a>
                @endif
                @if ($invoice->isEditable())
                    <x-confirm-form
                        :action="route('invoices.finalize', $invoice)"
                        method="POST"
                        title="Finalize this invoice?"
                        message="Finalising assigns the next invoice number and locks the amounts, line items, and customer. Notes, terms, due date, and transporter details stay editable."
                        confirm-label="Finalize"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800 shadow-sm">Finalize</button>
                    </x-confirm-form>
                @endif
                <span class="inline-flex rounded overflow-hidden shadow-sm">
                    <a href="{{ route('invoices.pdf', $invoice) }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm hover:bg-gray-900" title="Ink-saver download — black on white for printing">Download PDF</a>
                    <a href="{{ route('invoices.pdf', $invoice, false) . '?color=1' }}" class="px-2 py-1.5 bg-gray-700 text-white text-xs hover:bg-gray-800 border-l border-gray-600" title="Download full colour version (uses more ink)" aria-label="Download full colour PDF (uses more ink)">🎨</a>
                </span>
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="px-3 py-1.5 bg-white border text-gray-700 rounded text-sm hover:bg-gray-50">Print view</a>
                @if (! $invoice->isDraft() && ! $invoice->isCancelled() && (float) $invoice->grand_total > (float) $invoice->credited_amount)
                    <a href="{{ route('credit-notes.create', $invoice) }}" class="px-3 py-1.5 bg-amber-100 text-amber-900 border border-amber-300 rounded text-sm hover:bg-amber-200" title="Issue a credit note — for returns, post-sale discounts, rate corrections">Issue credit note</a>
                @endif
                @if ($invoice->isEditable())
                    <x-confirm-form
                        :action="route('invoices.destroy', $invoice)"
                        method="DELETE"
                        title="Delete this draft?"
                        message="This draft and all its line items are permanently removed. This cannot be undone."
                        confirm-label="Delete draft"
                        confirm-class="bg-red-600 hover:bg-red-700"
                        tone="danger">
                        <button type="button" class="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700 shadow-sm">Delete draft</button>
                    </x-confirm-form>
                @endif
                @if ($invoice->canBeCancelled())
                    <button type="button" onclick="document.getElementById('cancel-invoice-modal').showModal()" class="px-3 py-1.5 bg-white border border-red-300 text-red-700 rounded text-sm hover:bg-red-50">Cancel invoice</button>
                @endif
            </div>
        </div>
    </x-slot>

    @if ($invoice->canBeCancelled())
        <dialog id="cancel-invoice-modal" class="rounded-xl shadow-2xl p-0 backdrop:bg-black/40 w-[calc(100vw-1.5rem)] max-w-lg max-h-[calc(100vh-3rem)]">
            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="p-6 space-y-4 max-h-[calc(100vh-3rem)] overflow-y-auto">
                @csrf
                <div>
                    <h3 class="font-display font-bold text-lg text-gray-900">Cancel this invoice?</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Cancelling <strong class="font-mono">{{ $invoice->invoice_number }}</strong> preserves the record
                        for GST audit but stops further payments. The invoice number is NOT reused.
                    </p>
                </div>
                <div>
                    <label for="cancellation_reason" class="text-xs uppercase font-bold tracking-wider text-gray-500">Reason (required)</label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                              class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                              placeholder="e.g. Wrong amount — replacement issued as INV-0145"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="document.getElementById('cancel-invoice-modal').close()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">Keep invoice</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded hover:bg-red-700">Yes, cancel it</button>
                </div>
            </form>
        </dialog>
    @endif

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />
            @if ($errors->any())
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            @php $payments = $invoice->payments; @endphp

            @if (! $invoice->isDraft() && ! $invoice->isCancelled())
                @include('invoices.partials.share-panel', ['invoice' => $invoice])
                @if ((float) $invoice->balance > 0)
                    @include('invoices.partials.reminder-panel', ['invoice' => $invoice])
                @endif
                @include('invoices.partials.credit-notes', ['invoice' => $invoice])
            @endif

            @if ($invoice->isCancelled())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm">
                    <div class="font-semibold text-red-800">This invoice is cancelled.</div>
                    <div class="text-red-700 mt-1">
                        Cancelled on {{ $invoice->cancelled_at?->format('d M Y, h:i A') }}.
                        @if ($invoice->cancellation_reason)
                            <br>Reason: <span class="italic">"{{ $invoice->cancellation_reason }}"</span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($invoice->balance > 0 && ! $invoice->isDraft() && $invoice->status !== 'cancelled')
                <form method="POST" action="{{ route('invoices.payments', $invoice) }}" class="bg-white shadow sm:rounded-lg p-5 space-y-4">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Record a payment</h3>
                        <div class="text-sm text-gray-500">Balance due: <span class="font-mono font-semibold text-gray-900">₹{{ number_format((float) $invoice->balance, 2) }}</span></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Amount (₹) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $invoice->balance }}" value="{{ old('amount', $invoice->balance) }}" required class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Method *</label>
                            <select name="method" required class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                                @foreach (config('payment_methods.methods') as $code => $m)
                                    <option value="{{ $code }}" @selected(old('method', 'upi') === $code)>{{ $m['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Date received *</label>
                            <input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Reference / Txn ID</label>
                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" maxlength="80" placeholder="UPI txn / cheque no." class="mt-1 block w-full border-gray-300 rounded shadow-sm font-mono text-sm">
                        </div>
                    </div>
                    {{-- TDS section: collapsed by default. Indian B2B service providers
                         frequently have TDS deducted by corporate customers under Sections 194-x. --}}
                    <details x-data="{ open: false }" :open="open" @toggle="open = $event.target.open" class="border border-amber-200 bg-amber-50/30 rounded p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-amber-900 select-none">
                            🧾 Customer deducted TDS? <span class="text-xs font-normal text-gray-500">(click to expand)</span>
                        </summary>
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3" x-data="{
                            amt: {{ old('tds_amount', 0) }},
                            rate: {{ old('tds_rate', 0) }},
                            section: '{{ old('tds_section', '') }}',
                            // Section → standard rate map (Indian Income Tax Act, AY 2024-25 onwards).
                            // Source: Income Tax Department TDS rate chart.
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
                                this.amt = +(grossAmt * (parseFloat(this.rate) || 0) / 100).toFixed(2);
                            },
                            applySection() {
                                if (this.sectionRates[this.section] !== undefined) {
                                    this.rate = this.sectionRates[this.section];
                                    this.recalcFromRate();
                                }
                            },
                        }">
                            <div>
                                <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">TDS Section</label>
                                <select name="tds_section" x-model="section" @change="applySection()"
                                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                                    <option value="">— None —</option>
                                    <optgroup label="Common (services / contracts)">
                                        <option value="194C_indiv">194C — Contractor (Indiv/HUF) — 1%</option>
                                        <option value="194C_other">194C — Contractor (Co/Firm) — 2%</option>
                                        <option value="194J_prof">194J — Professional fees — 10%</option>
                                        <option value="194J_tech">194J — Technical services — 2%</option>
                                        <option value="194H">194H — Commission / brokerage — 5%</option>
                                    </optgroup>
                                    <optgroup label="Rent &amp; property">
                                        <option value="194I_plant">194I — Rent (plant/machinery) — 2%</option>
                                        <option value="194I_land">194I — Rent (land/building) — 10%</option>
                                        <option value="194-IB">194-IB — Rent by Indiv/HUF (&gt;₹50k/mo) — 5%</option>
                                        <option value="194-IA">194-IA — Sale of property (&gt;₹50L) — 1%</option>
                                    </optgroup>
                                    <optgroup label="Goods &amp; e-commerce">
                                        <option value="194Q">194Q — Purchase of goods (&gt;₹50L) — 0.1%</option>
                                        <option value="194O">194O — E-commerce participants — 1%</option>
                                    </optgroup>
                                    <optgroup label="Other">
                                        <option value="194A">194A — Interest (other than securities) — 10%</option>
                                        <option value="194D">194D — Insurance commission — 5%</option>
                                        <option value="194B">194B — Lottery / games — 30%</option>
                                        <option value="51">Section 51 — GST TDS (govt) — 2%</option>
                                        <option value="other">Other</option>
                                    </optgroup>
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1" x-show="section && section !== 'other' && sectionThresholds[section]" x-text="sectionThresholds[section]"></p>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">TDS Rate (%)</label>
                                <input type="number" name="tds_rate" step="0.01" min="0" max="30" x-model="rate" @input="recalcFromRate()"
                                       placeholder="e.g. 10" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                                <p class="text-[10px] text-gray-500 mt-1">206AA penalty: 20% if recipient has no PAN.</p>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">TDS Amount (₹)</label>
                                <input type="number" name="tds_amount" step="0.01" min="0" x-model="amt"
                                       placeholder="0.00" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                                <p class="text-[10px] text-gray-500 mt-1">Tracked for Form 26AS reconciliation. Invoice balance reduces by gross.</p>
                            </div>
                        </div>
                    </details>

                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Notes (optional)</label>
                        <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                    </div>
                    <div class="flex justify-end">
                        <button class="px-4 py-2 bg-money-600 hover:bg-money-700 text-white rounded font-semibold">Record payment & issue receipt</button>
                    </div>
                </form>
            @endif

            @if ($payments->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                        <div>
                            <h3 class="font-display font-bold text-gray-900 text-base flex items-center gap-2">
                                <svg class="w-5 h-5 text-money-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Payment Receipts
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Click <strong>PDF</strong> on any row to download the official receipt issued for that payment.</p>
                        </div>
                        <div class="text-xs text-gray-500 sm:text-right">{{ $payments->count() }} receipt{{ $payments->count() > 1 ? 's' : '' }} issued</div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2 text-left">Receipt</th>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Method</th>
                                    <th class="px-4 py-2 text-left">Reference</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($payments as $p)
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-sm font-semibold">{{ $p->receipt_number }}</td>
                                        <td class="px-4 py-2">{{ $p->received_at?->format('d M Y') }}</td>
                                        <td class="px-4 py-2">{{ $p->methodLabel() }}</td>
                                        <td class="px-4 py-2 text-xs font-mono text-gray-600">{{ $p->reference_number ?: '—' }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-semibold">
                                            ₹{{ number_format((float) $p->amount, 2) }}
                                            @if ((float) $p->tds_amount > 0)
                                                <div class="text-[10px] font-normal text-amber-700 mt-0.5" title="TDS deducted at source">
                                                    incl. TDS {{ $p->tds_section }} ₹{{ number_format((float) $p->tds_amount, 2) }}<br>
                                                    <span class="text-gray-500">Net to bank: ₹{{ number_format($p->netReceived(), 2) }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            <a href="{{ route('payments.receipt', $p) }}" class="text-brand-600 hover:underline text-sm">PDF</a>
                                            <span class="text-gray-300 mx-1">·</span>
                                            <x-confirm-form
                                                :action="route('payments.destroy', $p)"
                                                method="DELETE"
                                                title="Reverse this payment?"
                                                message="Receipt {{ $p->receipt_number }} (₹{{ number_format((float) $p->amount, 2) }}) will be removed. The receipt number stays reserved in the log for audit, but the invoice balance is restored."
                                                confirm-label="Reverse payment"
                                                confirm-class="bg-red-600 hover:bg-red-700"
                                                tone="warning">
                                                <button type="button" class="text-red-600 hover:underline text-sm">Reverse</button>
                                            </x-confirm-form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-xs uppercase tracking-wider text-gray-500 font-semibold">Total received</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold">₹{{ number_format((float) $payments->sum('amount'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                @include('invoices.partials.document', ['invoice' => $invoice, 'amountInWords' => $amountInWords])
            </div>
        </div>
    </div>
</x-app-layout>
