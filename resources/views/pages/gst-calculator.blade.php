@php
    $appName = config('seo.name', 'Apna Invoice');
    $url = url('/gst-calculator');

    // WebApplication (the calculator is a real tool), HowTo (how to use it) and
    // FAQPage (the questions below) — three rich-result types on one page.
    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'GST Calculator',
        'url' => $url,
        'applicationCategory' => 'FinanceApplication',
        'operatingSystem' => 'Web',
        'inLanguage' => 'en-IN',
        'description' => 'Free online GST calculator for India. Add or remove GST at 5%, 12%, 18% or 28%, with automatic CGST, SGST and IGST split for intra-state and inter-state supply.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
    ];

    $howToSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to calculate GST online',
        'inLanguage' => 'en-IN',
        'step' => [
            ['@type' => 'HowToStep', 'position' => 1, 'name' => 'Enter the amount', 'text' => 'Type the amount. Choose whether it already includes GST or not.'],
            ['@type' => 'HowToStep', 'position' => 2, 'name' => 'Pick the GST rate', 'text' => 'Select 5%, 12%, 18% or 28%, or type a custom rate.'],
            ['@type' => 'HowToStep', 'position' => 3, 'name' => 'Choose the supply type', 'text' => 'Same state splits into CGST and SGST. Different state shows IGST.'],
            ['@type' => 'HowToStep', 'position' => 4, 'name' => 'Read the result', 'text' => 'See the taxable value, GST amount and total instantly.'],
        ],
    ];

    $faqs = [
        ['q' => 'How is GST calculated on an amount?',
         'a' => 'To add GST, multiply the base amount by the GST rate and add it back. For example, 18% GST on Rs 1,000 is Rs 180, so the total is Rs 1,180. To remove GST from an inclusive amount, divide by 1 plus the rate. Rs 1,180 inclusive of 18% gives a base of Rs 1,000 and GST of Rs 180.'],
        ['q' => 'What is the difference between CGST, SGST and IGST?',
         'a' => 'When the seller and buyer are in the same state, GST splits equally into CGST (central) and SGST (state). When they are in different states, the whole tax is charged as IGST (integrated). This calculator does that split for you.'],
        ['q' => 'What are the GST rates in India?',
         'a' => 'The common GST slabs are 5%, 12%, 18% and 28%. A few items use 0%, 0.25% or 3%. Most services fall under 18%. Always confirm the rate for your specific HSN or SAC code.'],
        ['q' => 'How do I calculate GST inclusive and exclusive amounts?',
         'a' => 'Exclusive means GST is added on top of the amount you enter. Inclusive means the amount you enter already contains GST, and the tool works backwards to find the base value and the tax inside it. Switch the toggle to do either.'],
        ['q' => 'How do I do a reverse GST calculation?',
         'a' => 'A reverse GST calculation removes GST from a price that already includes it. Divide the inclusive amount by 1 plus the rate. For example, Rs 1,180 inclusive of 18% GST gives a base of Rs 1,000 (1,180 divided by 1.18) and GST of Rs 180. Switch this calculator to "Remove GST" to do it automatically.'],
        ['q' => 'Is this GST calculator free?',
         'a' => 'Yes. It is completely free, works in your browser and needs no sign up. When you are ready to turn a calculation into a proper GST invoice, you can create one free with ' . $appName . '.'],
    ];

    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
@endphp

