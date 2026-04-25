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
                    <div class="px-5 py-3 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Payment history</h3>
                        <div class="text-xs text-gray-500">{{ $payments->count() }} receipt{{ $payments->count() > 1 ? 's' : '' }} issued</div>
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
                                        <td class="px-4 py-2 text-right font-mono font-semibold">₹{{ number_format((float) $p->amount, 2) }}</td>
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
