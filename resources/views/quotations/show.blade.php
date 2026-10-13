<x-app-layout title="Quotation">
    @php
        $eff = $quotation->effectiveStatus();
        $btnPrimary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white shadow-sm transition';
        $btnSecondary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition';
        $btnDangerOutline = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-danger-300 text-danger-700 hover:bg-danger-50 transition';
        $canConvert = $quotation->canBeConverted() && in_array($quotation->status, ['sent', 'accepted']);
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
                    <span class="text-gray-600">Draft #{{ $quotation->id }}</span>
                @endif
                <x-status-badge :status="$eff" :label="$quotation->statusLabel()" />
            </h1>

            {{-- One primary action for the state the quote is in; the rest are
                 secondary. Order: primary, then supporting, then destructive. --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($quotation->isDraft())
                    <x-confirm-form
                        :action="route('quotations.send', $quotation)"
                        method="POST"
                        title="Mark as sent?"
                        message="This assigns the quote number and locks the quotation. You can still mark it accepted or declined, or convert it to an invoice."
                        confirm-label="Mark as sent"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="{{ $btnPrimary }}">Mark as sent</button>
                    </x-confirm-form>
                @endif

                @if ($canConvert)
                    <x-confirm-form
                        :action="route('quotations.convert', $quotation)"
                        method="POST"
                        title="Convert to tax invoice?"
                        message="A draft invoice is created with these line items and the quote is locked. Review the draft, then issue it."
                        confirm-label="Convert to invoice"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="{{ $btnPrimary }}">Convert to invoice</button>
                    </x-confirm-form>
                @endif

                @if ($quotation->isSent())
                    <x-confirm-form
                        :action="route('quotations.accept', $quotation)"
                        method="POST"
                        title="Mark as accepted?"
                        message="Record that the customer accepted this quote. You can then convert it to a tax invoice."
                        confirm-label="Mark as accepted"
                        confirm-class="bg-brand-700 hover:bg-brand-800"
                        tone="default">
                        <button type="button" class="{{ $btnSecondary }}">Mark as accepted</button>
                    </x-confirm-form>
                @endif

                @if ($quotation->isEditable())
                    <a href="{{ route('quotations.edit', $quotation) }}" class="{{ $btnSecondary }}">Edit</a>
                @endif

                <a href="{{ route('quotations.pdf', $quotation) }}" class="{{ $btnSecondary }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Download PDF
                </a>

                @if ($quotation->isSent())
                    <button type="button" onclick="document.getElementById('decline-quote-modal').showModal()" class="{{ $btnDangerOutline }}">Decline</button>
                @endif

                @if ($quotation->isDraft())
                    <x-confirm-form
                        :action="route('quotations.destroy', $quotation)"
                        method="DELETE"
                        title="Delete this draft?"
                        message="The draft quotation and its line items will be deleted. This cannot be undone."
                        confirm-label="Delete draft"
                        confirm-class="bg-danger-600 hover:bg-danger-700"
                        tone="danger">
                        <button type="button" class="{{ $btnDangerOutline }}">Delete draft</button>
                    </x-confirm-form>
                @endif
            </div>
        </div>
    </x-slot>

    @if ($quotation->isSent())
        <dialog id="decline-quote-modal" class="rounded-xl shadow-2xl p-0 backdrop:bg-black/40 w-[calc(100vw-1.5rem)] max-w-lg max-h-[calc(100vh-3rem)]">
            <form method="POST" action="{{ route('quotations.decline', $quotation) }}" class="p-5 sm:p-6 space-y-4 max-h-[calc(100vh-3rem)] overflow-y-auto">
                @csrf
                <h2 class="font-display font-bold text-lg text-gray-900">Mark as declined?</h2>
                <p class="text-sm text-gray-600">Record that the customer did not accept this quote.</p>
                <div>
                    <x-input-label for="decline_reason" value="Reason (optional)" />
                    <textarea id="decline_reason" name="decline_reason" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="500" placeholder="e.g. Going with a different vendor"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 rounded transition">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-danger-600 hover:bg-danger-700 text-white rounded text-sm font-semibold shadow-sm transition">Mark as declined</button>
                </div>
            </form>
        </dialog>
    @endif

    @php
        $daysLeft = $quotation->daysUntilExpiry();
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Subject + validity countdown - the two pieces of information a
                 customer/owner needs to recognise this quote at a glance. --}}
            @if ($quotation->subject)
                <div class="bg-white rounded-xl ring-1 ring-gray-200 px-5 py-4 flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-gray-600">Subject</div>
                        <div class="mt-0.5 text-base sm:text-lg font-semibold text-gray-900">{{ $quotation->subject }}</div>
                    </div>
                    @if ($daysLeft !== null && in_array($quotation->status, ['draft', 'sent']))
                        <div class="shrink-0 text-right">
                            <div class="text-xs font-semibold text-gray-600">Validity</div>
                            <div class="mt-0.5 text-sm font-semibold {{ $daysLeft < 0 ? 'text-danger-700' : ($daysLeft < 3 ? 'text-accent-700' : ($daysLeft < 7 ? 'text-accent-600' : 'text-money-700')) }}">
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
                <div class="rounded-lg p-3 text-sm flex items-center gap-2 {{ $daysLeft < 0 ? 'bg-danger-50 text-danger-800 border border-danger-200' : 'bg-accent-50 text-accent-900 border border-accent-200' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        @if ($daysLeft < 0)
                            <strong>Expired</strong> {{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} ago. It can still be marked accepted or converted, but the quoted validity has lapsed.
                        @else
                            Expires in <strong>{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</strong>. Follow up with the customer.
                        @endif
                    </span>
                </div>
            @endif

            @if ($quotation->isConverted() && $quotation->convertedInvoice)
                <div class="p-4 bg-brand-50 border border-brand-200 text-brand-900 rounded-lg flex items-center justify-between gap-4">
                    <div class="text-sm">
                        <div class="font-semibold">This quotation has been converted.</div>
                        <div class="mt-0.5">A draft invoice was created from these line items.</div>
                    </div>
                    <a href="{{ route('invoices.show', $quotation->convertedInvoice) }}" class="shrink-0 inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Open invoice</a>
                </div>
            @endif

            @if ($quotation->isDeclined())
                <div class="p-4 bg-danger-50 border border-danger-200 text-danger-900 rounded-lg text-sm">
                    <span class="font-semibold">Declined</span>
                    @if ($quotation->declined_at)
                        on {{ $quotation->declined_at->format('d M Y') }}
                    @endif
                    @if ($quotation->decline_reason)
                        · Reason: {{ $quotation->decline_reason }}
                    @endif
                </div>
            @endif

            @if ($eff === 'expired')
                <div class="p-4 bg-accent-50 border border-accent-200 text-accent-900 rounded-lg text-sm">
                    <span class="font-semibold">This quotation has expired.</span>
                    Validity ended on {{ $quotation->valid_until->format('d M Y') }}. You can still mark it accepted or convert it if the customer agrees.
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 sm:p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="text-xs font-semibold text-gray-600">Customer</div>
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
                    <div class="text-xs font-semibold text-gray-600">Quote date</div>
                    <div class="mt-1 text-gray-900">{{ $quotation->quote_date?->format('d M Y') }}</div>
                    <div class="mt-3 text-xs font-semibold text-gray-600">Valid until</div>
                    <div class="mt-1 text-gray-900">{{ $quotation->valid_until?->format('d M Y') ?? 'No expiry' }}</div>
                    @if ($quotation->reference)
                        <div class="mt-3 text-xs font-semibold text-gray-600">Customer reference</div>
                        <div class="mt-1 text-gray-900 text-sm">{{ $quotation->reference }}</div>
                    @endif
                    @if ($quotation->delivery_period)
                        <div class="mt-3 text-xs font-semibold text-gray-600">Delivery period</div>
                        <div class="mt-1 text-gray-900 text-sm">{{ $quotation->delivery_period }}</div>
                    @endif
                </div>

                <div>
                    <div class="text-xs font-semibold text-gray-600">Tax mode</div>
                    <div class="mt-1">
                        @if ($quotation->is_interstate)
                            <span class="inline-block px-2 py-0.5 bg-accent-100 text-accent-800 rounded text-xs font-medium">Inter-state (IGST)</span>
                        @else
                            <span class="inline-block px-2 py-0.5 bg-money-100 text-money-800 rounded text-xs font-medium">Intra-state (CGST + {{ $quotation->stateTaxLabel() }})</span>
                        @endif
                    </div>
                    <div class="mt-3 text-xs font-semibold text-gray-600">Grand total</div>
                    <div class="mt-1 text-2xl font-mono font-bold text-gray-900">₹{{ rupees($quotation->grand_total) }}</div>
                    <div class="text-xs text-gray-500">{{ $amountInWords }}</div>
                </div>
            </div>

            {{-- Sharing panel: email (PDF attached), WhatsApp deep-link, signed public link, and download.
                 Hidden once the quote is converted/declined since the quote is then read-only and
                 sending stale copies confuses the customer. --}}
            @if (! in_array($quotation->status, ['converted', 'declined']))
                @include('quotations.partials.share-panel', ['quotation' => $quotation])
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b">
                    <h2 class="font-semibold text-gray-900">Items</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Description</th>
                                <th class="px-3 py-2 text-left">HSN/SAC</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Discount</th>
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 sm:p-6 border-t bg-gray-50">
                    <div class="space-y-4 text-sm">
                        @if ($quotation->terms)
                            <div>
                                <div class="text-xs font-semibold text-gray-600">Terms &amp; conditions</div>
                                <div class="mt-1 whitespace-pre-line text-gray-800">{{ $quotation->terms }}</div>
                            </div>
                        @endif
                        @if ($quotation->notes)
                            <div>
                                <div class="text-xs font-semibold text-gray-600">Notes</div>
                                <div class="mt-1 whitespace-pre-line text-gray-800">{{ $quotation->notes }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="md:pl-6 space-y-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">{{ inr($quotation->subtotal) }}</span></div>
                        @if (! $quotation->is_interstate)
                            <div class="flex justify-between"><span>CGST</span><span class="font-mono">{{ inr($quotation->total_cgst) }}</span></div>
                            <div class="flex justify-between"><span>{{ $quotation->stateTaxLabel() }}</span><span class="font-mono">{{ inr($quotation->total_sgst) }}</span></div>
                        @else
                            <div class="flex justify-between"><span>IGST</span><span class="font-mono">{{ inr($quotation->total_igst) }}</span></div>
                        @endif
                        @if ((float) $quotation->round_off !== 0.0)
                            <div class="flex justify-between text-gray-600"><span>Round off</span><span class="font-mono">{{ (float) $quotation->round_off < 0 ? '-' : '' }}₹{{ inr(abs((float) $quotation->round_off)) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand total</span><span class="font-mono">₹{{ inr($quotation->grand_total) }}</span></div>
                        <p class="pt-3 text-xs text-gray-500">This is a quotation, not a tax invoice. GST is charged on the invoice, once the order is confirmed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
