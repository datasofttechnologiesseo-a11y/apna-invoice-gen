@props(['variant' => 'full'])

@if ($variant === 'full')
    {{-- ======================= FULL FOOTER ======================= --}}
    <footer class="relative bg-[#04211d] text-gray-300 overflow-hidden">
        {{-- Ambient glows --}}
        <div class="absolute top-0 left-1/4 w-[700px] h-[400px] bg-brand-700 rounded-full blur-[140px] opacity-25 -translate-y-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 right-1/4 w-[500px] h-[400px] bg-accent-600 rounded-full blur-[150px] opacity-15 translate-y-1/2 pointer-events-none"></div>
        {{-- Dot pattern --}}
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 24px 24px;"></div>

        {{-- Top accent line --}}
        <div class="relative h-0.5 bg-gradient-to-r from-transparent via-accent-500 to-transparent"></div>

        {{-- Newsletter hero --}}
        <div class="relative">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-16 md:py-20">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-900/90 via-brand-800/60 to-accent-900/40 ring-1 ring-white/10 backdrop-blur-sm">
                    <div class="absolute -top-16 -right-16 w-64 h-64 bg-accent-500 rounded-full blur-3xl opacity-20"></div>
                    <div class="absolute -bottom-16 -left-16 w-64 h-64 bg-brand-500 rounded-full blur-3xl opacity-25"></div>

                    <div class="relative p-8 md:p-12 grid md:grid-cols-5 gap-8 items-center">
                        <div class="md:col-span-3">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-accent-500/15 text-accent-300 text-[11px] font-bold uppercase tracking-widest ring-1 ring-accent-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent-400 animate-shimmer"></span> Stay in the loop
                            </span>
                            <h3 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-white leading-tight">
                                GST updates, straight to your inbox.
                            </h3>
                            <p class="mt-3 text-gray-300 text-base leading-relaxed max-w-lg">
                                Monthly roundup for Indian SMEs &amp; startups. Rule changes, deadlines, product releases, no fluff. Launching with our paid tiers; drop us a line to get on the list.
                            </p>
                        </div>
                        <div class="md:col-span-2 flex flex-col gap-3">
                            <a href="{{ route('pages.contact') }}?subject={{ urlencode('Newsletter, add me to updates') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-400 hover:to-accent-500 text-accent-950 font-bold rounded-xl transition shadow-lg shadow-accent-500/30">
                                Get early updates
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5-5 5M5 12h13"/></svg>
                            </a>
                            <p class="text-xs text-gray-400 text-center">Takes 30 seconds via our contact form.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main footer grid --}}
        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 pb-16">
            <div class="grid grid-cols-2 md:grid-cols-12 gap-y-10 gap-x-8">
                {{-- Brand column --}}
                <div class="col-span-2 md:col-span-4">
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" aria-label="Apna Invoice" class="inline-block bg-white rounded-lg p-2 ring-1 ring-white/10 hover:ring-white/30 transition">
                        <x-brand-logo class="h-10 w-auto" />
                    </a>
                    <p class="mt-5 text-gray-400 text-sm leading-relaxed max-w-sm">
                        GST-compliant invoicing built for Indian SMEs &amp; Startups by
                        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="text-white font-semibold hover:text-accent-400 transition">Datasoft Technologies</a>.
                        Professional invoices in 60 seconds.
                    </p>

                    {{-- Direct contact: WhatsApp + call. The highest-trust signal for
                         this audience, so it is sized to be read, not squinted at. --}}
                    <div class="mt-6 flex flex-wrap items-center gap-2.5">
                        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team, I need help with…') }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-[#0f7540] hover:bg-[#0c5f34] text-white text-base font-semibold rounded-lg transition whitespace-nowrap">
                            <svg class="w-[18px] h-[18px] shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            WhatsApp
                        </a>
                        <a href="tel:{{ config('seo.contact.phone_e164') }}"
                           class="inline-flex items-center gap-1.5 px-3 py-2.5 text-base font-semibold text-gray-100 hover:text-white rounded-lg ring-1 ring-white/20 hover:ring-white/40 hover:bg-white/5 transition tabular-nums whitespace-nowrap">
                            <svg class="w-[18px] h-[18px] shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ config('seo.contact.phone_display') }}
                        </a>
                    </div>

                </div>

                {{-- Link columns --}}
                @foreach ([
                    ['title' => 'Product', 'color' => 'brand', 'links' => [
                        ['href' => route('pages.features'), 'label' => 'Features'],
                        ['href' => route('pages.how-to-use'), 'label' => 'How to use'],
                        ['href' => route('pages.pricing'), 'label' => 'Pricing'],
                        ['href' => route('register'), 'label' => 'Sign up'],
                        ['href' => route('login'), 'label' => 'Log in'],
                    ]],
                    // Split by what the reader is looking for, and kept to six
                    // links a column. Resources previously carried ten while the
                    // others held six, so three of the four columns ended early
                    // and the block was a third empty.
                    ['title' => 'Free tools', 'color' => 'accent', 'links' => [
                        ['href' => route('pages.gst-calculator'), 'label' => 'Free GST calculator'],
                        ['href' => route('pages.gst-invoice-format'), 'label' => 'GST invoice format'],
                        ['href' => route('pages.billing-software'), 'label' => 'Free billing software'],
                        ['href' => route('pages.cash-memo-format'), 'label' => 'Cash memo format'],
                        ['href' => route('pages.credit-note-format'), 'label' => 'Credit note format'],
                        // Official CBIC HSN/SAC search, authoritative and always up to date.
                        ['href' => 'https://services.gst.gov.in/services/searchhsnsac', 'label' => 'HSN/SAC finder', 'external' => true],
                    ]],
                    // Company, support and trust. Several of these had no internal
                    // link anywhere on the site before, which left them orphaned
                    // for crawlers and unreachable for anyone checking who we are.
                    ['title' => 'Company', 'color' => 'money', 'links' => array_values(array_filter([
                        ['href' => route('pages.about'), 'label' => 'About us'],
                        ['href' => route('blog.index'), 'label' => 'Blog'],
                        ['href' => route('pages.faq'), 'label' => 'FAQ'],
                        ['href' => route('pages.partners'), 'label' => 'For CAs & partners'],
                        // Auth-gated app pages: they 302 to /login for anonymous
                        // crawlers and /invoices is robots-disallowed, so linking
                        // them publicly just leaks link equity into a dead end.
                        auth()->check() ? ['href' => route('help'), 'label' => 'Help Centre'] : null,
                        auth()->check() ? ['href' => route('invoices.templates'), 'label' => 'Invoice templates'] : null,
                    ]))],
                ] as $col)
                    <div class="col-span-1 {{ $col['title'] === 'Company' ? 'md:col-span-2' : 'md:col-span-3' }}">
                        {{-- One accent across every column. The hue is not
                             carrying meaning here, so varying it per column
                             was noise; a single rule reads as hierarchy. --}}
                        <h4 class="text-white font-semibold text-[11px] uppercase tracking-[0.14em] pb-3 border-b border-white/10">
                            <span class="inline-block w-6 h-px align-middle mr-2 bg-accent-400"></span>{{ $col['title'] }}
                        </h4>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            @foreach ($col['links'] as $link)
                                <li>
                                    <a href="{{ $link['href'] }}"
                                       @if (! empty($link['external'])) target="_blank" rel="noopener noreferrer" @endif
                                       class="group inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors duration-150">
                                        <span class="relative">
                                            {{ $link['label'] }}
                                            <span class="absolute inset-x-0 -bottom-0.5 h-px bg-accent-400 scale-x-0 group-hover:scale-x-100 origin-left transition-transform"></span>
                                        </span>
                                        @if (! empty($link['external']))
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        @endif
                                        @if (! empty($link['badge']))
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-accent-500 text-accent-950 uppercase tracking-wider">{{ $link['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Sub-footer bar.
             One line on desktop: copyright on the left, social and the origin
             badge on the right. It wraps to centred rows below md rather than
             overflowing, which is what the earlier version did on phones.

             The Terms / Privacy / Refunds / Cookies chips were removed from
             here at the owner's request. NOTE: /terms and /refund now have no
             internal link anywhere on the public site. --}}
        <div class="relative border-t border-white/10 bg-black/40 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4
                        flex flex-col md:flex-row md:flex-nowrap items-center justify-between gap-3
                        text-xs text-gray-300">

                <div class="flex items-center gap-x-2 text-center md:text-left min-w-0">
                    <span class="font-semibold text-white whitespace-nowrap">&copy; {{ date('Y') }}
                        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener"
                           class="hover:text-accent-400 transition">Datasoft Technologies</a>
                    </span>
                    <span class="hidden lg:inline text-white/30">&middot;</span>
                    <span class="hidden lg:inline whitespace-nowrap">All rights reserved</span>
                    <span class="hidden xl:inline text-white/30">&middot;</span>
                    <span class="hidden xl:inline whitespace-nowrap">Delhi NCR, India</span>
                </div>

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

                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex items-center gap-2">
                        @foreach ($socialLinks as $sl)
                            @if ($url = config('seo.social.' . $sl['key']))
                                <a href="{{ $url }}" target="_blank" rel="noopener"
                                   aria-label="{{ $sl['label'] }}" title="{{ $sl['label'] }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-300 ring-1 ring-white/15 hover:text-white hover:bg-white/10 hover:ring-white/35 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $sl['path'] }}"/></svg>
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-saffron-500/15 text-saffron-200 text-[11px] font-bold ring-1 ring-saffron-500/40 whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-saffron-400"></span> Made in India
                    </span>
                </div>
            </div>
        </div>
    </footer>

