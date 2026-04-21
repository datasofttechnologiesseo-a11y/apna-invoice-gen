<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            @page { margin: 12mm; }
            /* Ink-saver: strip coloured fills and tinted backgrounds so the
               printer uses primarily black ink. Keep structural borders. */
            * {
                -webkit-print-color-adjust: economy !important;
                print-color-adjust: economy !important;
            }
            .invoice-doc,
            .invoice-doc [class*="bg-"],
            .invoice-doc [style*="background"] {
                background: #ffffff !important;
                background-color: #ffffff !important;
                background-image: none !important;
            }
            .invoice-doc * {
                color: #000 !important;
            }
            .invoice-doc .text-gray-400,
            .invoice-doc .text-gray-500,
            .invoice-doc .text-gray-600 {
                color: #444 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<div class="no-print p-4 text-right">
    <button onclick="window.print()" class="px-4 py-2 bg-brand-600 text-white rounded">Print / Save as PDF</button>
    <a href="{{ route('invoices.show', $invoice) }}" class="ml-2 text-gray-600 underline">Back</a>
</div>
<div class="max-w-4xl mx-auto bg-white shadow my-4">
    @include('invoices.partials.document', ['invoice' => $invoice, 'amountInWords' => $amountInWords])
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
