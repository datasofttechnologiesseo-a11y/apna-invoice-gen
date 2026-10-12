@php
    $c = $quotation->company;
    $cust = $quotation->customer;
    $publicUrl = \App\Http\Controllers\QuotationShareController::makePublicUrl($quotation);
    $number = $quotation->quote_number ?: 'Draft #' . $quotation->id;

    $defaultSubject = ($c->name ?? 'Quotation') . ' · Quotation ' . $number;

    // Status-aware message body - accepted/declined/expired branches matter
    // here because customers don't want a "find attached" message after
    // they've already declined the quote. See Quotation::shareMessageText().
    $defaultBody = $quotation->shareMessageText();
    // wa.me link with the number normalised (a bare 10-digit mobile gets 91).
    $waLink = $quotation->whatsAppShareLink();
    $btn = 'inline-flex items-center justify-center gap-1.5 h-10 px-3 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition';
@endphp

<div x-data="{ open: {{ $errors->hasAny(['to', 'cc', 'subject', 'body']) ? "'email'" : 'null' }} }" class="bg-white rounded-xl ring-1 ring-gray-200">
    <div class="p-4 sm:px-5 flex items-start justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-semibold text-gray-900">Share</h2>
            <p class="text-xs text-gray-600">Email with the PDF attached, send on WhatsApp, or copy the public link (valid 30 days).</p>
            @if ($quotation->isDraft())
                <p class="mt-1 text-xs text-accent-800">Emailing it assigns the quote number.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="open = open === 'email' ? null : 'email'" :aria-expanded="(open === 'email').toString()" class="{{ $btn }}">Email</button>

            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="{{ $btn }}">
                    <svg class="w-4 h-4 text-[#128C7E]" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
            @else
                <a href="{{ route('customers.edit', $quotation->customer_id) }}" class="{{ $btn }} text-gray-600" title="Add the customer's mobile number to share on WhatsApp">Add mobile</a>
            @endif

            <button type="button" x-data="{ copied: false }"
                    @click="copyText(@js($publicUrl)).then(ok => { if (ok) { copied = true; setTimeout(() => copied = false, 2000) } })"
                    class="{{ $btn }}">
                <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
            </button>

            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="{{ $btn }}">View public page</a>
            <a href="{{ route('quotations.pdf', $quotation, false) . '?color=1' }}" class="{{ $btn }}" title="Full-colour PDF (uses more ink)">Colour PDF</a>
        </div>
    </div>

    <div x-show="open === 'email'" x-cloak class="p-4 sm:p-5 border-t border-gray-100">
        <form method="POST" action="{{ route('quotations.share.email', $quotation) }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label for="qshare-to" class="block text-sm font-medium text-gray-700">To *</label>
                    <input id="qshare-to" type="email" name="to" required value="{{ old('to', $cust?->email) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                </div>
                <div>
                    <label for="qshare-cc" class="block text-sm font-medium text-gray-700">CC (separate with commas)</label>
                    <input id="qshare-cc" type="text" name="cc" value="{{ old('cc') }}" placeholder="accounts@… , owner@…" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                </div>
            </div>
            <div>
                <label for="qshare-subject" class="block text-sm font-medium text-gray-700">Subject *</label>
                <input id="qshare-subject" type="text" name="subject" required value="{{ old('subject', $defaultSubject) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
            </div>
            <div>
                <label for="qshare-body" class="block text-sm font-medium text-gray-700">Message *</label>
                <textarea id="qshare-body" name="body" rows="7" required class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">{{ old('body', $defaultBody) }}</textarea>
            </div>
            <div class="text-xs text-gray-500">The email includes the quotation PDF and a link to view it online.</div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = null" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white">Send email</button>
            </div>
        </form>
    </div>
</div>