<x-layouts.marketing
    title="Free GST Calculator (CGST, SGST & IGST) for India"
    eyebrow="Free tool"
    lead="Add or remove GST at 5%, 12%, 18% or 28% in one tap. See the CGST and SGST split for same-state sales, or IGST for inter-state, with no sign up."
    description="Free online GST calculator for India. Add or remove GST at 5, 12, 18 & 28% with automatic CGST, SGST & IGST split — inclusive or exclusive, no sign-up."
    keywords="free GST calculator, GST calculator, free GST calculator India, online GST calculator, CGST SGST IGST calculator, GST inclusive calculator, GST exclusive calculator, reverse GST calculator, GST calculator 5 12 18 28, GST percentage calculator"
    :json-ld="[$appSchema, $howToSchema, $faqSchema]">

    {{-- ===== The calculator widget ===== --}}
    <div x-data="gstCalc()" x-cloak class="not-prose -mt-2 mb-12 rounded-2xl ring-1 ring-gray-200 shadow-card overflow-hidden">
        <div class="grid md:grid-cols-2">
            {{-- Inputs --}}
            <div class="p-6 sm:p-8 bg-white space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Amount (₹)</label>
                    <input type="number" min="0" step="0.01" x-model.number="amount" @input="recompute()"
                           class="w-full text-lg border-gray-300 rounded-lg shadow-sm focus:ring-brand-500 focus:border-brand-500 font-mono"
                           placeholder="1000" inputmode="decimal">
                </div>

                <div>
                    <span class="block text-sm font-semibold text-gray-800 mb-1.5">This amount is</span>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="mode='exclusive'; recompute()"
                                :class="mode==='exclusive' ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-gray-700 ring-gray-300'"
                                class="px-3 py-2.5 rounded-lg text-sm font-semibold ring-1 transition">GST exclusive</button>
                        <button type="button" @click="mode='inclusive'; recompute()"
                                :class="mode==='inclusive' ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-gray-700 ring-gray-300'"
                                class="px-3 py-2.5 rounded-lg text-sm font-semibold ring-1 transition">GST inclusive</button>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500" x-text="mode==='exclusive' ? 'GST will be added on top of this amount.' : 'This amount already contains GST. We work backwards to find it.'"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">GST rate</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="r in [5,12,18,28]" :key="r">
                            <button type="button" @click="rate=r; recompute()"
                                    :class="rate===r ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-gray-700 ring-gray-300'"
                                    class="px-4 py-2 rounded-lg text-sm font-semibold ring-1 transition" x-text="r + '%'"></button>
                        </template>
                        <div class="flex items-center gap-1.5 px-2 rounded-lg ring-1 ring-gray-300 bg-white">
                            <input type="number" min="0" max="100" step="0.01" x-model.number="rate" @input="recompute()"
                                   class="w-16 border-0 p-1.5 text-sm font-mono focus:ring-0" aria-label="Custom GST rate">
                            <span class="text-sm text-gray-500 pr-1">%</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="block text-sm font-semibold text-gray-800 mb-1.5">Supply type</span>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="supply='intra'; recompute()"
                                :class="supply==='intra' ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-gray-700 ring-gray-300'"
                                class="px-3 py-2.5 rounded-lg text-sm font-semibold ring-1 transition">Same state<br><span class="text-[11px] font-normal opacity-80">CGST + SGST</span></button>
                        <button type="button" @click="supply='inter'; recompute()"
                                :class="supply==='inter' ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-gray-700 ring-gray-300'"
                                class="px-3 py-2.5 rounded-lg text-sm font-semibold ring-1 transition">Different state<br><span class="text-[11px] font-normal opacity-80">IGST</span></button>
                    </div>
                </div>
            </div>

            {{-- Result --}}
            <div class="p-6 sm:p-8 bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 text-white flex flex-col justify-center">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-brand-200">Taxable value</span>
                        <span class="font-mono text-base" x-text="'₹ ' + fmt(out.base)"></span>
                    </div>
                    <template x-if="supply==='intra'">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-brand-200">CGST <span x-text="'(' + half(rate) + '%)'"></span></span>
                                <span class="font-mono text-base" x-text="'₹ ' + fmt(out.cgst)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-brand-200">SGST <span x-text="'(' + half(rate) + '%)'"></span></span>
                                <span class="font-mono text-base" x-text="'₹ ' + fmt(out.sgst)"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="supply==='inter'">
                        <div class="flex items-center justify-between">
                            <span class="text-brand-200">IGST <span x-text="'(' + (rate||0) + '%)'"></span></span>
                            <span class="font-mono text-base" x-text="'₹ ' + fmt(out.igst)"></span>
                        </div>
                    </template>
                    <div class="flex items-center justify-between border-t border-white/15 pt-3">
                        <span class="text-brand-100">Total GST</span>
                        <span class="font-mono text-base font-semibold" x-text="'₹ ' + fmt(out.gst)"></span>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/20 pt-3">
                        <span class="font-semibold">Total amount</span>
                        <span class="font-mono text-2xl font-extrabold" x-text="'₹ ' + fmt(out.total)"></span>
                    </div>
                </div>
                <a href="{{ route('register') }}" class="mt-6 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white text-brand-800 font-semibold text-sm hover:bg-brand-50 transition">
                    Turn this into a GST invoice, free
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                {{-- Share-the-result loop: the calculation travels to another
                     trader's WhatsApp with the calculator's link attached. --}}
                <a :href="whatsAppShare()" target="_blank" rel="noopener"
                   class="mt-2 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#25D366]/15 ring-1 ring-[#25D366]/50 text-white font-semibold text-sm hover:bg-[#25D366]/25 transition">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.511-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Share this calculation on WhatsApp
                </a>
                {{-- Native OS share sheet on mobile → reaches SMS, Telegram,
                     email and any WhatsApp contact in one tap. --}}
                <button type="button" @click="nativeShare()"
                   class="mt-2 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white/10 ring-1 ring-white/30 text-white font-semibold text-sm hover:bg-white/20 transition w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    <span x-text="copied ? 'Link copied!' : 'Share…'">Share…</span>
                </button>
                <p class="mt-2 text-center text-[11px] text-brand-300">No card. Unlimited invoices during beta.</p>
            </div>
        </div>
    </div>

    {{-- ===== Supporting content (real, useful, indexable) ===== --}}
    <h2>How to calculate GST</h2>
    <p>
        GST is a percentage added to the value of goods or services. To <strong>add GST</strong>, take the base amount,
        multiply it by the rate, and add it back. For example, 18% GST on ₹1,000 is ₹180, so the customer pays ₹1,180.
        To <strong>remove GST</strong> from a price that already includes it, divide the total by one plus the rate.
        A ₹1,180 price that includes 18% GST has a base value of ₹1,000 and ₹180 of tax inside it.
    </p>
    <p>
        The formulas are simple, but doing them by hand for every line of an invoice is where mistakes creep in.
        The calculator above handles both directions, and splits the tax the way GST actually works in India.
    </p>

    <h2>CGST, SGST and IGST, in plain words</h2>
    <p>
        GST is one tax collected in parts. When you sell to a customer in your <strong>own state</strong>, the tax is
        shared between the centre and the state, so it shows as <strong>CGST</strong> and <strong>SGST</strong>, each half
        of the rate. When you sell to a customer in a <strong>different state</strong>, the whole tax goes to the centre
        as <strong>IGST</strong>. The total you collect is the same either way. Only the labels and the split change,
        based on the place of supply.
    </p>

    <h2>Reverse GST calculation: remove GST from an inclusive amount</h2>
    <p>
        A <strong>reverse GST calculation</strong> works backwards from a price that already includes tax to find the
        base value and the GST inside it. Divide the inclusive amount by <strong>1 plus the rate</strong>. For example,
        ₹1,180 inclusive of 18% GST gives a base of ₹1,000 (₹1,180 ÷ 1.18) and GST of ₹180. Switch the toggle above to
        <strong>Remove GST</strong> and the calculator does this for you — useful when your MRP or quoted price already
        has GST baked in and you need to show the tax separately on a
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice</a> or a
        <a href="{{ route('pages.cash-memo-format') }}">cash memo</a>.
    </p>

    <h2>GST rates in India</h2>
    <p>The common slabs are below. Always confirm the exact rate for your HSN or SAC code, since a few items sit outside these.</p>
    <ul>
        <li><strong>5%</strong>, often basic and essential goods and some transport services.</li>
        <li><strong>12%</strong>, a middle band covering many processed goods and services.</li>
        <li><strong>18%</strong>, the most common rate, where most services land.</li>
        <li><strong>28%</strong>, higher band for items like luxury and certain durables.</li>
    </ul>

    <h2>From calculation to a real invoice</h2>
    <p>
        A calculator gives you a number. An invoice gives you a record. When you make a GST bill with {{ $appName }},
        the CGST, SGST and IGST split happens automatically from your customer's state, the HSN or SAC code sits on each
        line, and you get a clean PDF you can share on WhatsApp in seconds. See the
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a>, explore
        <a href="{{ route('pages.billing-software') }}">free billing software</a> for your shop, or read how a
        <a href="{{ route('pages.credit-note-format') }}">GST credit note</a> reverses a bill.
        <a href="{{ route('register') }}">Create your first GST invoice free</a>, or
        <a href="{{ url('/') }}">see everything {{ $appName }} does</a>.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach

    {{-- Embeddable widget: give bloggers/CAs a copy-paste iframe. Each embed is
         a live referral + backlink surface pointing back at the tool. --}}
    <h2>Embed this GST calculator on your site</h2>
    <p>Run a CA practice, tax blog or MSME portal? Drop this free calculator into any page — no fees, no sign-up. Just copy the snippet below.</p>
    <div class="not-prose" x-data="{ copied: false, code: '<iframe src=&quot;{{ url('/gst-calculator/embed') }}&quot; width=&quot;100%&quot; height=&quot;520&quot; style=&quot;border:1px solid #e5e7eb;border-radius:12px;max-width:480px&quot; title=&quot;GST Calculator by Apna Invoice&quot; loading=&quot;lazy&quot;></iframe>' }">
        <pre class="text-xs bg-gray-900 text-gray-100 rounded-lg p-4 overflow-x-auto"><code x-text="code"></code></pre>
        <button type="button"
                @click="navigator.clipboard.writeText(code); copied = true; setTimeout(() => copied = false, 2000)"
                class="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition">
            <span x-text="copied ? 'Copied!' : 'Copy embed code'">Copy embed code</span>
        </button>
    </div>

    @push('scripts')
    <script>
        function gstCalc() {
            return {
                amount: 1000,
                rate: 18,
                mode: 'exclusive',   // 'exclusive' = add GST, 'inclusive' = extract GST
                supply: 'intra',     // 'intra' = CGST+SGST, 'inter' = IGST
                out: { base: 0, gst: 0, cgst: 0, sgst: 0, igst: 0, total: 0 },
                init() {
                    // Prefill from a shared deep link so a recipient sees the
                    // exact calculation their friend sent — not a blank default.
                    const q = new URLSearchParams(location.search);
                    if (q.has('amount')) this.amount = parseFloat(q.get('amount')) || this.amount;
                    if (q.has('rate')) this.rate = parseFloat(q.get('rate')) || this.rate;
                    if (['inclusive', 'exclusive'].includes(q.get('mode'))) this.mode = q.get('mode');
                    if (['intra', 'inter'].includes(q.get('supply'))) this.supply = q.get('supply');
                    this.recompute();
                },
                half(r) { return (((parseFloat(r) || 0) / 2)); },
                fmt(n) { return (parseFloat(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                recompute() {
                    const amt = parseFloat(this.amount) || 0;
                    const rate = parseFloat(this.rate) || 0;
                    let base, gst, total;
                    if (this.mode === 'inclusive') {
                        total = +amt.toFixed(2);
                        base = +(amt / (1 + rate / 100)).toFixed(2);
                        gst = +(total - base).toFixed(2);
                    } else {
                        base = +amt.toFixed(2);
                        gst = +(base * rate / 100).toFixed(2);
                        total = +(base + gst).toFixed(2);
                    }
                    let cgst = 0, sgst = 0, igst = 0;
                    if (this.supply === 'intra') {
                        cgst = +(gst / 2).toFixed(2);
                        sgst = +(gst - cgst).toFixed(2);   // remainder so halves always sum to gst
                    } else {
                        igst = gst;
                    }
                    this.out = { base, gst, cgst, sgst, igst, total };
                },
                /**
                 * Deep link to THIS calculation (prefills the recipient's
                 * calculator) plus campaign tags so shared traffic is
                 * attributable in analytics.
                 */
                deepLink(source) {
                    const p = new URLSearchParams({
                        amount: this.amount, rate: this.rate, mode: this.mode, supply: this.supply,
                        utm_source: source, utm_medium: 'share', utm_campaign: 'gst_calculator',
                    });
                    return `{{ url('/gst-calculator') }}?${p.toString()}`;
                },
                shareText() {
                    const split = this.supply === 'intra'
                        ? `CGST ₹${this.fmt(this.out.cgst)} + SGST ₹${this.fmt(this.out.sgst)}`
                        : `IGST ₹${this.fmt(this.out.igst)}`;
                    return `GST @ ${this.rate || 0}% on ₹${this.fmt(this.out.base)}:\n${split}\nTotal: ₹${this.fmt(this.out.total)}`;
                },
                /**
                 * WhatsApp share — the result plus a deep link, so the tool and
                 * the exact calculation travel together between traders.
                 */
                whatsAppShare() {
                    const text = `${this.shareText()}\n\nCalculated free at ${this.deepLink('whatsapp')}`;
                    return 'https://wa.me/?text=' + encodeURIComponent(text);
                },
                /**
                 * Native OS share sheet on mobile (SMS, Telegram, email, any
                 * WhatsApp contact) with a clipboard fallback on desktop.
                 */
                nativeShare() {
                    const url = this.deepLink('web_share');
                    if (navigator.share) {
                        navigator.share({ title: 'GST calculation', text: this.shareText(), url }).catch(() => {});
                    } else {
                        navigator.clipboard?.writeText(this.shareText() + '\n\n' + url);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2000);
                    }
                },
                copied: false,
            };
        }
    </script>
    @endpush
</x-layouts.marketing>
