@props(['variant' => 'full'])

@if ($variant === 'full')
    {{-- ======================= FULL FOOTER =======================
         Light and quiet, so it never competes with a page's own closing
         section. What it carries: who we are and how to reach a person
         (WhatsApp and phone, the highest-trust signal for this audience),
         the link columns, and one "Made in India" line.

         The old dark block with glows, a dot pattern and a "newsletter" card
         was removed: there is no newsletter yet, the card only linked to the
         contact form, and "Contact us" now sits in the Company column.

         The Terms / Privacy / Refunds / Cookies chips stay out of the footer
         at the owner's request. The policy pages link to each other from
         their own side column. --}}
    <footer class="bg-gray-50 border-t border-gray-200 text-gray-600">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
            <div class="grid grid-cols-2 md:grid-cols-12 gap-x-8 gap-y-10">
                {{-- Brand and contact --}}
                <div class="col-span-2 md:col-span-4">
                    <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="inline-block">
                        <x-brand-logo class="h-9 w-auto block" />
                    </a>
                    <p class="mt-4 text-sm leading-relaxed max-w-xs">
                        GST billing, purchases, stock and returns for Indian businesses. Free during beta.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team, I need help with…') }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 min-h-[44px] px-3.5 bg-[#0f7540] hover:bg-[#0c5f34] text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
                            <svg class="w-[18px] h-[18px] shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            WhatsApp
                        </a>
                        <a href="tel:{{ config('seo.contact.phone_e164') }}"
                           class="inline-flex items-center gap-2 min-h-[44px] px-3.5 bg-white text-sm font-semibold text-gray-800 hover:text-brand-700 rounded-lg ring-1 ring-gray-300 hover:ring-gray-400 transition tabular-nums whitespace-nowrap">
                            <svg class="w-[18px] h-[18px] shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ config('seo.contact.phone_display') }}
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Mon to Sat, 9.30 am to 7 pm IST</p>
                </div>

                {{-- Link columns --}}
                @foreach ([
                    ['title' => 'Product', 'span' => 'md:col-span-2', 'links' => [
                        ['href' => route('pages.features'), 'label' => 'Features'],
                        ['href' => route('pages.how-to-use'), 'label' => 'How to use'],
                        ['href' => route('pages.pricing'), 'label' => 'Pricing'],
                        ['href' => route('register'), 'label' => 'Sign up'],
                        ['href' => route('login'), 'label' => 'Log in'],
                    ]],
                    // Split by what the reader is looking for, kept to six
                    // links a column so no column runs long.
                    ['title' => 'Free tools', 'span' => 'md:col-span-3', 'links' => [
                        ['href' => route('pages.gst-calculator'), 'label' => 'GST calculator'],
                        ['href' => route('pages.gst-invoice-format'), 'label' => 'GST invoice format'],
                        ['href' => route('pages.billing-software'), 'label' => 'Free billing software'],
                        ['href' => route('pages.cash-memo-format'), 'label' => 'Cash memo format'],
                        ['href' => route('pages.credit-note-format'), 'label' => 'Credit note format'],
                        // Official CBIC HSN/SAC search, authoritative and always up to date.
                        ['href' => 'https://services.gst.gov.in/services/searchhsnsac', 'label' => 'HSN/SAC finder', 'external' => true],
                    ]],
                    // Company, support and trust. Several of these have no other
                    // internal link on the site, so they live here for crawlers
                    // and for anyone checking who we are.
                    ['title' => 'Company', 'span' => 'md:col-span-3', 'links' => array_values(array_filter([
                        ['href' => route('pages.about'), 'label' => 'About us'],
                        ['href' => route('pages.contact'), 'label' => 'Contact us'],
                        ['href' => route('blog.index'), 'label' => 'Blog'],
                        ['href' => route('pages.faq'), 'label' => 'FAQ'],
                        ['href' => route('pages.partners'), 'label' => 'For CAs and partners'],
                        // Auth-gated app pages: they 302 to /login for anonymous
                        // crawlers and /invoices is robots-disallowed, so linking
                        // them publicly just leaks link equity into a dead end.
                        auth()->check() ? ['href' => route('help'), 'label' => 'Help Centre'] : null,
                        auth()->check() ? ['href' => route('invoices.templates'), 'label' => 'Invoice templates'] : null,
                    ]))],
                ] as $col)
                    <nav class="col-span-1 {{ $col['span'] }}" aria-label="{{ $col['title'] }}">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $col['title'] }}</h2>
                        <ul class="mt-3 text-sm">
                            @foreach ($col['links'] as $link)
                                <li>
                                    <a href="{{ $link['href'] }}"
                                       @if (! empty($link['external'])) target="_blank" rel="noopener noreferrer" @endif
                                       class="inline-flex items-center gap-1.5 py-1.5 text-gray-600 hover:text-gray-900 hover:underline underline-offset-4 decoration-gray-300 transition-colors">
                                        {{ $link['label'] }}
                                        @if (! empty($link['external']))
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                            <span class="sr-only">(opens the GST portal)</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endforeach
            </div>
        </div>

        {{-- Sub-footer: copyright and origin on the left, social on the right.
             Wraps to stacked rows below sm instead of overflowing. --}}
        @php
            $socialLinks = [
                ['key' => 'facebook',  'label' => 'Facebook',
                 'path' => 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.51 1.5-3.9 3.77-3.9 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.45 2.9h-2.33V22c4.78-.79 8.44-4.93 8.44-9.94z'],
                ['key' => 'instagram', 'label' => 'Instagram',
                 'path' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 5.68a4.16 4.16 0 100 8.32 4.16 4.16 0 000-8.32zm0 6.86a2.7 2.7 0 110-5.4 2.7 2.7 0 010 5.4zm5.3-7.02a.97.97 0 11-1.94 0 .97.97 0 011.94 0z'],
                ['key' => 'linkedin',  'label' => 'LinkedIn',
                 'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z'],
            ];
        @endphp
        <div class="border-t border-gray-200">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center sm:justify-between gap-3 text-sm text-gray-500">
                <p class="text-center sm:text-left">
                    &copy; {{ date('Y') }} Apna Invoice. Made in India by
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-medium text-gray-700 hover:text-brand-700">Datasoft Technologies</a>, Delhi NCR.
                </p>
                <div class="flex items-center gap-1">
                    @foreach ($socialLinks as $sl)
                        @if ($url = config('seo.social.' . $sl['key']))
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               aria-label="{{ $sl['label'] }}" title="{{ $sl['label'] }}"
                               class="inline-flex items-center justify-center w-[40px] h-[40px] rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $sl['path'] }}"/></svg>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </footer>

@else
    {{-- ======================= MINIMAL FOOTER (app pages) =======================
         Signed-in pages get a quiet strip, not the marketing footer. What used
         to sit here was the full dark-green block: brand panel, Home/Blogs
         links, social icons, "Made in India" and "GST Ready" badges and a
         support card, under every invoice list and every settings page. None
         of it is anything a logged-in user came here for, and it competed
         with the page it was sitting under.

         What stays is the part that is still useful once you are inside the
         product: how to get help, and the legal links. ------------------- --}}
    <footer class="mt-16 border-t border-gray-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
                <div class="text-center sm:text-left">
                    &copy; {{ now()->year }}
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener"
                       class="font-semibold text-gray-600 hover:text-brand-700 transition">Datasoft Technologies</a>. All rights reserved.
                </div>
                <div class="flex flex-wrap items-center justify-center gap-x-1 gap-y-1">
                    {{-- WhatsApp support lives in the ribbon (and the top bar), not
                         in a bubble floating over the page. --}}
                    @if ($wa = config('seo.contact.whatsapp_url'))
                        <a href="{{ $wa }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-[#0f7540] bg-[#0f7540]/[0.07] hover:bg-[#0f7540]/[0.14] transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            Chat with us
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </footer>
@endif
