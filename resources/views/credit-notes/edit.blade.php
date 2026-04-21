<x-app-layout>
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Invoices', 'href' => route('invoices.index')],
            ['label' => $invoice->invoice_number ?? 'Draft #' . $invoice->id, 'href' => route('invoices.show', $invoice)],
            ['label' => 'Issue credit note'],
        ]" />
        <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
            Issue credit note against <span class="font-mono">{{ $invoice->invoice_number }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-5">
            <x-flash />
            @if ($errors->any())
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            <div class="p-5 bg-brand-50 border border-brand-200 rounded-lg text-sm text-brand-900">
                <div class="font-semibold">Section 34 of the CGST Act</div>
                <p class="mt-1 leading-relaxed">
                    Credit notes adjust a finalised invoice when goods are returned, the rate was
                    overcharged, or a post-sale discount is agreed. The credit note reduces the
                    effective receivable on the original invoice and must be reported in GSTR-1
                    for the month it's issued.
                </p>
            </div>

            <form method="POST" action="{{ route('credit-notes.store', $invoice) }}" class="p-6 bg-white shadow sm:rounded-lg space-y-5">
                @csrf

                {{-- Summary of the parent invoice --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Invoice total</div>
                        <div class="font-mono font-semibold text-gray-900">₹{{ number_format((float) $invoice->grand_total, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Already credited</div>
                        <div class="font-mono text-gray-900">₹{{ number_format((float) $invoice->credited_amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Paid</div>
                        <div class="font-mono text-gray-900">₹{{ number_format((float) $invoice->paid_amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-money-700 font-semibold">Max creditable</div>
                        <div class="font-mono font-bold text-money-700">₹{{ number_format($creditable, 2) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t pt-5">
                    <div>
                        <x-input-label for="credit_note_date" value="Credit note date *" />
                        <x-text-input id="credit_note_date" name="credit_note_date" type="date" class="mt-1 block w-full" :value="old('credit_note_date', now()->toDateString())" :max="now()->toDateString()" required />
                        <p class="text-xs text-gray-500 mt-1">Must be on or after the invoice date ({{ $invoice->invoice_date?->format('d M Y') }}).</p>
                    </div>

                    <div>
                        <x-input-label for="amount" value="Credit amount (₹) *" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" :max="$creditable" class="mt-1 block w-full text-right font-mono" :value="old('amount', $creditable)" required />
                        <p class="text-xs text-gray-500 mt-1">Tax components are pro-rated automatically across CGST/SGST/IGST.</p>
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="reason" value="Reason *" />
                        <select id="reason" name="reason" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (config('credit_note_reasons') as $code => $r)
                                <option value="{{ $code }}" @selected(old('reason') === $code) title="{{ $r['hint'] }}">{{ $r['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Notes (shown on the credit note PDF)" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="1000" placeholder="e.g. Goods returned as per DC #123 dated 12 May 2026.">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-gray-500 hover:underline">← Cancel</a>
                    <x-primary-button>Issue credit note</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
