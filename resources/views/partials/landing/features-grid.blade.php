{{--
    Everything in the free plan at a glance: one tile per area, each a plain
    statement of what is in the product today.
--}}
@php
    // [icon path, tint, title, text]
    $tiles = [
        ['M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'bg-brand-100 text-brand-800',
            'GST invoices', 'Tax invoice, bill of supply or plain invoice, picked from your GST registration.'],
        ['M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'bg-sky-100 text-sky-800',
            'Quotations', 'Send a quote, then turn the accepted one into an invoice.'],
        ['M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z', 'bg-rose-100 text-rose-800',
            'Credit notes', 'Returns and price corrections under Section 34, with stock put back.'],
        ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'bg-money-100 text-money-800',
            'UPI QR on the bill', 'Your UPI ID as a QR for the balance due. The customer scans and pays.'],
        ['M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'bg-accent-100 text-accent-800',
            'Stock', 'Moves with every bill and purchase, with counts and reorder alerts.'],
        ['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'bg-violet-100 text-violet-800',
            'Purchases and expenses', 'Supplier bills, payments made and expenses, with input tax credit.'],
        ['M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12', 'bg-brand-100 text-brand-800',
            'GSTR-1, 2B and 3B', 'JSON for the portal, 2B matching and the 3B tables from your books.'],
        ['M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z', 'bg-sky-100 text-sky-800',
            'Bank matching', 'Statement lines from your bank matched to receipts and payments.'],
        ['M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'bg-rose-100 text-rose-800',
            'Payment reminders', 'Polite reminders by email for invoices past their due date.'],
        ['M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'bg-money-100 text-money-800',
            'Reports', 'Profit and loss, registers, aging and HSN summary in Excel or PDF.'],
        ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'bg-accent-100 text-accent-800',
            'Team and CA access', 'Logins for staff and your CA, each with its own role.'],
        ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12', 'bg-violet-100 text-violet-800',
            'Import and backup', 'Bring customers, vendors and items from Excel. Download everything any time.'],
    ];
@endphp
<section id="everything" aria-labelledby="lp-all-title" class="scroll-mt-20 border-y border-gray-200 bg-gray-50">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">All in one place</p>
            <h2 id="lp-all-title" class="mt-2 font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Billing, GST, stock and money, in one app</h2>
            <p class="mt-3 leading-relaxed text-gray-600">Works in the browser on a phone or a computer. Add it to your home screen and it opens like an app.</p>
        </div>
        <ul class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($tiles as [$icon, $tint, $title, $text])
                <li class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-card hover:ring-brand-200">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $tint }}" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-base font-bold text-gray-900">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
