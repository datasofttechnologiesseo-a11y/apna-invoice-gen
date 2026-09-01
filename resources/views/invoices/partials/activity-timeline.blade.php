@php
    // Build the activity timeline purely from existing fields (no schema
    // change, no new columns). Order: oldest → newest. Each entry is one
    // verifiable event that happened to the invoice.
    $events = [];

    // 1. Created (always)
    $events[] = [
        'when' => $invoice->created_at,
        'icon' => 'M12 4v16m8-8H4',
        'tone' => 'gray',
        'title' => 'Created',
        'sub' => 'Invoice draft was started',
    ];

    // 2. Finalized - when an invoice number was assigned and amounts locked
    if ($invoice->finalized_at) {
        $events[] = [
            'when' => $invoice->finalized_at,
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'tone' => 'brand',
            'title' => 'Issued',
            'sub' => 'Issued as ' . ($invoice->invoice_number ?? 'invoice') . ' - line items locked',
        ];
    }

    // 3. Each payment received - use created_at (precise timestamp) for the
    // chronological position in the timeline, but show received_at (the user-
    // entered business date) in the visible label.
    foreach ($invoice->payments ?? [] as $payment) {
        $events[] = [
            'when' => $payment->created_at,
            'display' => $payment->received_at ?? $payment->created_at,
            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            'tone' => 'money',
            'title' => '₹' . inr($payment->amount) . ' received',
            'sub' => trim(
                ($payment->method ? ucfirst(str_replace('_', ' ', $payment->method)) : 'Payment')
                . ($payment->receipt_number ? ' · Receipt ' . $payment->receipt_number : '')
                . ($payment->reference_number ? ' · Ref ' . $payment->reference_number : '')
            ),
        ];
    }

    // 4. Each credit note (if any)
    foreach ($invoice->creditNotes ?? [] as $cn) {
        $events[] = [
            'when' => $cn->created_at,
            'icon' => 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
            'tone' => 'accent',
            'title' => 'Credit note ' . ($cn->credit_note_number ?? '#' . $cn->id) . ' issued',
            'sub' => '₹' . inr(($cn->grand_total ?? 0)) . ' credited back to customer',
        ];
    }

    // 5. Fully paid - derive from balance, anchor to last payment time
    if (! $invoice->isDraft() && (float) $invoice->balance <= 0 && $invoice->payments?->isNotEmpty()) {
        $lastPayment = $invoice->payments->sortByDesc('created_at')->first();
        $events[] = [
            'when' => $lastPayment->created_at,
            'icon' => 'M5 13l4 4L19 7',
            'tone' => 'money',
            'title' => 'Fully paid',
            'sub' => 'Outstanding balance is ₹0',
            'final' => true,
        ];
    }

    // 6. Cancelled (terminal)
    if ($invoice->cancelled_at) {
        $events[] = [
            'when' => $invoice->cancelled_at,
            'icon' => 'M6 18L18 6M6 6l12 12',
            'tone' => 'danger',
            'title' => 'Cancelled',
            'sub' => $invoice->cancellation_reason ? '"' . $invoice->cancellation_reason . '"' : 'No reason given',
            'final' => true,
        ];
    }

    // Sort by when (oldest first), keeping a stable order for ties
    usort($events, fn ($a, $b) => $a['when'] <=> $b['when']);

    $tones = [
        'gray'    => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600',    'ring' => 'ring-gray-200'],
        'brand'   => ['bg' => 'bg-brand-100',   'text' => 'text-brand-700',   'ring' => 'ring-brand-200'],
        'money' => ['bg' => 'bg-money-100', 'text' => 'text-money-700', 'ring' => 'ring-money-200'],
        'accent'   => ['bg' => 'bg-accent-100',   'text' => 'text-accent-700',   'ring' => 'ring-accent-200'],
        'danger'     => ['bg' => 'bg-danger-100',     'text' => 'text-danger-700',     'ring' => 'ring-danger-200'],
    ];
@endphp

<div x-data="{ open: {{ $invoice->isDraft() || count($events) <= 2 ? 'false' : 'true' }} }"
     class="bg-white rounded-2xl shadow-card ring-1 ring-gray-200 overflow-hidden">
    <button type="button"
            @click="open = !open"
            class="w-full flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 transition"
            :class="open ? 'border-b border-gray-100' : ''"
            :aria-expanded="open.toString()">
        <div class="flex items-center gap-2 min-w-0">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="font-display font-bold text-gray-900 truncate">Activity</h3>
            <span class="text-[11px] uppercase tracking-wider font-bold text-gray-500 shrink-0">{{ count($events) }} {{ count($events) === 1 ? 'event' : 'events' }}</span>
        </div>
        <svg class="w-4 h-4 text-gray-500 transition" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    {{-- Hidden semantic list for screen readers / search engines --}}
    <ol class="sr-only">
        @foreach ($events as $e)
            <li>{{ $e['title'] }} on {{ $e['when']?->format('d M Y') }}</li>
        @endforeach
    </ol>

    <div x-show="open" x-cloak x-transition.duration.150ms>
        <ol class="relative px-5 py-5 space-y-5">
            {{-- Vertical connector line behind the icons --}}
            <div class="absolute left-[33px] top-7 bottom-7 w-px bg-gray-200" aria-hidden="true"></div>

            @foreach ($events as $i => $e)
                @php $t = $tones[$e['tone']] ?? $tones['gray']; @endphp
                <li class="relative flex items-start gap-4">
                    <div class="relative z-10 shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $t['bg'] }} ring-4 ring-white">
                        <svg class="w-5 h-5 {{ $t['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $e['icon'] }}"/>
                        </svg>
                    </div>
                    @php $shown = $e['display'] ?? $e['when']; @endphp
                    <div class="min-w-0 flex-1 pt-1.5">
                        <div class="flex items-baseline justify-between gap-3 flex-wrap">
                            <div class="font-semibold text-gray-900">{{ $e['title'] }}</div>
                            <time class="text-xs text-gray-500 tabular-nums whitespace-nowrap"
                                  datetime="{{ $shown?->toIso8601String() }}"
                                  title="{{ $shown?->format('d M Y, h:i A') }}">
                                {{ $shown?->diffForHumans() }}
                            </time>
                        </div>
                        @if (! empty($e['sub']))
                            <div class="text-sm text-gray-500 mt-0.5">{{ $e['sub'] }}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
