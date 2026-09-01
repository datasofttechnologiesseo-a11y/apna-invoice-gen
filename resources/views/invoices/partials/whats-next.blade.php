{{--
    "What's next?" engagement card - sits at the top of every invoice show page.

    Why: Indian SME users land on this page after every invoice action and were
    previously left to figure out which of the dozen buttons in the header was
    the "right" next thing to do. This card explicitly nominates the next 2-3
    actions ordered by likelihood, with chunky thumb-targets.

    States rendered (in priority order, only ONE shows at a time):
      1. cancelled  → suppressed (red banner already explains)
      2. draft      → "Issue when ready" (move forward)
      3. paid       → "Fully paid, well done - next sale?" (close the loop)
      4. issued + balance > 0 → "Share & track payment" (most common)

    Layout: branded gradient header strip + grid of 2-4 action tiles.
    Each tile is a real <a> or <button> so keyboard users can Tab through.
--}}
@php
    $cust = $invoice->customer;
    $waLink = $invoice->whatsAppShareLink('share');
    $hasPhone = ! empty($cust?->phone);
    $hasEmail = ! empty($cust?->email);
    $isPaid = (float) $invoice->balance <= 0 && (float) $invoice->paid_amount > 0;
    $isDraft = $invoice->isDraft();
    $isCancelled = $invoice->isCancelled();
@endphp

@unless ($isCancelled)
<div class="bg-white rounded-xl shadow-card ring-1 ring-brand-100 overflow-hidden">
    {{-- Header strip - gradient gives the card weight without screaming.
         Amber accent on the icon for Indian-flag warmth without being kitsch. --}}
    <div class="px-5 py-3 bg-gradient-to-r from-brand-50 to-accent-50 border-b border-brand-100 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-accent-100 text-accent-700">
                @if ($isPaid)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @elseif ($isDraft)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                @endif
            </span>
            <div>
                <div class="text-xs uppercase tracking-widest font-bold text-brand-700">
                    @if ($isPaid)
                        Fully paid - well done!
                    @elseif ($isDraft)
                        Draft saved
                    @else
                        What's next?
                    @endif
                </div>
                <div class="text-sm text-gray-700">
                    @if ($isPaid)
                        {{ $cust?->name ? "Receipt ready for {$cust->name}." : 'Receipt is ready.' }}
                        Carry the momentum - book the next sale.
                    @elseif ($isDraft)
                        Issue the invoice to lock the number and share it with {{ $cust?->name ?? 'the customer' }}.
                    @else
                        Invoice issued. Share with the customer and track payment until it's settled.
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Action tiles. We render up to 4 - most likely action first.
         Mobile: stacks; ≥sm: 2-col grid; ≥md: 4-col when 4 actions exist. --}}
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 @if (! $isPaid && ! $isDraft) md:grid-cols-4 @else md:grid-cols-3 @endif gap-3">

        @if ($isDraft)
            {{-- DRAFT state: emphasise "Issue" so the user moves the doc forward. --}}
            <x-confirm-form
                :action="route('invoices.finalize', $invoice)"
                method="POST"
                title="Issue this invoice?"
                message="Issuing assigns the next invoice number and locks the amounts, line items, and customer. Notes, terms, due date, and transporter details stay editable."
                confirm-label="Issue invoice"
                confirm-class="bg-brand-700 hover:bg-brand-800"
                tone="default">
                <button type="button" class="w-full group flex items-center gap-3 px-4 py-3 bg-brand-700 hover:bg-brand-800 text-white rounded-lg shadow-sm text-left transition">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white/15 group-hover:bg-white/25 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-semibold leading-tight">Issue invoice</span>
                        <span class="block text-xs text-white/80">Lock the number, ready to share</span>
                    </span>
                </button>
            </x-confirm-form>

            <a href="{{ route('invoices.edit', $invoice) }}" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-brand-300 hover:bg-brand-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-100 group-hover:text-brand-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">Continue editing</span>
                    <span class="block text-xs text-gray-500">Add line items, change customer</span>
                </span>
            </a>

            <a href="{{ route('invoices.pdf', $invoice) }}" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-brand-300 hover:bg-brand-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-100 group-hover:text-brand-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h11l5 5v7a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">Preview as PDF</span>
                    <span class="block text-xs text-gray-500">See how it'll look</span>
                </span>
            </a>

        @elseif ($isPaid)
            {{-- PAID state: close the loop, point to the next sale. --}}
            <a href="{{ route('invoices.create') }}" class="group flex items-center gap-3 px-4 py-3 bg-accent-600 hover:bg-accent-700 text-white rounded-lg shadow-sm text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white/15 group-hover:bg-white/25 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold leading-tight">New invoice</span>
                    <span class="block text-xs text-white/80">Book the next sale</span>
                </span>
            </a>

            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-[#25D366]/60 hover:bg-[#25D366]/10 rounded-lg text-left transition">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[#25D366]/15 text-[#075E54]">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-semibold text-gray-900 leading-tight">Send "thank you"</span>
                        <span class="block text-xs text-gray-500">via WhatsApp</span>
                    </span>
                </a>
            @endif

            <a href="{{ route('customers.ledger', $invoice->customer_id) }}" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-brand-300 hover:bg-brand-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-100 group-hover:text-brand-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0h6m-9 4h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">{{ $cust?->name ?? 'Customer' }} ledger</span>
                    <span class="block text-xs text-gray-500">All bills & payments</span>
                </span>
            </a>

        @else
            {{-- ISSUED + balance > 0 state: the common case. Share → Record → Repeat. --}}

            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="group flex items-center gap-3 px-4 py-3 bg-[#0f7540] hover:bg-[#0c5f34] text-white rounded-lg shadow-sm text-left transition">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white/20 group-hover:bg-white/30 transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-semibold leading-tight">Send on WhatsApp</span>
                        <span class="block text-xs text-white/85">To {{ $cust->phone }}</span>
                    </span>
                </a>
            @else
                <a href="#share-customer-phone" onclick="document.querySelector('a[href*=\'customers/{{ $invoice->customer_id }}/edit\']')?.click(); return false;" class="group flex items-center gap-3 px-4 py-3 bg-gray-100 text-gray-600 rounded-lg text-left cursor-pointer hover:bg-gray-200 transition" title="Add a mobile number to enable WhatsApp share">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-200">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-semibold leading-tight">Add mobile number</span>
                        <span class="block text-xs">…to unlock WhatsApp share</span>
                    </span>
                </a>
            @endif

            <a href="#record-payment" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-money-200 hover:ring-money-400 hover:bg-money-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-money-100 text-money-700 group-hover:bg-money-600 group-hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">Record Receipt</span>
                    <span class="block text-xs text-gray-500">₹{{ inr($invoice->balance) }} balance</span>
                </span>
            </a>

            <a href="{{ route('invoices.pdf', $invoice) }}" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-brand-300 hover:bg-brand-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-100 group-hover:text-brand-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h11l5 5v7a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">Download PDF</span>
                    <span class="block text-xs text-gray-500">For email / print</span>
                </span>
            </a>

            <a href="{{ route('invoices.create') }}" class="group flex items-center gap-3 px-4 py-3 bg-white ring-1 ring-gray-200 hover:ring-accent-300 hover:bg-accent-50 rounded-lg text-left transition">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-accent-100 text-accent-700 group-hover:bg-accent-600 group-hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </span>
                <span class="flex-1">
                    <span class="block font-semibold text-gray-900 leading-tight">New invoice</span>
                    <span class="block text-xs text-gray-500">Keep the momentum</span>
                </span>
            </a>
        @endif
    </div>
</div>
@endunless
