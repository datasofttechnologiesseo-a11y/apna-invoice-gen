@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $publicUrl = \App\Http\Controllers\InvoiceShareController::makePublicUrl($invoice);

    $docTitle = $invoice->documentTitle();
    $defaultSubject = ($c->name ?? 'Invoice') . ' · ' . $docTitle . ' ' . ($invoice->invoice_number ?? '#' . $invoice->id);

    // Payment-status-aware message body (paid → thank you, partial → balance,
    // overdue → days, future → due-date language). Single source of truth on
    // the Invoice model: the same text is used by WhatsApp, the email body and
    // the WhatsApp reminder channel, so customers never see contradictory copy.
    $defaultBody = $invoice->shareMessageText('share');
    // wa.me link with the number normalised (a bare 10-digit mobile gets 91).
    $waLink = $invoice->whatsAppShareLink('share');

    $btn = 'inline-flex items-center justify-center gap-1.5 h-10 px-3 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition';
@endphp

<div x-data="{ open: {{ $errors->hasAny(['to', 'cc', 'subject', 'body']) ? "'email'" : 'null' }} }" class="bg-white rounded-xl ring-1 ring-gray-200">
    <div class="p-4">
        <h2 class="font-semibold text-gray-900">Share</h2>
        <p class="mt-0.5 text-xs text-gray-600">Email with the PDF attached, send on WhatsApp, or copy the public link (valid 30 days).</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="{{ $btn }}" title="Send on WhatsApp to {{ $cust?->phone }}">
                    <svg class="w-4 h-4 text-[#128C7E]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
            @else
                <a href="{{ route('customers.edit', $invoice->customer_id) }}" class="{{ $btn }} text-gray-600" title="Add the customer's mobile number to share on WhatsApp">
                    Add mobile
                </a>
            @endif

            <button type="button" @click="open = open === 'email' ? null : 'email'" :aria-expanded="(open === 'email').toString()" class="{{ $btn }}">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email
            </button>

            <button type="button" x-data="{ copied: false }"
                    @click="copyText(@js($publicUrl)).then(ok => { if (ok) { copied = true; setTimeout(() => copied = false, 2000) } })"
                    class="{{ $btn }}">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
            </button>

            {{-- Native OS share sheet: reaches SMS, Telegram, email and any
                 WhatsApp contact, even when the customer has no saved number. --}}
            <button type="button"
                    x-data="{ share: { title: @js($defaultSubject), text: @js($defaultBody), url: @js($publicUrl) }, copied: false }"
                    @click="navigator.share ? navigator.share(share).catch(() => {}) : copyText(share.text + '\n' + share.url).then(ok => { if (ok) { copied = true; setTimeout(() => copied = false, 2000) } })"
                    class="{{ $btn }}">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                <span x-text="copied ? 'Copied' : 'More…'">More…</span>
            </button>
        </div>
    </div>

    <div x-show="open === 'email'" x-cloak class="p-4 border-t border-gray-100">
        <form method="POST" action="{{ route('invoices.share.email', $invoice) }}" class="space-y-3">
            @csrf
            <div>
                <label for="share-to" class="block text-sm font-medium text-gray-700">To *</label>
                <input id="share-to" type="email" name="to" required value="{{ old('to', $cust->email) }}" aria-describedby="to-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                <x-input-error :messages="$errors->get('to')" for="to" class="mt-1" />
            </div>
            <div>
                <label for="share-cc" class="block text-sm font-medium text-gray-700">CC</label>
                <input id="share-cc" type="text" name="cc" value="{{ old('cc') }}" placeholder="accounts@…, owner@…" aria-describedby="cc-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                <p class="mt-1 text-xs text-gray-500">Separate addresses with commas.</p>
                <x-input-error :messages="$errors->get('cc')" for="cc" class="mt-1" />
            </div>
            <div>
                <label for="share-subject" class="block text-sm font-medium text-gray-700">Subject *</label>
                <input id="share-subject" type="text" name="subject" required value="{{ old('subject', $defaultSubject) }}" aria-describedby="subject-error" class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                <x-input-error :messages="$errors->get('subject')" for="subject" class="mt-1" />
            </div>
            <div>
                <label for="share-body" class="block text-sm font-medium text-gray-700">Message *</label>
                <textarea id="share-body" name="body" rows="7" required aria-describedby="body-error" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('body', $defaultBody) }}</textarea>
                <x-input-error :messages="$errors->get('body')" for="body" class="mt-1" />
            </div>
            <p class="text-xs text-gray-500">The invoice PDF is attached.</p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = null" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-brand-700 hover:bg-brand-800 text-white">Send email</button>
            </div>
        </form>
    </div>
</div>
