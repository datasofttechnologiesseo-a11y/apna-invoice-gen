@php
    $c = $quotation->company;
    $cust = $quotation->customer;
    $number = $quotation->displayNumber();
    $symbol = ($quotation->currency ?: 'INR') === 'INR' ? '₹' : e($quotation->currency) . ' ';
    $grand = $symbol . number_format((float) $quotation->grand_total, 2);
    $status = $quotation->effectiveStatus();
    $ogTitle = 'Quotation ' . $number . ' from ' . ($c?->name ?: 'a business') . ' — ' . $grand;
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo
        :title="'Quotation ' . $number"
        :description="'Quotation ' . $number . ' from ' . ($c?->name ?: 'a business') . ' for ' . $grand . '. View or download the PDF.'"
        :og-title="$ogTitle"
        :noindex="true" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">

<main class="flex-1 w-full max-w-lg mx-auto px-4 py-8 sm:py-12">
    <div class="text-center mb-6">
        <div class="text-sm text-gray-500">Quotation from</div>
        <h1 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $c?->name ?: 'Business' }}</h1>
    </div>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Quotation no.</div>
                <div class="text-lg font-bold text-gray-900">{{ $number }}</div>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-brand-100 text-brand-800 ring-1 ring-brand-200 capitalize">{{ $status }}</span>
        </div>

        <div class="px-6 py-6 space-y-4">
            @if ($cust?->name)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Prepared for</span>
                    <span class="font-semibold text-gray-900 text-right">{{ $cust->name }}</span>
                </div>
            @endif
            @if ($quotation->quote_date)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Date</span>
                    <span class="font-medium text-gray-900">{{ $quotation->quote_date->format('d M Y') }}</span>
                </div>
            @endif
            @if ($quotation->valid_until)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Valid until</span>
                    <span class="font-medium text-gray-900">{{ $quotation->valid_until->format('d M Y') }}</span>
                </div>
            @endif

            <div class="pt-4 border-t border-gray-100 flex justify-between items-baseline">
                <span class="text-gray-500 text-sm">Quoted total</span>
                <span class="text-3xl font-extrabold text-gray-900">{{ $grand }}</span>
            </div>
        </div>

        <div class="px-6 pb-6">
            <a href="{{ $pdfUrl }}"
               class="flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">
                Download PDF
            </a>
        </div>
    </div>

    <div class="mt-8 text-center">
        <a href="{{ route('register') }}?utm_source=public_quotation&utm_medium=web&utm_campaign=recipient_cta"
           class="inline-flex items-center gap-2 text-brand-700 hover:text-brand-800 font-semibold">
            Make your own quotes & GST invoices free →
        </a>
        <p class="mt-1 text-xs text-gray-400">Powered by Apna Invoice — free GST invoicing for Indian businesses</p>
    </div>
</main>

</body>
</html>
