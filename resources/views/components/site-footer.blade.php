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
                                Monthly roundup for Indian SMEs &amp; startups. Rule changes, deadlines, product releases — no fluff, unsubscribe anytime.
                            </p>
                        </div>
                        <form onsubmit="event.preventDefault(); this.querySelector('button').innerText='✓ Subscribed'" class="md:col-span-2 flex flex-col gap-3">
                            <label for="newsletter-email" class="sr-only">Your work email</label>
                            <input id="newsletter-email" name="email" type="email" required placeholder="you@company.com" autocomplete="email" class="w-full px-5 py-3.5 rounded-xl bg-white/10 border-white/10 text-white placeholder-gray-400 focus:border-accent-400 focus:ring-accent-400/40 focus:bg-white/15 transition">
                            <button class="w-full px-5 py-3.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-400 hover:to-accent-500 text-accent-950 font-bold rounded-xl transition shadow-lg shadow-accent-500/30">
                                Subscribe →
                            </button>
                        </form>
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
                        Professional invoices in under a minute.
                    </p>

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
                    <span>Jurisdiction: India</span>
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
