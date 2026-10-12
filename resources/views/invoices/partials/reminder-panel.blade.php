@php
    /** @var \App\Models\Invoice $invoice */
    $reminders = $invoice->reminders()->latest('sent_at')->limit(5)->get();
    $reminderService = app(\App\Services\Reminders\ReminderService::class);
    $daysPastDue = $reminderService->daysPastDue($invoice);
    $hasEmail = (bool) $invoice->customer?->email;
    // WhatsApp is offered once on the page, in the Share card: the reminder
    // text is the same status-aware message (Invoice::shareMessageText).
@endphp

@if ($reminderService->isEligible($invoice))
    <div class="bg-white rounded-xl ring-1 ring-gray-200">
        <div class="p-4">
            <h2 class="font-semibold text-gray-900">Payment reminders</h2>
            <p class="mt-0.5 text-sm text-gray-600">
                @if ($daysPastDue < 0)
                    Due in {{ abs($daysPastDue) }} {{ \Illuminate\Support\Str::plural('day', abs($daysPastDue)) }}.
                @elseif ($daysPastDue === 0)
                    Due today.
                @else
                    <span class="text-danger-700 font-semibold">{{ $daysPastDue }} {{ \Illuminate\Support\Str::plural('day', $daysPastDue) }} overdue.</span>
                    Automatic reminders follow your reminder schedule.
                @endif
            </p>
            <div class="mt-3">
                @if ($hasEmail)
                    <form method="POST" action="{{ route('invoices.remind', $invoice) }}">
                        @csrf
                        <input type="hidden" name="channel" value="email">
                        <button class="w-full inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Send email reminder
                        </button>
                    </form>
                @else
                    <p class="text-xs text-gray-600">No customer email on file. Use WhatsApp in the Share card.</p>
                @endif
            </div>
        </div>
        @if ($reminders->isNotEmpty())
            <div class="px-4 py-3 border-t border-gray-100 text-xs">
                <div class="font-semibold text-gray-700 mb-1.5">Recent reminders</div>
                <ul class="space-y-1.5">
                    @foreach ($reminders as $r)
                        <li class="text-gray-700">
                            <span class="inline-block w-2 h-2 mr-1 rounded-full {{ $r->status === 'sent' ? 'bg-money-500' : 'bg-danger-500' }}" aria-hidden="true"></span>
                            <span class="font-medium">{{ $r->channel === 'whatsapp' ? 'WhatsApp' : ucfirst((string) $r->channel) }}</span>
                            · {{ $r->sent_at?->format('d M Y, h:i A') }}
                            · <span class="{{ $r->status === 'sent' ? 'text-money-700' : 'text-danger-700' }}">{{ $r->status === 'sent' ? ($r->trigger === 'auto' ? 'Automatic' : 'Sent') : 'Failed' }}</span>
                            @if ($r->recipient)
                                <div class="pl-3 text-gray-500 truncate">{{ $r->recipient }}</div>
                            @endif
                            @if ($r->status !== 'sent' && $r->error)
                                <div class="pl-3 text-danger-700">{{ $r->error }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
