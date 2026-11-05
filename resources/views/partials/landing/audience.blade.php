{{--
    Who it is for: one tab per kind of business, each with what that business
    gets. $activeAudience (set by /start from ?for=) opens the visitor's own
    tab first. Without JavaScript that first panel shows on its own.
--}}
@php
    $activeAudience = $activeAudience ?? null;
    // key => [tab label, icon path, heading, points]
    $audiences = [
        'shop' => ['Shops', 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'Shops and showrooms', [
                'Counter bills on 80 mm thermal paper, or A4 for business customers.',
                'Customer pays by scanning the UPI QR printed on the bill.',
                'Stock goes down with every bill, with a reorder alert on the dashboard.',
            ]],
        'wholesale' => ['Wholesale', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'Wholesalers and distributors', [
                'IGST for sales to other states, CGST and SGST within your state.',
                'Transporter, vehicle and e-way bill number printed on the invoice.',
                'Who owes you, by 30, 60 and 90 days, with reminders by email.',
            ]],
        'services' => ['Services', 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'Service businesses and freelancers', [
                'SAC codes on each line, and quotations that turn into invoices.',
                'TDS deducted by the client recorded against the payment.',
                'Invoices sent on WhatsApp or by email with the PDF attached.',
            ]],
        'traders' => ['Traders', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'Traders and manufacturers', [
                'Purchase bills from suppliers bring stock in and give input tax credit.',
                'Each supplier bill checked against your GSTR-2B.',
                'Stock summary, purchase register and profit and loss in Excel or PDF.',
            ]],
        'ca' => ['CAs', 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            'CAs and accountants', [
                'Your own login to each client’s books, with the role they give you.',
                'GSTR-1 JSON for the portal and GSTR-3B figures from the books.',
                'Sales and purchase registers, HSN summary and aging in Excel.',
            ]],
    ];
    $first = array_key_exists((string) $activeAudience, $audiences) ? $activeAudience : 'shop';
@endphp
<section aria-labelledby="lp-who-title" class="bg-white" x-data="{ tab: @js($first) }">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">Who it is for</p>
            <h2 id="lp-who-title" class="mt-2 font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Set up for the way your business bills</h2>
        </div>

        {{-- Tabs: a row that scrolls sideways on a phone. --}}
        <div class="-mx-4 mt-7 overflow-x-auto px-4 sm:mx-0 sm:px-0" x-cloak>
            <div class="inline-flex gap-2 rounded-xl bg-gray-100 p-1.5" role="tablist" aria-label="Kind of business">
                @foreach ($audiences as $key => [$label, $icon])
                    <button type="button" role="tab" id="lp-tab-{{ $key }}" aria-controls="lp-panel-{{ $key }}"
                            :aria-selected="tab === @js($key)" :tabindex="tab === @js($key) ? 0 : -1"
                            @click="tab = @js($key)"
                            @keydown.arrow-right.prevent="$el.nextElementSibling?.click(); $el.nextElementSibling?.focus()"
                            @keydown.arrow-left.prevent="$el.previousElementSibling?.click(); $el.previousElementSibling?.focus()"
                            :class="tab === @js($key) ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-700 hover:text-gray-900'"
                            class="inline-flex min-h-[44px] items-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @foreach ($audiences as $key => [$label, $icon, $heading, $points])
            <div id="lp-panel-{{ $key }}" role="tabpanel" aria-labelledby="lp-tab-{{ $key }}"
                 x-show="tab === @js($key)" @if ($key !== $first) x-cloak @endif
                 class="mt-6 grid gap-6 rounded-2xl bg-gradient-to-br from-brand-50 to-white p-6 ring-1 ring-brand-100 sm:p-8 lg:grid-cols-12 lg:items-center">
                <div class="lg:col-span-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-white shadow-brand" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl font-bold text-gray-900">{{ $heading }}</h3>
                    @guest
                        <a href="#signup" data-lp-signup class="mt-4 inline-flex min-h-[44px] items-center gap-1.5 rounded font-semibold text-brand-700 hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                            Start free
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>
                        </a>
                    @endguest
                </div>
                <ul class="grid gap-3 lg:col-span-8">
                    @foreach ($points as $point)
                        <li class="flex gap-3 rounded-xl bg-white p-4 text-[15px] leading-relaxed text-gray-800 shadow-sm ring-1 ring-gray-200">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>
