@php $creditNotes = $invoice->creditNotes; @endphp

@if ($creditNotes->isNotEmpty())
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">Credit notes issued</h3>
                <div class="text-xs text-gray-500">Section 34 adjustments against this invoice</div>
            </div>
            <div class="text-xs text-gray-500">
                Total credited: <span class="font-mono font-semibold text-gray-900">₹{{ number_format((float) $invoice->credited_amount, 2) }}</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Credit note</th>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Reason</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($creditNotes as $cn)
                        <tr>
                            <td class="px-4 py-2 font-mono text-sm font-semibold">{{ $cn->credit_note_number }}</td>
                            <td class="px-4 py-2">{{ $cn->credit_note_date?->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-xs">{{ $cn->reasonLabel() }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold">₹{{ number_format((float) $cn->amount, 2) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('credit-notes.pdf', $cn) }}" class="text-brand-600 hover:underline text-sm">PDF</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <x-confirm-form
                                    :action="route('credit-notes.destroy', $cn)"
                                    method="DELETE"
                                    title="Reverse {{ $cn->credit_note_number }}?"
                                    :message="'The credit note is removed and the invoice balance is restored by ₹' . number_format((float) $cn->amount, 2) . '. The credit note number stays reserved in the audit log.'"
                                    confirm-label="Reverse credit note"
                                    confirm-class="bg-red-600 hover:bg-red-700"
                                    tone="warning">
                                    <button type="button" class="text-red-600 hover:underline text-sm">Reverse</button>
                                </x-confirm-form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
