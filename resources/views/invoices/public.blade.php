@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $number = $invoice->invoice_number ?: ('#' . $invoice->id);
    $docTitle = $invoice->documentTitle();
    $symbol = ($invoice->currency ?: 'INR') === 'INR' ? '₹' : e($invoice->currency) . ' ';
    $grand = $symbol . number_format((float) $invoice->grand_total, 2);
    $balance = (float) $invoice->balance;
    $isPaid = $balance <= 0.005;
    $upi = trim((string) ($c?->upi_id ?? ''));
    // UPI intent deep-link - only meaningful when money is still owed and the
    // seller published a UPI ID. am/tn are URL-encoded per the UPI spec.
    $upiLink = ($upi && ! $isPaid)
        ? 'upi://pay?pa=' . rawurlencode($upi)
            . '&pn=' . rawurlencode($c?->name ?? '')
            . '&am=' . number_format($balance, 2, '.', '')
            . '&cu=INR&tn=' . rawurlencode(($docTitle) . ' ' . $number)
        : null;
    $ogTitle = $docTitle . ' ' . $number . ' from ' . ($c?->name ?: 'a business') . ' - ' . $grand;
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Private document: never index, but DO let link unfurlers read OG tags. --}}
    <x-seo
        :title="$docTitle . ' ' . $number"
        :description="$docTitle . ' ' . $number . ' from ' . ($c?->name ?: 'a business') . ' for ' . $grand . '. View or download the PDF, and pay by UPI.'"
        :og-title="$ogTitle"
        :noindex="true" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">

<main class="flex-1 w-full max-w-lg mx-auto px-4 py-8 sm:py-12">
    {{-- Seller identity --}}
    <div class="text-center mb-6">
        <div class="text-sm text-gray-500">{{ $docTitle }} from</div>
        <h1 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $c?->name ?: 'Business' }}</h1>
    </div>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ $docTitle }} no.</div>
                <div class="text-lg font-bold text-gray-900">{{ $number }}</div>
            </div>
            @if ($isPaid)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-money-100 text-money-800 ring-1 ring-money-200">PAID</span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-accent-100 text-accent-800 ring-1 ring-accent-200">DUE</span>
            @endif
        </div>

        <div class="px-6 py-6 space-y-4">
            @if ($cust?->name)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Billed to</span>
                    <span class="font-semibold text-gray-900 text-right">{{ $cust->name }}</span>
                </div>
            @endif
            @if ($invoice->invoice_date)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Date</span>
                    <span class="font-medium text-gray-900">{{ $invoice->invoice_date->format('d M Y') }}</span>
                </div>
            @endif
            @if ($invoice->due_date && ! $isPaid)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Due date</span>
                    <span class="font-medium text-gray-900">{{ $invoice->due_date->format('d M Y') }}</span>
                </div>
            @endif

            <div class="pt-4 border-t border-gray-100 flex justify-between items-baseline">
                <span class="text-gray-500 text-sm">{{ $isPaid ? 'Total paid' : 'Amount due' }}</span>
                <span class="text-3xl font-extrabold text-gray-900">{{ $isPaid ? $grand : $symbol . number_format($balance, 2) }}</span>
            </div>
        </div>

        <div class="px-6 pb-6 space-y-3">
            <a href="{{ $pdfUrl }}"
               class="flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">
                Download PDF
            </a>
            @if ($upiLink)
                <a href="{{ $upiLink }}"
                   class="flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl bg-money-600 hover:bg-money-700 text-white font-semibold shadow-sm transition">
                    Pay {{ $symbol . number_format($balance, 2) }} via UPI
                </a>
                <p class="text-center text-xs text-gray-500">Opens your UPI app (GPay, PhonePe, Paytm…)</p>
            @endif
        </div>
    </div>

    {{-- The viral loop: the recipient is another business owner. Invite them in. --}}
    <div class="mt-8 text-center">
        <a href="{{ route('register') }}?utm_source=public_invoice&utm_medium=web&utm_campaign=recipient_cta"
           class="inline-flex items-center gap-2 text-brand-700 hover:text-brand-800 font-semibold">
            Make your own GST invoices free →
        </a>
        <p class="mt-1 text-xs text-gray-500">Powered by Apna Invoice - free GST invoicing for Indian businesses</p>
    </div>
</main>

</body>
</html>
