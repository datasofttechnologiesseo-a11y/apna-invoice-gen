@props(['variant' => 'full'])

@if ($variant === 'full')
    {{-- ======================= FULL FOOTER ======================= --}}
    <footer class="relative bg-[#050a1a] text-gray-300 overflow-hidden">
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
                                Monthly roundup for Indian SMEs &amp; startups. Rule changes, deadlines, product releases — no fluff. Launching with our paid tiers; drop us a line to get on the list.
                            </p>
                        </div>
                        <div class="md:col-span-2 flex flex-col gap-3">
                            <a href="{{ route('pages.contact') }}?subject={{ urlencode('Newsletter — add me to updates') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-400 hover:to-accent-500 text-accent-950 font-bold rounded-xl transition shadow-lg shadow-accent-500/30">
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
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" aria-label="Apna Invoice" class="inline-block bg-white rounded-lg p-2 ring-1 ring-white/10 hover:ring-white/30 transition">
                        <x-brand-logo class="h-10 w-auto" />
                    </a>
                    <p class="mt-5 text-gray-400 text-sm leading-relaxed max-w-sm">
                        GST-compliant invoicing built for Indian SMEs &amp; Startups by
                        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="text-white font-semibold hover:text-accent-400 transition">Datasoft Technologies</a>.
                        Professional invoices in 60 seconds.
                    </p>

                    {{-- Direct contact: WhatsApp + call. Highest-trust signal for Indian SME audience. --}}
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team — I need help with…') }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#25D366]/15 hover:bg-[#25D366]/25 text-[#3DDC84] hover:text-white text-xs font-semibold rounded-md ring-1 ring-[#25D366]/30 hover:ring-[#25D366]/60 transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            WhatsApp
                        </a>
                        <a href="tel:{{ config('seo.contact.phone_e164') }}"
                           class="inline-flex items-center gap-1.5 text-gray-400 hover:text-white text-xs font-mono transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ config('seo.contact.phone_display') }}
                        </a>
                    </div>
                </div>

                {{-- Link columns --}}
                @foreach ([
                    ['title' => 'Product', 'color' => 'brand', 'links' => [
                        ['href' => '/#features', 'label' => 'Features'],
                        ['href' => '/#pricing', 'label' => 'Pricing'],
                        ['href' => '/#faq', 'label' => 'FAQ'],
                        ['href' => route('register'), 'label' => 'Sign up'],
                        ['href' => route('login'), 'label' => 'Log in'],
                    ]],
                    ['title' => 'Resources', 'color' => 'accent', 'links' => [
                        ['href' => route('help'), 'label' => 'Help Center'],
                        ['href' => route('invoices.templates'), 'label' => 'Invoice templates'],
                        // Official CBIC HSN/SAC search — authoritative and always up to date.
                        ['href' => 'https://services.gst.gov.in/services/searchhsnsac', 'label' => 'HSN/SAC finder', 'external' => true],
                        ['href' => route('pages.contact'), 'label' => 'Contact support'],
                    ]],
                ] as $col)
                    <div class="col-span-1 md:col-span-4">
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-4 rounded-full bg-{{ $col['color'] }}-500"></div>
                            <h4 class="text-white font-bold tracking-wide text-sm uppercase">{{ $col['title'] }}</h4>
                        </div>
                        <ul class="mt-5 space-y-3 text-sm">
                            @foreach ($col['links'] as $link)
                                <li>
                                    <a href="{{ $link['href'] }}"
                                       @if (! empty($link['external'])) target="_blank" rel="noopener noreferrer" @endif
                                       class="group inline-flex items-center gap-2 text-gray-400 hover:text-white transition">
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

        {{-- Sub-footer bar --}}
        <div class="relative border-t border-white/10 bg-black/40 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-5 flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-white">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-center md:text-left">
                    <span class="font-semibold">© 2026 <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="hover:text-accent-400 transition">Datasoft Technologies</a></span>
                    <span class="hidden md:inline text-white/40">•</span>
                    <span>All rights reserved</span>
                    <span class="hidden md:inline text-white/40">•</span>
                    <span>Jurisdiction: Delhi NCR, India</span>
                </div>
                <div class="flex items-center gap-2">
                    <span>Built with</span>
                    <svg class="w-4 h-4 text-red-400 animate-shimmer" fill="currentColor" viewBox="0 0 20 20"><path d="M3.2 5.3a5.3 5.3 0 017.5 0l.3.3.3-.3a5.3 5.3 0 017.5 7.5L10.9 17.6a1.3 1.3 0 01-1.8 0L3.2 12.8a5.3 5.3 0 010-7.5z"/></svg>
                    <span>in India by</span>
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="font-bold hover:text-accent-400 transition">DST</a>
                </div>
            </div>
        </div>
    </footer>

