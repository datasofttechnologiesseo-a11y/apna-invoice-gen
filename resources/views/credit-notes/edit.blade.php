<x-app-layout title="Credit note">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Invoices', 'href' => route('invoices.index')],
            ['label' => $invoice->displayNumber(), 'href' => route('invoices.show', $invoice)],
            ['label' => 'New credit note'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
            Credit note against <span class="font-mono">{{ $invoice->invoice_number }}</span>
        </h1>
    </x-slot>

    @php
        $lines = $lines ?? collect();
        $oldLines = (array) old('lines', []);
        $stateLabel = $invoice->stateTaxLabel();
        $initialReason = old('reason', array_key_first(config('credit_note_reasons')));
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="max-w-4xl space-y-5">
            <x-flash />
            @if ($errors->any())
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            <div class="p-4 bg-brand-50 border border-brand-200 rounded-xl text-sm text-brand-900">
                <div class="font-semibold">Section 34 of the CGST Act</div>
                <p class="mt-1 leading-relaxed">
                    For goods returned, an overcharged rate or a post-sale discount. It reduces what is receivable on this invoice and is reported in GSTR-1 for the month it is issued.
                </p>
            </div>

            <form method="POST" action="{{ route('credit-notes.store', $invoice) }}"
                  x-data="creditNoteForm(@js($lines), {{ (float) $creditable }}, @js(plain_amount(old('amount', $creditable))), @js((object) $oldLines))"
                  class="p-4 sm:p-6 bg-white rounded-xl ring-1 ring-gray-200 space-y-6">
                @csrf

                {{-- Summary of the parent invoice --}}
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-600">Invoice total</dt>
                        <dd class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($invoice->grand_total) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-600">Already credited</dt>
                        <dd class="font-mono text-gray-900 tabular-nums">₹{{ inr($invoice->credited_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-600">Paid</dt>
                        <dd class="font-mono text-gray-900 tabular-nums">₹{{ inr($invoice->paid_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-600">Can be credited</dt>
                        <dd class="font-mono font-bold text-gray-900 tabular-nums">₹{{ inr($creditable) }}</dd>
                    </div>
                </dl>

                {{-- Lines: pick what came back and how many. The amount and the
                     tax split are worked out the same way the server does it. --}}
                @if ($lines->isNotEmpty())
                    <section class="border-t pt-5" aria-labelledby="cn-lines-title">
                        <div class="flex items-baseline justify-between gap-3 flex-wrap">
                            <h2 id="cn-lines-title" class="font-semibold text-gray-900">Items returned or credited <span class="font-normal text-sm text-gray-500">(optional)</span></h2>
                            <button type="button" x-show="hasLines" x-cloak @click="clearLines()" class="inline-flex items-center min-h-[40px] text-sm font-medium text-brand-700 hover:underline">Clear quantities</button>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Enter the quantity for each line, and the amount is worked out with its GST. Leave them blank for a price reduction and type the amount below.</p>

                        {{-- Desktop table --}}
                        <div class="mt-3 hidden sm:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Item</th>
                                        <th class="px-3 py-2 text-right">Billed</th>
                                        <th class="px-3 py-2 text-right">Left</th>
                                        <th class="px-3 py-2 text-right">Quantity</th>
                                        <th class="px-3 py-2 text-right">Amount (₹)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="l in lines" :key="l.id">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <div class="font-medium text-gray-900" x-text="l.description"></div>
                                                <div class="text-xs text-gray-600"><span x-text="(String(l.hsn_sac || '').startsWith('99') ? 'SAC ' : 'HSN ') + (l.hsn_sac || '–')"></span> · <span x-text="l.gst_rate + '% GST'"></span></div>
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono tabular-nums" x-text="qtyStr(l.quantity) + (l.unit ? ' ' + l.unit : '')"></td>
                                            <td class="px-3 py-2 text-right font-mono tabular-nums" x-text="qtyStr(l.left)"></td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" step="any" min="0" :max="l.left" inputmode="decimal"
                                                       :name="`lines[${l.id}]`" x-model="qty[l.id]" @input="sync()"
                                                       :disabled="l.left <= 0"
                                                       :aria-label="`Quantity to credit for ${l.description}`"
                                                       :aria-describedby="`lines.${l.id}-error`"
                                                       class="w-24 h-10 border-gray-300 rounded-md text-sm text-right disabled:bg-gray-100">
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono tabular-nums" x-text="fig(l) ? fmt(fig(l).total) : '–'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Phone: one card per line, no sideways scroll --}}
                        <ul class="mt-3 sm:hidden divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                            <template x-for="l in lines" :key="'m' + l.id">
                                <li class="p-3">
                                    <div class="font-medium text-gray-900" x-text="l.description"></div>
                                    <div class="text-xs text-gray-600">
                                        <span x-text="(String(l.hsn_sac || '').startsWith('99') ? 'SAC ' : 'HSN ') + (l.hsn_sac || '–')"></span>
                                        · <span x-text="l.gst_rate + '% GST'"></span>
                                        · Billed <span x-text="qtyStr(l.quantity)"></span>, left <span x-text="qtyStr(l.left)"></span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        {{-- Mirrors the desktop input; only one of the two is submitted. --}}
                                        <input type="number" step="any" min="0" :max="l.left" inputmode="decimal"
                                               x-model="qty[l.id]" @input="sync()" :disabled="l.left <= 0"
                                               :aria-label="`Quantity to credit for ${l.description}`"
                                               class="w-28 h-10 border-gray-300 rounded-md text-sm text-right disabled:bg-gray-100">
                                        <span class="font-mono tabular-nums text-sm" x-text="fig(l) ? '₹' + fmt(fig(l).total) : ''"></span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                        @foreach ($lines as $l)
                            <x-input-error :messages="$errors->get('lines.' . $l['id'])" :for="'lines.' . $l['id']" class="mt-2" />
                        @endforeach
                        <x-input-error :messages="$errors->get('lines')" for="lines" class="mt-2" />

                        {{-- Tax split of the picked lines --}}
                        <div x-show="hasLines" x-cloak class="mt-4 ml-auto max-w-sm space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-gray-600">Taxable value</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(totals.taxable)"></span></div>
                            @if ($invoice->is_interstate)
                                <div class="flex justify-between"><span class="text-gray-600">IGST</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(totals.igst)"></span></div>
                            @else
                                <div class="flex justify-between"><span class="text-gray-600">CGST</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(totals.cgst)"></span></div>
                                <div class="flex justify-between"><span class="text-gray-600">{{ $stateLabel }}</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(totals.sgst)"></span></div>
                            @endif
                            <div class="flex justify-between border-t pt-1 font-semibold"><span>Credit amount</span><span class="font-mono tabular-nums" x-text="'₹' + fmt(lineAmount)"></span></div>
                            <p x-show="Math.abs(lineAmount - lineSum) >= 0.005" class="text-xs text-gray-500">Adjusted by the invoice round-off.</p>
                        </div>
                    </section>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t pt-5">
                    <div>
                        <x-input-label for="amount" value="Credit amount (₹) *" />
                        <input inputmode="decimal" id="amount" name="amount" type="number" step="any" min="0.01" max="{{ $creditable }}"
                               x-model="amount" @input="if (!hasLines) typedAmount = amount" :readonly="hasLines" :required="!hasLines"
                               aria-describedby="amount-error amount-hint"
                               class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-right font-mono focus:border-brand-600 focus:ring-brand-600"
                               :class="hasLines && 'bg-gray-100 text-gray-700'">
                        <p id="amount-hint" class="text-xs text-gray-500 mt-1">
                            <span x-show="hasLines" x-cloak>Worked out from the quantities above. Clear them to type an amount.</span>
                            <span x-show="!hasLines">The taxable value and GST are split from the amount automatically.</span>
                        </p>
                        <x-input-error :messages="$errors->get('amount')" for="amount" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="credit_note_date" value="Credit note date *" />
                        <x-text-input id="credit_note_date" name="credit_note_date" type="date" class="mt-1 block w-full h-10" :value="old('credit_note_date', now()->toDateString())" :max="now()->toDateString()" required />
                        <p class="text-xs text-gray-500 mt-1">On or after the invoice date ({{ $invoice->invoice_date?->format('d M Y') }}).</p>
                        <x-input-error :messages="$errors->get('credit_note_date')" for="credit_note_date" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="reason" value="Reason *" />
                        <select id="reason" name="reason" x-model="reason" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                            @foreach (config('credit_note_reasons') as $code => $r)
                                <option value="{{ $code }}" @selected(old('reason') === $code) title="{{ $r['hint'] }}">{{ $r['label'] }}</option>
                            @endforeach
                        </select>
                        <p x-show="reason === 'sales_return' && hasLines" x-cloak class="text-xs text-gray-600 mt-1">Items that track stock go back into stock on the credit note date.</p>
                        <x-input-error :messages="$errors->get('reason')" for="reason" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Notes (printed on the credit note)" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600" maxlength="1000" placeholder="e.g. Goods returned as per DC #123 dated 12 May 2026.">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" for="notes" class="mt-1" />
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-4 border-t">
                    <a href="{{ route('invoices.show', $invoice) }}" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</a>
                    <x-primary-button>Issue credit note</x-primary-button>
                </div>
            </form>
          </div>
        </div>
    </div>

    @push('scripts')
    <script>
        /**
         * Credit note from invoice lines. Mirrors App\Services\CreditNoteLines:
         * per line, taxable value and each tax head × qty ÷ billed qty, rounded
         * to the paisa; the last of a line takes the exact remainder; the note
         * is the sum, adjusted only by the invoice round-off. The server
         * recomputes everything; this is the preview.
         */
        function creditNoteForm(lines, creditable, initialAmount, oldQty) {
            const r2 = v => Math.round((v + (v >= 0 ? 1e-9 : -1e-9)) * 100) / 100;
            const qty = {};
            (lines || []).forEach(l => { qty[l.id] = (oldQty && oldQty[l.id] !== undefined) ? oldQty[l.id] : ''; });
            return {
                lines: lines || [],
                qty,
                creditable,
                reason: @json($initialReason),
                amount: initialAmount,
                typedAmount: initialAmount,
                totals: { taxable: 0, cgst: 0, sgst: 0, igst: 0 },
                lineSum: 0,
                lineAmount: 0,
                get hasLines() { return this.lines.some(l => (parseFloat(this.qty[l.id]) || 0) > 0); },
                init() { if (this.hasLines) this.sync(); },
                fig(l) {
                    let q = parseFloat(this.qty[l.id]) || 0;
                    if (q <= 0 || l.left <= 0) return null;
                    q = Math.min(q, l.left);
                    let f;
                    if (q >= l.left - 0.0005) {
                        f = {
                            taxable: Math.max(0, r2(l.amount - l.done.taxable)),
                            cgst: Math.max(0, r2(l.cgst - l.done.cgst)),
                            sgst: Math.max(0, r2(l.sgst - l.done.sgst)),
                            igst: Math.max(0, r2(l.igst - l.done.igst)),
                        };
                    } else {
                        const k = l.quantity > 0 ? q / l.quantity : 0;
                        f = { taxable: r2(l.amount * k), cgst: r2(l.cgst * k), sgst: r2(l.sgst * k), igst: r2(l.igst * k) };
                    }
                    f.total = r2(f.taxable + f.cgst + f.sgst + f.igst);
                    return f;
                },
                sync() {
                    if (!this.hasLines) {
                        this.amount = this.typedAmount;
                        return;
                    }
                    const t = { taxable: 0, cgst: 0, sgst: 0, igst: 0 };
                    this.lines.forEach(l => {
                        const f = this.fig(l);
                        if (!f) return;
                        t.taxable += f.taxable; t.cgst += f.cgst; t.sgst += f.sgst; t.igst += f.igst;
                    });
                    Object.keys(t).forEach(k => { t[k] = r2(t[k]); });
                    this.totals = t;
                    this.lineSum = r2(t.taxable + t.cgst + t.sgst + t.igst);
                    let amount = this.lineSum;
                    const clearsAll = this.lines.every(l => l.left <= 0 || (parseFloat(this.qty[l.id]) || 0) >= l.left - 0.0005);
                    if (amount > this.creditable + 0.001 && amount - this.creditable < 1) {
                        amount = this.creditable;
                    } else if (clearsAll && amount <= this.creditable && this.creditable - amount < 1) {
                        amount = this.creditable;
                    }
                    this.lineAmount = amount;
                    this.amount = String(amount);
                },
                clearLines() {
                    this.lines.forEach(l => { this.qty[l.id] = ''; });
                    this.sync();
                },
                qtyStr(v) { return String(+(+v).toFixed(3)); },
                fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
    @endpush
</x-app-layout>
