@php
    // Grouped questions from config/faqs.php. The home page shows the
    // 'general' list, which is picked from these same groups.
    $groups = config('faqs.groups', []);
    $all = collect($groups)->flatMap(fn ($g) => $g['items'])->values()->all();

    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $all),
    ];

    $contact = config('seo.contact');
    $supportEmail = $contact['email'] ?? 'support@datasofttechnologies.com';
@endphp

<x-layouts.marketing
    title="GST invoicing FAQ for India"
    eyebrow="Help"
    lead="Straight answers on price, GST invoices, returns, stock and your data. If your question is not here, call or WhatsApp us."
    description="Apna Invoice FAQ: price, GSTIN, IGST and CGST, e-way bills, GSTR-1 and GSTR-3B, Tally, using it on your phone and keeping a backup of your data."
    keywords="Apna Invoice FAQ, GST billing software questions, free GST invoice India, GSTR-1 JSON, e-way bill, composition bill of supply, GST billing help"
    type="website"
    :json-ld="[$faqSchema]">

    <div
        x-data="{
            q: '',
            words() { return this.q.toLowerCase().trim().split(/\s+/).filter(w => w !== '') },
            hit(el) { const w = this.words(); return w.length === 0 || w.every(t => el.dataset.text.includes(t)) },
            groupHit(el) { return Array.from(el.querySelectorAll('[data-faq]')).some(d => this.hit(d)) },
            count() { return Array.from(this.$refs.list.querySelectorAll('[data-faq]')).filter(d => this.hit(d)).length }
        }">

        {{-- Search. Hidden until Alpine runs, so a visitor without JavaScript
             never sees a box that does nothing. --}}
        <div x-cloak>
            <label for="faq-search" class="block text-sm font-semibold text-gray-900">Search the questions</label>
            <div class="relative mt-2">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                <input id="faq-search" type="search" x-model="q" autocomplete="off"
                       placeholder="For example: GSTR-1, e-way bill, Tally, UPI"
                       class="block w-full min-h-[44px] rounded-lg border-0 bg-white py-2.5 pl-10 pr-3 text-[15px] text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600">
            </div>
            <p class="mt-2 min-h-[1.25rem] text-sm text-gray-600" aria-live="polite">
                <span x-text="words().length === 0 ? '' : count() === 0 ? 'No question matches that. Try another word, or ask us below.' : count() + (count() === 1 ? ' question found' : ' questions found')"></span>
            </p>
        </div>

        {{-- Jump list for phones; on wide screens the side column lists the
             same sections. --}}
        <nav aria-label="FAQ sections" class="mt-4 lg:hidden" x-bind:class="{ 'hidden': words().length }">
            <ul class="flex flex-wrap gap-2 text-sm">
                @foreach ($groups as $group)
                    <li>
                        <a href="#{{ $group['id'] }}" class="inline-flex min-h-[36px] items-center rounded-full bg-gray-100 px-3 font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-800">{{ $group['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div x-ref="list" class="mt-6">
            @foreach ($groups as $group)
                <section class="scroll-mt-6 {{ $loop->first ? '' : 'mt-12' }}" aria-labelledby="{{ $group['id'] }}" x-bind:class="{ 'hidden': ! groupHit($el) }">
                    <h2 id="{{ $group['id'] }}" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-gray-900 scroll-mt-6">{{ $group['title'] }}</h2>
                    <div class="mt-4 divide-y divide-gray-200 border-y border-gray-200">
                        @foreach ($group['items'] as $f)
                            <details class="group" data-faq
                                     data-text="{{ mb_strtolower($f['q'] . ' ' . $f['a']) }}"
                                     x-bind:class="{ 'hidden': ! hit($el) }"
                                     x-effect="if (words().length) $el.open = hit($el)"
                                     @if ($loop->parent->first) open @endif>
                                <summary class="flex min-h-[44px] cursor-pointer list-none items-start justify-between gap-4 py-4 font-semibold text-gray-900 hover:text-brand-800 [&::-webkit-details-marker]:hidden">
                                    <span>{{ $f['q'] }}</span>
                                    <svg class="mt-1 h-5 w-5 shrink-0 text-gray-500 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </summary>
                                <p class="max-w-2xl pb-5 pr-8 text-[1.0625rem] leading-relaxed text-gray-600">{{ $f['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="mt-14 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-6 sm:p-7">
        <h2 id="still-stuck" class="font-display text-xl font-bold text-gray-900">Still stuck?</h2>
        <p class="mt-2 text-gray-600">Message or call us, 9.30 am to 7 pm, Monday to Saturday. You can also read the <a href="{{ route('pages.manuals.handbook') }}">free PDF handbook</a>.</p>
        <ul class="mt-5 grid gap-3 sm:grid-cols-3 text-[15px]">
            <li>
                <a href="{{ $contact['whatsapp_url'] }}?text={{ urlencode('Hi Apna Invoice team, I have a question about') }}" target="_blank" rel="noopener"
                   class="flex min-h-[44px] flex-col justify-center rounded-lg bg-white px-4 py-3 ring-1 ring-gray-200 hover:ring-gray-300">
                    <span class="font-semibold text-gray-900">WhatsApp</span>
                    <span class="tabular-nums text-gray-600">{{ $contact['phone_display'] }}</span>
                </a>
            </li>
            <li>
                <a href="tel:{{ $contact['phone_e164'] }}"
                   class="flex min-h-[44px] flex-col justify-center rounded-lg bg-white px-4 py-3 ring-1 ring-gray-200 hover:ring-gray-300">
                    <span class="font-semibold text-gray-900">Call</span>
                    <span class="tabular-nums text-gray-600">{{ $contact['phone_display'] }}</span>
                </a>
            </li>
            <li>
                <a href="mailto:{{ $supportEmail }}"
                   class="flex min-h-[44px] flex-col justify-center rounded-lg bg-white px-4 py-3 ring-1 ring-gray-200 hover:ring-gray-300">
                    <span class="font-semibold text-gray-900">Email</span>
                    <span class="break-all text-gray-600">{{ $supportEmail }}</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-6 sm:p-7">
        <h2 data-toc="skip" class="font-display text-xl font-bold text-gray-900">Make your first GST invoice</h2>
        <p class="mt-2 text-gray-600">
            Free during beta, with no card.
            <a href="{{ route('register') }}">Create your account</a>, try the
            <a href="{{ route('pages.gst-calculator') }}">GST calculator</a> without signing up, or read
            <a href="{{ route('pages.how-to-use') }}">how to use Apna Invoice</a>.
        </p>
    </div>
</x-layouts.marketing>