@else
    {{-- ======================= MINIMAL FOOTER (app pages) ======================= --}}
    <footer class="mt-16 relative bg-gradient-to-br from-brand-950 via-[#0b1224] to-[#050a1a] text-gray-200 overflow-hidden">
        {{-- Ambient glow --}}
        <div class="absolute top-0 left-1/2 w-[700px] h-[250px] bg-brand-600 rounded-full blur-[110px] opacity-30 -translate-x-1/2 -translate-y-1/3 pointer-events-none"></div>
        <div class="absolute bottom-0 right-0 w-[300px] h-[200px] bg-accent-600 rounded-full blur-[100px] opacity-10 pointer-events-none"></div>
        {{-- Top gradient accent --}}
        <div class="relative h-[2px] bg-gradient-to-r from-transparent via-accent-500 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-10">
            <div class="grid md:grid-cols-3 gap-8 items-center">
                {{-- Brand --}}
                <div class="flex items-center gap-4">
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" aria-label="Apna Invoice" class="inline-block bg-white rounded-lg p-1.5 ring-1 ring-white/10 hover:ring-white/30 transition">
                        <x-brand-logo class="h-8 w-auto" />
                    </a>
                    <div class="leading-tight hidden sm:block">
                        <div class="font-bold text-white text-sm">Apna Invoice</div>
                        <div class="mt-0.5 text-xs text-gray-400">By <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="hover:text-accent-400 transition">Datasoft Technologies</a></div>
                    </div>
                </div>

                {{-- Center links --}}
                <div class="flex items-center justify-center gap-1 text-sm">
                    @foreach ([['/','Home'], ['/#faq','Help'], [route('pages.privacy'),'Privacy'], [route('pages.terms'),'Terms']] as $item)
                        <a href="{{ $item[0] }}" class="px-3 py-1.5 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition font-medium">{{ $item[1] }}</a>
                    @endforeach
                </div>

                {{-- Badges + social --}}
                <div class="flex items-center justify-center md:justify-end gap-2 flex-wrap">
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

            {{-- Bottom strip --}}
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-white">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-center md:text-left">
                    <span class="font-semibold">© 2026 <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="hover:text-accent-400 transition">Datasoft Technologies</a></span>
                    <span class="hidden md:inline text-white/40">•</span>
                    <span>All rights reserved</span>
                </div>
                <div class="flex items-center gap-2">
                    <span>Built with</span>
                    <svg class="w-4 h-4 text-red-400 animate-shimmer" fill="currentColor" viewBox="0 0 20 20"><path d="M3.2 5.3a5.3 5.3 0 017.5 0l.3.3.3-.3a5.3 5.3 0 017.5 7.5L10.9 17.6a1.3 1.3 0 01-1.8 0L3.2 12.8a5.3 5.3 0 010-7.5z"/></svg>
                    <span>in India by</span>
                    <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="font-bold hover:text-accent-400 transition">DST</a>
                </div>
            </div>
        </div>
    </footer>
@endif
