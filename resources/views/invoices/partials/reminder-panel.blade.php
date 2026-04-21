@php
    /** @var \App\Models\Invoice $invoice */
    $reminders = $invoice->reminders()->latest('sent_at')->limit(5)->get();
    $reminderService = app(\App\Services\Reminders\ReminderService::class);
    $daysPastDue = $reminderService->daysPastDue($invoice);
    $hasEmail = (bool) $invoice->customer?->email;
    $waLink = \App\Services\Reminders\WhatsAppReminderChannel::waMeLink($invoice, max(0, $daysPastDue));
@endphp

@if ($reminderService->isEligible($invoice))
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-5 py-3 border-b flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">Payment reminders</h3>
                <div class="text-xs text-gray-500">
                    @if ($daysPastDue < 0)
                        Due in {{ abs($daysPastDue) }} day{{ abs($daysPastDue) > 1 ? 's' : '' }}.
                    @elseif ($daysPastDue === 0)
                        Due today.
                    @else
                        <span class="text-red-600 font-semibold">{{ $daysPastDue }} day{{ $daysPastDue > 1 ? 's' : '' }} overdue</span> — automatic reminders will trigger at the configured thresholds.
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($hasEmail)
                    <form method="POST" action="{{ route('invoices.remind', $invoice) }}" class="inline">
                        @csrf
                        <input type="hidden" name="channel" value="email">
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-600 text-white text-sm font-semibold rounded hover:bg-brand-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Send email reminder
                        </button>
                    </form>
                @else
                    <span class="text-xs text-gray-400">No customer email on file</span>
                @endif

                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#25D366] text-white text-sm font-semibold rounded hover:bg-[#1ebe5b]">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                        WhatsApp reminder
                    </a>
                @endif
            </div>
        </div>
        @if ($reminders->isNotEmpty())
            <div class="px-5 py-3 text-xs">
                <div class="text-gray-500 uppercase tracking-wider font-bold mb-2">Recent reminders</div>
                <ul class="space-y-1">
                    @foreach ($reminders as $r)
                        <li class="flex items-center gap-2 text-gray-700">
                            <span class="inline-block w-2 h-2 rounded-full {{ $r->status === 'sent' ? 'bg-money-500' : 'bg-red-500' }}"></span>
                            <span class="uppercase font-semibold text-[10px] tracking-wider text-gray-500">{{ $r->channel }}</span>
                            <span>·</span>
                            <span>{{ $r->recipient ?: '—' }}</span>
                            <span>·</span>
                            <span>{{ $r->sent_at?->format('d M Y, h:i A') }}</span>
                            <span class="text-gray-400">·</span>
                            <span class="{{ $r->status === 'sent' ? 'text-money-700' : 'text-red-700' }}">
                                {{ $r->status === 'sent'
                                    ? ($r->trigger === 'auto' ? 'auto' : 'manual')
                                    : 'failed' . ($r->error ? ' — ' . $r->error : '') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
