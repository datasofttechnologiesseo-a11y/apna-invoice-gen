{{-- Chromeless, iframe-embeddable GST calculator. Third-party sites (CA blogs,
     MSME portals) drop this in via <iframe>; the "Powered by Apna Invoice"
     footer makes every embed a live referral + backlink surface. Kept noindex
     so it never competes with the full /gst-calculator page in search. --}}
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- noindex keeps the widget out of results; follow lets crawlers pass
         through its "Powered by" link to the full /gst-calculator page, which
         is the point of handing the embed out. --}}
    <meta name="robots" content="noindex, follow">
    <title>GST Calculator · Apna Invoice</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { background: transparent; }</style>
</head>
<body class="font-sans antialiased text-gray-900">

<div class="max-w-md mx-auto p-4" x-data="gstEmbed()" x-init="recompute()">
    {{-- Visually hidden: the widget has no visible title by design, but a
         document still needs one h1 to be navigable. --}}
    <h1 class="sr-only">GST calculator</h1>
    <div class="rounded-xl ring-1 ring-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 space-y-3">
            <div>
                <label for="embed-amount" class="block text-xs font-semibold text-gray-600 mb-1">Amount (₹)</label>
                <input id="embed-amount" type="number" min="0" x-model="amount" @input="recompute()" inputmode="decimal"
                       class="w-full rounded-lg border-gray-300 focus:border-brand-600 focus:ring-brand-600 text-lg font-semibold tabular-nums">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="embed-rate" class="block text-xs font-semibold text-gray-600 mb-1">GST rate</label>
                    {{-- GST 2.0 slabs first; 12% and 28% stay for supplies made
                         before 22 September 2025 and for tobacco. --}}
                    <select id="embed-rate" x-model="rate" @change="recompute()" class="w-full rounded-lg border-gray-300 focus:border-brand-600 focus:ring-brand-600">
                        <optgroup label="Current slabs">
                            @foreach ([0, 5, 18, 40] as $r)
                                <option value="{{ $r }}">{{ $r }}%</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Earlier slabs">
                            @foreach ([12, 28] as $r)
                                <option value="{{ $r }}">{{ $r }}%</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label for="embed-supply" class="block text-xs font-semibold text-gray-600 mb-1">Supply</label>
                    <select id="embed-supply" x-model="supply" @change="recompute()" aria-label="Supply type, within state or between states" class="w-full rounded-lg border-gray-300 focus:border-brand-600 focus:ring-brand-600">
                        <option value="intra">Intra-state</option>
                        <option value="inter">Inter-state</option>
                    </select>
                </div>
            </div>
            <div class="flex rounded-lg ring-1 ring-[#878fa0] p-0.5 text-sm font-semibold">
                <button type="button" @click="mode='exclusive'; recompute()"
                        :class="mode==='exclusive' ? 'bg-brand-700 text-white' : 'text-gray-600'"
                        class="flex-1 py-2 rounded-md transition">Add GST</button>
                <button type="button" @click="mode='inclusive'; recompute()"
                        :class="mode==='inclusive' ? 'bg-brand-700 text-white' : 'text-gray-600'"
                        class="flex-1 py-2 rounded-md transition">Remove GST</button>
            </div>
        </div>

        <div class="bg-gray-50 border-t border-gray-200 p-4 space-y-2 text-sm tabular-nums">
            <div class="flex justify-between"><span class="text-gray-600">Base amount</span><span class="font-semibold">₹<span x-text="fmt(out.base)"></span></span></div>
            <template x-if="supply==='intra'">
                <div class="space-y-2">
                    <div class="flex justify-between"><span class="text-gray-600">CGST</span><span class="font-semibold">₹<span x-text="fmt(out.cgst)"></span></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">SGST</span><span class="font-semibold">₹<span x-text="fmt(out.sgst)"></span></span></div>
                </div>
            </template>
            <template x-if="supply==='inter'">
                <div class="flex justify-between"><span class="text-gray-600">IGST</span><span class="font-semibold">₹<span x-text="fmt(out.igst)"></span></span></div>
            </template>
            <div class="flex justify-between pt-2 border-t border-gray-200 text-base"><span class="font-semibold text-gray-700">Total</span><span class="font-extrabold text-gray-900">₹<span x-text="fmt(out.total)"></span></span></div>
        </div>
    </div>

    <div class="mt-2 text-center">
        <a href="{{ url('/gst-calculator') }}?utm_source=embed&utm_medium=widget&utm_campaign=powered_by"
           target="_blank" rel="noopener"
           class="text-xs text-gray-500 hover:text-brand-700">Powered by <strong>Apna Invoice</strong> · free GST calculator &amp; invoicing</a>
    </div>
</div>

<script>
    function gstEmbed() {
        return {
            amount: 1000, rate: 18, mode: 'exclusive', supply: 'intra',
            out: { base: 0, gst: 0, cgst: 0, sgst: 0, igst: 0, total: 0 },
            fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
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
                if (this.supply === 'intra') { cgst = +(gst / 2).toFixed(2); sgst = +(gst - cgst).toFixed(2); }
                else { igst = gst; }
                this.out = { base, gst, cgst, sgst, igst, total };
            },
        };
    }
</script>
</body>
</html>
