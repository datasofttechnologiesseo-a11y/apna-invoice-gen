@php
    $creditNotes = $invoice->creditNotes;
    $cnDeadline = $invoice->creditNoteDeadline();
    $cnDaysLeft = $invoice->creditNoteDaysLeft();
    $cnWindowClosed = $invoice->isCreditNoteWindowClosed();
    // Only nudge while the invoice can still be credited (something left to credit).
    $cnStillCreditable = (float) $invoice->grand_total > (float) $invoice->credited_amount;
    $showDeadline = $cnDeadline && $cnStillCreditable
        && ($cnWindowClosed || ($cnDaysLeft !== null && $cnDaysLeft <= 60));
@endphp

@if ($creditNotes->isNotEmpty() || $showDeadline)
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-baseline justify-between gap-2">
            <h2 class="font-semibold text-gray-900">Credit notes</h2>
            @if ($creditNotes->isNotEmpty())
                <div class="text-sm text-gray-600">Credited <span class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($invoice->credited_amount) }}</span></div>
            @endif
        </div>

        {{-- Section 34(2): warn BEFORE the window shuts, not only when a
             credit note is attempted too late. --}}
        @if ($showDeadline)
            @if (! $cnWindowClosed)
                <div class="px-4 py-3 bg-accent-50 text-sm text-accent-900 border-b border-accent-100">
                    <div class="font-semibold">Window closes {{ $cnDaysLeft === 0 ? 'today' : 'in ' . $cnDaysLeft . ' ' . \Illuminate\Support\Str::plural('day', $cnDaysLeft) }} ({{ $cnDeadline->format('d M Y') }}).</div>
                    <div class="mt-0.5 text-xs">After this date a credit note cannot reduce GST on this invoice (Section 34(2)).</div>
                </div>
            @else
                <div class="px-4 py-3 bg-gray-50 text-sm text-gray-700 border-b border-gray-100">
                    <div class="font-semibold">Window closed on {{ $cnDeadline->format('d M Y') }}.</div>
                    <div class="mt-0.5 text-xs">A credit note cannot reduce GST on this invoice now (Section 34(2)). A commercial adjustment outside GST is still possible.</div>
                </div>
            @endif
        @endif

        @if ($creditNotes->isNotEmpty())
            <ul class="divide-y divide-gray-100">
                @foreach ($creditNotes as $cn)
                    <li class="px-4 py-3 text-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-mono font-semibold text-gray-900">{{ $cn->credit_note_number }}</span>
                            <span class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ inr($cn->amount) }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-600">{{ $cn->credit_note_date?->format('d M Y') }} · {{ $cn->reasonLabel() }}</div>
                        <div class="mt-1 flex items-center gap-4">
                            <a href="{{ route('credit-notes.pdf', $cn) }}" class="inline-flex items-center min-h-[40px] text-brand-700 font-medium hover:underline">PDF</a>
                            <x-confirm-form
                                :action="route('credit-notes.destroy', $cn)"
                                method="DELETE"
                                title="Reverse {{ $cn->credit_note_number }}?"
                                :message="'The credit note is removed and the invoice balance is restored by ₹' . inr($cn->amount) . '. The credit note number stays reserved in the audit log.'"
                                confirm-label="Reverse credit note"
                                confirm-class="bg-danger-600 hover:bg-danger-700"
                                tone="warning">
                                <button type="button" class="inline-flex items-center min-h-[40px] text-danger-700 hover:underline">Reverse</button>
                            </x-confirm-form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
