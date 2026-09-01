<x-app-layout title="Quotation">
    @php
        $eff = $quotation->effectiveStatus();
        $statusColors = [
            'draft' => 'bg-gray-100 text-gray-700',
            'sent' => 'bg-blue-100 text-blue-800',
            'accepted' => 'bg-emerald-100 text-emerald-800',
            'declined' => 'bg-red-100 text-red-800',
            'converted' => 'bg-purple-100 text-purple-800',
            'expired' => 'bg-amber-100 text-amber-800',
        ];
    @endphp

    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Quotations', 'href' => route('quotations.index')],
            ['label' => $quotation->displayNumber()],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight flex flex-wrap items-center gap-2">
                @if ($quotation->quote_number)
                    <span>Quotation {{ $quotation->quote_number }}</span>
                @else
                    <span class="text-gray-500">Draft #{{ $quotation->id }}</span>
                @endif
                <span class="text-xs px-2 py-0.5 rounded uppercase font-bold tracking-wider {{ $statusColors[$eff] ?? 'bg-gray-100' }}">{{ ucfirst($eff) }}</span>
            </h1>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Utility group: edit/PDF - neutral grays, lowest visual priority --}}
                @if ($quotation->isEditable())
                    <a href="{{ route('quotations.edit', $quotation) }}" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-white ring-1 ring-gray-300 hover:ring-brand-400 text-gray-700 hover:text-brand-700 rounded text-sm font-medium transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                @endif

                <a href="{{ route('quotations.pdf', $quotation) }}" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium rounded shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Download PDF
                </a>

                {{-- Workflow group: state transitions. Hairline separator before
                     so they're visually distinct from utility actions. --}}
                @if ($quotation->isDraft() || $quotation->isSent() || ($quotation->canBeConverted() && in_array($quotation->status, ['sent', 'accepted'])))
                    <span class="hidden sm:inline-block w-px h-6 bg-gray-200" aria-hidden="true"></span>
                @endif

                @if ($quotation->isDraft())
                    <x-confirm-form
                        :action="route('quotations.send', $quotation)"
                        method="POST"
                        title="Mark as sent?"
                        message="This assigns the quote number and locks the quotation against further edits. You can still mark it as accepted, declined, or convert it later."
                        confirm-label="Mark as sent"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gradient-to-br from-saffron-400 to-saffron-500 hover:from-saffron-500 hover:to-saffron-600 text-brand-900 rounded text-sm font-semibold shadow-sm ring-1 ring-saffron-300 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            Mark as sent
                        </button>
                    </x-confirm-form>
                @endif

                @if ($quotation->isSent())
                    <x-confirm-form
                        :action="route('quotations.accept', $quotation)"
                        method="POST"
                        title="Mark as accepted?"
                        message="The customer has confirmed this quote - record their acceptance. You can then convert it to a tax invoice."
                        confirm-label="Mark as accepted"
                        confirm-class="bg-emerald-700 hover:bg-emerald-800"
                        tone="default">
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded text-sm font-semibold shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Mark as accepted
                        </button>
                    </x-confirm-form>

                    <button type="button" onclick="document.getElementById('decline-quote-modal').showModal()"
                            class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-white ring-1 ring-red-300 text-red-700 rounded text-sm font-medium hover:bg-red-50 transition">
                        Decline
                    </button>
                @endif

                @if ($quotation->canBeConverted() && in_array($quotation->status, ['sent', 'accepted']))
                    <x-confirm-form
                        :action="route('quotations.convert', $quotation)"
                        method="POST"
                        title="Convert to tax invoice?"
                        message="A new draft invoice will be created with these line items. The quote is then locked. Review and click Issue to send the tax invoice."
                        confirm-label="Convert to invoice"
                        confirm-class="bg-purple-700 hover:bg-purple-800"
                        tone="default">
                        {{-- Convert is the most-valuable workflow action - saffron CTA --}}
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gradient-to-br from-saffron-400 to-saffron-500 hover:from-saffron-500 hover:to-saffron-600 text-brand-900 rounded text-sm font-semibold shadow-sm ring-1 ring-saffron-300 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Convert to invoice
                        </button>
                    </x-confirm-form>
                @endif

                {{-- Destructive group: separator + danger action --}}
                @if ($quotation->isDraft())
                    <span class="hidden sm:inline-block w-px h-6 bg-gray-200" aria-hidden="true"></span>
                    <x-confirm-form
                        :action="route('quotations.destroy', $quotation)"
                        method="DELETE"
                        title="Delete this draft?"
                        message="This draft quotation and its line items are permanently removed. This cannot be undone."
                        confirm-label="Delete draft"
                        confirm-class="bg-red-600 hover:bg-red-700"
                        tone="danger">
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-white ring-1 ring-red-300 hover:bg-red-50 text-red-700 rounded text-sm font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </x-confirm-form>
                @endif
            </div>
        </div>
    </x-slot>

    @if ($quotation->isSent())
        <dialog id="decline-quote-modal" class="rounded-xl shadow-2xl p-0 backdrop:bg-black/40 w-[calc(100vw-1.5rem)] max-w-lg max-h-[calc(100vh-3rem)]">
            <form method="POST" action="{{ route('quotations.decline', $quotation) }}" class="p-5 sm:p-6 space-y-4 max-h-[calc(100vh-3rem)] overflow-y-auto">
                @csrf
                <h3 class="font-display font-bold text-lg text-gray-900">Mark as declined?</h3>
                <p class="text-sm text-gray-600">Record that the customer chose not to proceed. Optionally note why - useful for follow-ups.</p>
                <div>
                    <x-input-label for="decline_reason" value="Reason (optional)" />
                    <textarea id="decline_reason" name="decline_reason" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="500" placeholder="e.g. Going with a different vendor"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 rounded transition">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm font-semibold shadow-sm transition">Mark as declined</button>
                </div>
            </form>
        </dialog>
    @endif

    @php
        $daysLeft = $quotation->daysUntilExpiry();
    @endphp

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Subject + validity countdown - the two pieces of information a
                 customer/owner needs to recognise this quote at a glance. --}}
            @if ($quotation->subject)
                <div class="bg-white shadow sm:rounded-lg px-5 py-4 flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Subject</div>
                        <div class="mt-0.5 text-base sm:text-lg font-semibold text-gray-900">{{ $quotation->subject }}</div>
                    </div>
                    @if ($daysLeft !== null && in_array($quotation->status, ['draft', 'sent']))
                        <div class="shrink-0 text-right">
                            <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Validity</div>
                            <div class="mt-0.5 text-sm font-semibold {{ $daysLeft < 0 ? 'text-red-700' : ($daysLeft < 3 ? 'text-amber-700' : ($daysLeft < 7 ? 'text-amber-600' : 'text-emerald-700')) }}">
                                @if ($daysLeft < 0)
                                    Expired {{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} ago
                                @elseif ($daysLeft === 0)
                                    Expires today
                                @else
                                    {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} remaining
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($daysLeft !== null && in_array($quotation->status, ['draft', 'sent']) && $daysLeft < 7)
                {{-- Even without a subject, surface a near-expiry warning. --}}
                <div class="rounded-lg p-3 text-sm flex items-center gap-2 {{ $daysLeft < 0 ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-amber-50 text-amber-900 border border-amber-200' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        @if ($daysLeft < 0)
                            <strong>Expired</strong> {{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} ago - the quote can still be marked accepted or converted, but the price you committed to has lapsed.
                        @else
                            Expires in <strong>{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</strong> - follow up with the customer.
                        @endif
                    </span>
                </div>
            @endif

            @if ($quotation->isConverted() && $quotation->convertedInvoice)
                <div class="p-4 bg-purple-50 border border-purple-200 text-purple-900 rounded-lg flex items-center justify-between gap-4">
                    <div class="text-sm">
                        <div class="font-semibold">This quotation has been converted.</div>
                        <div class="mt-0.5">A draft invoice was created from these line items.</div>
                    </div>
                    <a href="{{ route('invoices.show', $quotation->convertedInvoice) }}" class="px-3 py-1.5 bg-purple-700 text-white rounded text-sm hover:bg-purple-800">Open invoice →</a>
                </div>
            @endif

            @if ($quotation->isDeclined())
                <div class="p-4 bg-red-50 border border-red-200 text-red-900 rounded-lg text-sm">
                    <span class="font-semibold">Declined</span>
                    @if ($quotation->declined_at)
                        on {{ $quotation->declined_at->format('d M Y') }}
                    @endif
                    @if ($quotation->decline_reason)
                        - {{ $quotation->decline_reason }}
                    @endif
                </div>
            @endif

            @if ($eff === 'expired')
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm">
                    <span class="font-semibold">This quotation has expired.</span>
                    Validity ended on {{ $quotation->valid_until->format('d M Y') }}. You can still mark it accepted or convert it if the customer agrees, or duplicate it as a fresh quote.
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Customer</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $quotation->customer?->name }}</div>
                    @if ($quotation->customer?->phone)
                        <div class="text-sm text-gray-600 font-mono">{{ $quotation->customer->phone }}</div>
                    @endif
                    @if ($quotation->customer?->email)
                        <div class="text-sm text-gray-600">{{ $quotation->customer->email }}</div>
                    @endif
                    @if ($quotation->customer?->state)
                        <div class="text-xs text-gray-500 mt-1">{{ $quotation->customer->state->name }}</div>
                    @endif
                </div>

                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Quote date</div>
                    <div class="mt-1 text-gray-900">{{ $quotation->quote_date?->format('d M Y') }}</div>
                    <div class="mt-3 text-xs text-gray-500 uppercase tracking-wider">Valid until</div>
                    <div class="mt-1 text-gray-900">{{ $quotation->valid_until?->format('d M Y') ?? 'No expiry' }}</div>
                    @if ($quotation->reference)
                        <div class="mt-3 text-xs text-gray-500 uppercase tracking-wider">Customer ref</div>
                        <div class="mt-1 text-gray-900 text-sm">{{ $quotation->reference }}</div>
                    @endif
                    @if ($quotation->delivery_period)
                        <div class="mt-3 text-xs text-gray-500 uppercase tracking-wider">Delivery period</div>
                        <div class="mt-1 text-gray-900 text-sm">{{ $quotation->delivery_period }}</div>
                    @endif
                </div>

                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Tax mode</div>
                    <div class="mt-1">
                        @if ($quotation->is_interstate)
                            <span class="inline-block px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-medium">Inter-state (IGST)</span>
                        @else
                            <span class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-xs font-medium">Intra-state (CGST + SGST)</span>
                        @endif
                    </div>
                    <div class="mt-3 text-xs text-gray-500 uppercase tracking-wider">Grand total</div>
                    <div class="mt-1 text-2xl font-mono font-bold text-gray-900">₹{{ inr($quotation->grand_total) }}</div>
                    <div class="text-[10px] text-gray-500">{{ $amountInWords }}</div>
                </div>
            </div>

            {{-- Sharing panel: email (PDF attached), WhatsApp deep-link, signed public link, and download.
                 Hidden once the quote is converted/declined since the quote is then read-only and
                 sending stale copies confuses the customer. --}}
            @if (! in_array($quotation->status, ['converted', 'declined']))
                @include('quotations.partials.share-panel', ['quotation' => $quotation])
            @endif

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="font-medium text-gray-900">Line items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Description</th>
                                <th class="px-3 py-2 text-left">HSN/SAC</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Disc</th>
                                <th class="px-3 py-2 text-right">GST%</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotation->items as $i => $item)
                                @php
                                    $productName = $item->product?->name ?? null;
                                    $descRaw = trim((string) $item->description);
                                    $descIsJunk = $descRaw === '' || preg_match('/^0+(\.0+)?$/', $descRaw) === 1;
                                    $primary = $productName ?: ($descIsJunk ? '' : $descRaw);
                                    $secondary = ($productName && ! $descIsJunk && strcasecmp($descRaw, $productName) !== 0) ? $descRaw : null;
                                @endphp
                                <tr class="border-t">
                                    <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold">{{ $primary }}</div>
                                        @if ($secondary)
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $secondary }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono">{{ $item->hsn_sac }}</td>
                                    <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ inr($item->rate) }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ inr($item->discount) }}</td>
                                    <td class="px-3 py-2 text-right">{{ rtrim(rtrim(inr($item->gst_rate), '0'), '.') }}%</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ inr($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-t bg-gray-50">
                    <div class="space-y-4 text-sm">
                        @if ($quotation->terms)
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Terms & conditions</div>
                                <div class="mt-1 whitespace-pre-line text-gray-800">{{ $quotation->terms }}</div>
                            </div>
                        @endif
                        @if ($quotation->notes)
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Notes</div>
                                <div class="mt-1 whitespace-pre-line text-gray-800">{{ $quotation->notes }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="md:pl-6 space-y-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">{{ inr($quotation->subtotal) }}</span></div>
                        @if (! $quotation->is_interstate)
                            <div class="flex justify-between"><span>CGST</span><span class="font-mono">{{ inr($quotation->total_cgst) }}</span></div>
                            <div class="flex justify-between"><span>SGST</span><span class="font-mono">{{ inr($quotation->total_sgst) }}</span></div>
                        @else
                            <div class="flex justify-between"><span>IGST</span><span class="font-mono">{{ inr($quotation->total_igst) }}</span></div>
                        @endif
                        @if ((float) $quotation->round_off !== 0.0)
                            <div class="flex justify-between text-gray-500"><span>Round-off</span><span class="font-mono">{{ inr($quotation->round_off) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono">₹{{ inr($quotation->grand_total) }}</span></div>
                        <p class="pt-3 text-[11px] text-gray-500 italic">This is a quotation, not a tax invoice. No GST is collected or filed at this stage. Convert to a tax invoice once accepted.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
