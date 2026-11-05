{{--
    What it does, four areas, each beside a real screen from the sample
    business. Sides alternate on desktop. Images load lazily: none of them is
    in the first screen.

    "Start free" links scroll to the sign-up form in the hero (#signup).
--}}
@php
    $usps = [
        [
            'id' => 'lp-billing', 'eyebrow' => 'Billing',
            'title' => 'Bill on your phone and send it on WhatsApp',
            'lead' => 'A hardware shop in Pune can bill a contractor at the counter and send the invoice to his WhatsApp before the goods are loaded.',
            'points' => [
                'Save and issue gives the bill the next number in your series, like SE/26-27/0001.',
                'WhatsApp opens with the message and the invoice link ready. Email goes with the PDF attached.',
                'Add your UPI ID once. The QR on the bill lets the customer scan and pay the balance.',
            ],
            'screen' => ['m-invoice-view.webp', true, null, 'An issued invoice on a phone with WhatsApp, email and UPI payment options'],
        ],
        [
            'id' => 'lp-gst', 'eyebrow' => 'GST returns',
            'title' => 'GST returns prepared from your own books',
            'lead' => 'Every invoice and purchase bill you enter feeds the return. Nothing is typed in twice.',
            'points' => [
                'GSTR-1 with every table, a JSON file for the GST portal and Excel for the offline tool.',
                'Upload the GSTR-2B file and see each supplier bill as matched, mismatched or missing.',
                'GSTR-3B tables 3.1, 4 and 6.1, with credit set off in the Rule 88A order.',
            ],
            'note' => 'You or your CA file the return on the GST portal.',
            'screen' => ['gstr1.webp', false, 'apnainvoice.com/finance/gstr1', 'GSTR-1 summary for October with B2B, B2C and HSN tables'],
        ],
        [
            'id' => 'lp-money', 'eyebrow' => 'Money in and out',
            'title' => 'Know who has paid and who still owes you',
            'lead' => 'Upload the statement you download from net banking. Most lines match on their own.',
            'points' => [
                'Excel or CSV statements from HDFC, SBI, ICICI, Axis and other banks.',
                'Each bank line is matched to a receipt, supplier payment or expense of the same amount.',
                'Part payments, TDS deducted by the customer and aging of what is due, by 30, 60 and 90 days.',
            ],
            'screen' => ['bank.webp', false, 'apnainvoice.com/bank', 'Bank statement lines matched to receipts and payments, with one line left to review'],
        ],
        [
            'id' => 'lp-stock', 'eyebrow' => 'Stock and reports',
            'title' => 'Stock that moves with every bill',
            'lead' => 'Turn on stock for the items you keep and leave it off for services.',
            'points' => [
                'Issued invoices take stock out, purchases bring it in, returns put it back.',
                'Items at or below their reorder level show on the dashboard.',
                'Profit and loss, sales and purchase registers and the stock summary in Excel, PDF or print.',
            ],
            'screen' => ['stock.webp', false, 'apnainvoice.com/reports/stock-summary', 'Stock summary with opening, in, out and closing quantity for each item'],
        ],
    ];
@endphp
<div id="features">
    @foreach ($usps as $i => $u)
        @php [$src, $mobile, $addr, $alt] = $u['screen']; $flip = $i % 2 === 1; @endphp
        <section id="{{ $u['id'] }}" aria-labelledby="{{ $u['id'] }}-title"
                 @class(['scroll-mt-20', 'border-y border-gray-200 bg-gray-50' => $flip, 'bg-white' => ! $flip])>
            <div class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-14 lg:px-8">
                <div @class(['lg:col-span-5', 'lg:order-2' => $flip])>
                    <p class="text-sm font-semibold text-brand-700">{{ $u['eyebrow'] }}</p>
                    <h2 id="{{ $u['id'] }}-title" class="mt-2 font-display text-2xl font-extrabold leading-tight tracking-tight text-gray-900 md:text-3xl">{{ $u['title'] }}</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">{{ $u['lead'] }}</p>
                    <ul class="mt-6 space-y-3 text-[15px] leading-relaxed text-gray-800">
                        @foreach ($u['points'] as $point)
                            <li class="flex gap-3">
                                <svg class="mt-1 h-5 w-5 shrink-0 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (! empty($u['note']))
                        <p class="mt-4 border-l-2 border-accent-500 pl-3 text-sm text-gray-700">{{ $u['note'] }}</p>
                    @endif
                    @guest
                        <a href="#signup" data-lp-signup class="mt-6 inline-flex min-h-[44px] items-center gap-1.5 rounded font-semibold text-brand-700 hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                            Start free
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>
                        </a>
                    @endguest
                </div>
                <div @class(['lg:col-span-7', 'lg:order-1' => $flip])>
                    @if ($mobile)
                        <x-screen :src="$src" :mobile="true" :alt="$alt" class="!max-w-[260px]" />
                    @else
                        <x-screen :src="$src" :url="$addr" :alt="$alt" />
                    @endif
                </div>
            </div>
        </section>
    @endforeach
</div>
