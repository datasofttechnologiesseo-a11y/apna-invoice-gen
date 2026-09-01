@php
    $creditNotes = $invoice->creditNotes;
    $cnDeadline = $invoice->creditNoteDeadline();
    $cnDaysLeft = $invoice->creditNoteDaysLeft();
    $cnWindowClosed = $invoice->isCreditNoteWindowClosed();
    // Only nudge while the invoice can still be credited (something left to credit).
    $cnStillCreditable = (float) $invoice->grand_total > (float) $invoice->credited_amount;
@endphp

{{-- Proactive Section 34(2) deadline notice - warn the operator BEFORE the
     window shuts (not only when they try to issue a credit note too late). --}}
@if ($cnDeadline && $cnStillCreditable)
    @if (! $cnWindowClosed && $cnDaysLeft !== null && $cnDaysLeft <= 60)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3 text-sm">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-amber-900">
                <div class="font-semibold">Credit-note window closes {{ $cnDaysLeft === 0 ? 'today' : 'in ' . $cnDaysLeft . ' ' . \Illuminate\Support\Str::plural('day', $cnDaysLeft) }} ({{ $cnDeadline->format('d M Y') }}).</div>
                <div class="mt-0.5">Under CGST Section 34(2), after this date a credit note against this invoice can no longer reduce your GST liability. If you expect a return or adjustment, issue the credit note before then.</div>
            </div>
        </div>
    @elseif ($cnWindowClosed)
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex items-start gap-3 text-sm">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-gray-600">
                <div class="font-semibold text-gray-700">Credit-note window closed on {{ $cnDeadline->format('d M Y') }}.</div>
                <div class="mt-0.5">Per CGST Section 34(2), a credit note issued now can't reduce the GST on this invoice. A commercial/accounting adjustment is still possible outside GST.</div>
            </div>
        </div>
    @endif
@endif

@if ($creditNotes->isNotEmpty())
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">Credit notes issued</h3>
                <div class="text-xs text-gray-500">Section 34 adjustments against this invoice</div>
            </div>
            <div class="text-xs text-gray-500">
                Total credited: <span class="font-mono font-semibold text-gray-900">₹{{ inr($invoice->credited_amount) }}</span>
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
                            <td class="px-4 py-2 text-right font-mono font-semibold">₹{{ inr($cn->amount) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('credit-notes.pdf', $cn) }}" class="text-brand-700 hover:underline text-sm">PDF</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <x-confirm-form
                                    :action="route('credit-notes.destroy', $cn)"
                                    method="DELETE"
                                    title="Reverse {{ $cn->credit_note_number }}?"
                                    :message="'The credit note is removed and the invoice balance is restored by ₹' . inr($cn->amount) . '. The credit note number stays reserved in the audit log.'"
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