@else
    {{-- ======================= MINIMAL FOOTER (app pages) ======================= --}}
    <footer class="mt-16 relative bg-gradient-to-br from-brand-950 via-[#08312c] to-[#04211d] text-gray-200 overflow-hidden">
        {{-- Ambient glow --}}
        <div class="absolute top-0 left-1/2 w-[700px] h-[250px] bg-brand-600 rounded-full blur-[110px] opacity-30 -translate-x-1/2 -translate-y-1/3 pointer-events-none"></div>
        <div class="absolute bottom-0 right-0 w-[300px] h-[200px] bg-accent-600 rounded-full blur-[100px] opacity-10 pointer-events-none"></div>
        {{-- Top gradient accent --}}
        <div class="relative h-[2px] bg-gradient-to-r from-transparent via-accent-500 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-10">
            <div class="grid md:grid-cols-3 gap-8 items-center">
                {{-- Brand --}}
                <div class="flex items-center gap-4">
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" aria-label="Apna Invoice" class="inline-block bg-white rounded-lg p-1.5 ring-1 ring-white/10 hover:ring-white/30 transition">
                        <x-brand-logo class="h-8 w-auto" />
                    </a>
                    <div class="leading-tight hidden sm:block">
                        <div class="font-bold text-white text-sm">Apna Invoice</div>
                        <div class="mt-0.5 text-xs text-gray-400">By <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="hover:text-accent-400 transition">Datasoft Technologies</a></div>
                    </div>
                </div>

                {{-- Center links (flex-wrap: five links exceed 320px small phones) --}}
                <div class="flex items-center justify-center gap-1 text-sm flex-wrap">
                    @foreach ([['/','Home'], [route('blog.index'),'Blogs'], ['/#faq','Help'], [route('pages.privacy'),'Privacy'], [route('pages.terms'),'Terms']] as $item)
                        <a href="{{ $item[0] }}" class="px-3 py-1.5 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition font-medium">{{ $item[1] }}</a>
                    @endforeach
                </div>

                {{-- Badges + social --}}
                <div class="flex items-center justify-center md:justify-end gap-2 flex-wrap">
                    @php
                        $social = [
                            ['key' => 'facebook',  'label' => 'Facebook',
                             'path' => 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.51 1.5-3.9 3.77-3.9 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.45 2.9h-2.33V22c4.78-.79 8.44-4.93 8.44-9.94z'],
                            ['key' => 'instagram', 'label' => 'Instagram',
                             'path' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 5.68a4.16 4.16 0 100 8.32 4.16 4.16 0 000-8.32zm0 6.86a2.7 2.7 0 110-5.4 2.7 2.7 0 010 5.4zm5.3-7.02a.97.97 0 11-1.94 0 .97.97 0 011.94 0z'],
                            ['key' => 'linkedin',  'label' => 'LinkedIn',
                             'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z'],
                        ];
                    @endphp
                    @foreach ($social as $s)
                        @if ($url = config('seo.social.' . $s['key']))
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               aria-label="{{ $s['label'] }}"
                               title="{{ $s['label'] }}"
                               class="inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-300 ring-1 ring-white/15 hover:text-white hover:bg-white/10 hover:ring-white/30 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $s['path'] }}"/></svg>
                            </a>
                        @endif
                    @endforeach

                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-saffron-500/15 text-saffron-200 text-[11px] font-bold ring-1 ring-saffron-500/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-saffron-400 animate-shimmer"></span> Made in India
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-brand-500/15 text-brand-200 text-[11px] font-bold ring-1 ring-brand-500/40">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1L3 4v6c0 4.5 3 8.3 7 9 4-.7 7-4.5 7-9V4l-7-3zm-.7 12.3L6 10l1.4-1.4 1.9 1.9 4.4-4.4L15 7.5l-5.7 5.8z" clip-rule="evenodd"/></svg>
                        GST Ready
                    </span>
                </div>
            </div>

            {{-- Divider --}}
            <div class="my-6 h-px bg-gradient-to-r from-transparent via-white/15 to-transparent"></div>

            {{-- "We're here for you" support row, keeps the user from feeling
                 stranded. Sits above the legal line so it reads as the primary
                 message, not boilerplate. Mailto on email so a tap opens the
                 native compose sheet on mobile. --}}
            <div class="rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
                <div class="flex items-center gap-3 text-gray-200">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-saffron-500/15 ring-1 ring-saffron-500/40 text-saffron-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 10c0 3.866-3.582 7-8 7a8.84 8.84 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z"/></svg>
                    </span>
                    <div class="leading-tight">
                        <div class="font-semibold text-white">Need help? We're a message away.</div>
                        <div class="text-xs text-gray-400">No bots, no queues, real humans from the Datasoft team.</div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="mailto:support@datasofttechnologies.com" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-white text-xs font-semibold transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        support@datasofttechnologies.com
                    </a>
                    <a href="{{ route('help') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-saffron-500/15 hover:bg-saffron-500/25 ring-1 ring-saffron-500/40 text-saffron-100 text-xs font-semibold transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Help &amp; FAQ
                    </a>
                </div>
            </div>

            {{-- Bottom strip --}}
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-white">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-center md:text-left">
                    <span class="font-semibold">© 2026 <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="hover:text-accent-400 transition">Datasoft Technologies</a></span>
                    <span class="hidden md:inline text-white/40">•</span>
                    <span>All rights reserved</span>
                </div>
                <div class="flex items-center gap-2">
                    <span>Built with</span>
                    <svg class="w-4 h-4 text-red-400 animate-shimmer" fill="currentColor" viewBox="0 0 20 20"><path d="M3.2 5.3a5.3 5.3 0 017.5 0l.3.3.3-.3a5.3 5.3 0 017.5 7.5L10.9 17.6a1.3 1.3 0 01-1.8 0L3.2 12.8a5.3 5.3 0 010-7.5z"/></svg>
                    <span>in India by</span>
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-bold hover:text-accent-400 transition">DST</a>
                </div>
            </div>
        </div>
    </footer>
@endif
