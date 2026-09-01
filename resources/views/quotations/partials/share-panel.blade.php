@php
    $c = $quotation->company;
    $cust = $quotation->customer;
    $publicUrl = \App\Http\Controllers\QuotationShareController::makePublicUrl($quotation);
    $number = $quotation->quote_number ?: 'Draft #' . $quotation->id;

    $waDigits = $cust?->phone ? preg_replace('/[^0-9]/', '', $cust->phone) : '';
    $defaultSubject = ($c->name ?? 'Quotation') . ' · Quotation ' . $number;

    // Status-aware message body - accepted/declined/expired branches matter
    // here because customers don't want a "find attached" message after
    // they've already declined the quote. See Quotation::shareMessageText().
    $defaultBody = $quotation->shareMessageText();
    $waLink = $quotation->whatsAppShareLink() ?? '';
@endphp

<div x-data="{ open: null }" class="bg-white shadow sm:rounded-lg">
    <div class="px-5 py-3 border-b flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Share this quotation</h3>
            <div class="text-xs text-gray-500">
                Email (PDF attached), WhatsApp, or share a secure public link (valid 30 days).
                @if ($quotation->isDraft())
                    <span class="ml-1 inline-block text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold uppercase tracking-wider">Sending will assign a quote number</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="open = open === 'email' ? null : 'email'"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-brand-600 text-white text-sm font-semibold rounded hover:bg-brand-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email
            </button>

            @if ($waDigits && strlen($waDigits) >= 10)
                <a href="{{ $waLink }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-[#25D366] text-white text-sm font-semibold rounded hover:bg-[#1ebe5b]">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
            @else
                <span class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gray-100 text-gray-500 text-sm rounded cursor-not-allowed" title="Add a mobile number to the customer to enable WhatsApp sharing">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </span>
            @endif

            <span class="inline-flex rounded overflow-hidden shadow-sm">
                <a href="{{ route('quotations.pdf', $quotation) }}"
                   class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gray-800 text-white text-sm font-semibold hover:bg-gray-900"
                   title="Ink-saver download - black on white for printing">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Download PDF
                </a>
                <a href="{{ route('quotations.pdf', $quotation, false) . '?color=1' }}"
                   class="inline-flex items-center justify-center min-h-[40px] px-2.5 py-2 bg-gray-700 text-white hover:bg-gray-800 border-l border-gray-600"
                   title="Download full-colour PDF (uses more ink)" aria-label="Download full-colour PDF (uses more ink)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                </a>
            </span>

            <button type="button" onclick="window.print()" class="print-keep inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>

            <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview public view
            </a>

            <button type="button" x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3 py-2 bg-gray-100 text-gray-800 text-sm font-semibold rounded hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span x-text="copied ? 'Copied!' : 'Copy link'"></span>
            </button>
        </div>
    </div>

    <div x-show="open === 'email'" x-cloak class="p-5 border-t">
        <form method="POST" action="{{ route('quotations.share.email', $quotation) }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">To *</label>
                    <input type="email" name="to" required value="{{ old('to', $cust?->email) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">CC (comma-separated)</label>
                    <input type="text" name="cc" value="{{ old('cc') }}" placeholder="accounts@… , owner@…" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Subject *</label>
                <input type="text" name="subject" required value="{{ old('subject', $defaultSubject) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Message *</label>
                <textarea name="body" rows="7" required class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">{{ old('body', $defaultBody) }}</textarea>
            </div>
            <div class="text-xs text-gray-500">The quotation PDF is attached automatically. A "View online" button is included in the email so the customer can open the public link.</div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = null" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded">Send email</button>
            </div>
        </form>
    </div>
</div>
