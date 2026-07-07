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
    description="Free online GST calculator for India. Add or remove GST at 5, 12, 18 and 28 percent with automatic CGST, SGST and IGST split. Inclusive and exclusive, intra-state and inter-state."
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
        line, and you get a clean PDF you can share on WhatsApp in seconds.
        <a href="{{ route('register') }}">Create your first GST invoice free</a>, or
        <a href="{{ url('/') }}">see everything {{ $appName }} does</a>.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach

    @push('scripts')
    <script>
        function gstCalc() {
            return {
                amount: 1000,
                rate: 18,
                mode: 'exclusive',   // 'exclusive' = add GST, 'inclusive' = extract GST
                supply: 'intra',     // 'intra' = CGST+SGST, 'inter' = IGST
                out: { base: 0, gst: 0, cgst: 0, sgst: 0, igst: 0, total: 0 },
                init() { this.recompute(); },
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
            };
        }
    </script>
    @endpush
</x-layouts.marketing>
