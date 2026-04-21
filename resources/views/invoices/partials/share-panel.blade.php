@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $publicUrl = \App\Http\Controllers\InvoiceShareController::makePublicUrl($invoice);

    // WhatsApp deep link — uses wa.me (works from phone tap or desktop WhatsApp Web).
    // Strip non-digit chars from the phone so "+91 98765 43210" becomes "919876543210".
    $waDigits = $cust?->phone ? preg_replace('/[^0-9]/', '', $cust->phone) : '';
    $defaultSubject = ($c->name ?? 'Invoice') . ' · Invoice ' . ($invoice->invoice_number ?? '#' . $invoice->id);

    $defaultBody = "Hi " . ($cust->name ?? 'there') . ",\n\n"
        . "Please find attached invoice " . ($invoice->invoice_number ?? '') . " "
        . "dated " . $invoice->invoice_date?->format('d M Y') . " for ₹" . number_format((float) $invoice->grand_total, 2) . ".\n\n"
        . ((float) $invoice->balance > 0
            ? "Balance due: ₹" . number_format((float) $invoice->balance, 2) . (
                $invoice->due_date ? " (due by " . $invoice->due_date->format('d M Y') . ")" : ""
              ) . ".\n\n"
            : "Thank you for your prompt payment — balance is clear.\n\n"
          )
        . ($c->upi_id ? "Pay via UPI: " . $c->upi_id . "\n" : "")
        . "Let me know if you have any questions.\n\n"
        . "Warm regards,\n"
        . $c->name;

    $waText = "Invoice " . ($invoice->invoice_number ?? '') . " from " . ($c->name ?? '')
        . "\nAmount: ₹" . number_format((float) $invoice->grand_total, 2)
        . ((float) $invoice->balance > 0 ? "\nBalance due: ₹" . number_format((float) $invoice->balance, 2) : "")
        . "\n\nView & download: " . $publicUrl;
    $waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waText);
@endphp

<div x-data="{ open: null }" class="bg-white shadow sm:rounded-lg">
    <div class="px-5 py-3 border-b flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Share this invoice</h3>
            <div class="text-xs text-gray-500">Email, WhatsApp, or share a secure public link (valid 30 days).</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="open = open === 'email' ? null : 'email'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-600 text-white text-sm font-semibold rounded hover:bg-brand-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email
            </button>

            @if ($waDigits && strlen($waDigits) >= 10)
                <a href="{{ $waLink }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#25D366] text-white text-sm font-semibold rounded hover:bg-[#1ebe5b]">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-400 text-sm rounded cursor-not-allowed" title="Add a mobile number to the customer to enable WhatsApp sharing">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </span>
            @endif

            <button type="button" x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-800 text-sm font-semibold rounded hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span x-text="copied ? 'Copied!' : 'Copy link'"></span>
            </button>

            <a href="https://twitter.com/intent/tweet?text={{ urlencode('Invoice from ' . $c->name) }}&url={{ urlencode($publicUrl) }}" target="_blank" rel="noopener" class="p-1.5 text-gray-500 hover:text-[#1DA1F2]" title="Share on Twitter/X" aria-label="Share on Twitter/X">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.643 4.937c-.835.37-1.732.62-2.675.733.962-.576 1.7-1.49 2.048-2.578-.9.534-1.897.922-2.958 1.13-.85-.904-2.06-1.47-3.4-1.47-2.572 0-4.658 2.086-4.658 4.66 0 .364.042.718.12 1.06-3.873-.195-7.304-2.05-9.602-4.868-.4.69-.63 1.49-.63 2.342 0 1.616.823 3.043 2.072 3.878-.764-.025-1.482-.234-2.11-.583v.06c0 2.257 1.605 4.14 3.737 4.568-.392.106-.803.162-1.227.162-.3 0-.593-.028-.877-.082.593 1.85 2.313 3.198 4.352 3.234-1.595 1.25-3.604 1.995-5.786 1.995-.376 0-.747-.022-1.112-.065 2.062 1.323 4.51 2.093 7.14 2.093 8.57 0 13.255-7.098 13.255-13.254 0-.2-.005-.402-.014-.602.91-.658 1.7-1.477 2.323-2.41z"/></svg>
            </a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($publicUrl) }}" target="_blank" rel="noopener" class="p-1.5 text-gray-500 hover:text-[#0077B5]" title="Share on LinkedIn" aria-label="Share on LinkedIn">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($publicUrl) }}" target="_blank" rel="noopener" class="p-1.5 text-gray-500 hover:text-[#1877F2]" title="Share on Facebook" aria-label="Share on Facebook">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
        </div>
    </div>

    <div x-show="open === 'email'" x-cloak class="p-5 border-t">
        <form method="POST" action="{{ route('invoices.share.email', $invoice) }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold">To *</label>
                    <input type="email" name="to" required value="{{ old('to', $cust->email) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
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
            <div class="text-xs text-gray-500">The invoice PDF is attached automatically.</div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = null" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded">Send email</button>
            </div>
        </form>
    </div>
</div>
