<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Pick a starting template</h2>
                <p class="mt-1 text-sm text-gray-500">Every template shows a realistic sample below, pre-filled with your company. Use it as-is or tweak before finalising.</p>
            </div>
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700 whitespace-nowrap">← Back to invoices</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Top banner --}}
            <div class="mb-8 p-5 rounded-2xl bg-gradient-to-br from-brand-50 via-white to-accent-50 ring-1 ring-brand-100 flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white flex items-center justify-center flex-shrink-0 shadow-brand">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div class="flex-1 text-sm">
                    <div class="font-display font-bold text-gray-900 text-base">What you're seeing</div>
                    <p class="mt-1 text-gray-600 leading-relaxed">
                        Each card below contains a <strong>mini invoice preview</strong> with sample line items, GST calculated correctly for your state, and the real total. Click <strong class="text-brand-700">Preview full PDF</strong> to see exactly what your customer will receive — with <strong>{{ $company->name }}</strong>'s branding. Pick the closest match — you can edit everything afterwards.
                    </p>
                </div>
            </div>

            {{-- Cards grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($templates as $key => $tpl)
                    @php
                        $totals = $tpl['totals'];
                        $items = $tpl['computed_items'];
                        $hasItems = count($items) > 0 && !empty($items[0]['description']);
                        $gstRates = collect($items)->pluck('gst_rate')->unique()->filter()->sort()->values();
                        $styleKey = $tpl['style'] ?? 'classic';
                        $styleMeta = config('invoice_styles.' . $styleKey, config('invoice_styles.classic'));
                        // Map style → Tailwind classes for the mini preview header — sober tones
                        $styleTheme = [
                            'classic' => ['bg' => 'bg-white', 'border' => 'border-b-2 border-[#1e3a8a]', 'text' => 'text-gray-900', 'accent' => 'text-[#1e3a8a]'],
                            'bold'    => ['bg' => 'bg-white', 'border' => 'border-b-[3px] border-[#c2410c]', 'text' => 'text-[#c2410c]', 'accent' => 'text-[#c2410c]'],
                            'minimal' => ['bg' => 'bg-white', 'border' => 'border-b border-emerald-200', 'text' => 'text-gray-900', 'accent' => 'text-emerald-700'],
                            'retail'  => ['bg' => 'bg-white', 'border' => 'border-b-2 border-slate-900', 'text' => 'text-slate-900 font-mono', 'accent' => 'text-slate-700 font-mono'],
                            'warm'    => ['bg' => 'bg-white', 'border' => 'border-b-2 border-double border-red-700', 'text' => 'text-red-700', 'accent' => 'text-red-700'],
                        ][$styleKey] ?? null;
                        $styleTheme ??= ['bg' => 'bg-white', 'border' => 'border-b border-gray-200', 'text' => 'text-gray-900', 'accent' => 'text-brand-700'];
                    @endphp
                    <div class="group relative bg-white rounded-2xl ring-1 ring-gray-100 hover:ring-brand-300 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all overflow-hidden flex flex-col">

                        {{-- Card header with gradient --}}
                        <div class="p-5 bg-gradient-to-br {{ $tpl['gradient'] }} text-white relative">
                            <div class="flex items-start justify-between gap-2">
                                <div class="w-11 h-11 rounded-xl bg-white/20 ring-1 ring-white/30 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tpl['icon'] }}"/></svg>
                                </div>
                                <span class="px-2.5 py-1 rounded-full bg-white/20 ring-1 ring-white/30 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap">{{ $tpl['tag'] }}</span>
                            </div>
                            <h3 class="mt-3 font-display font-extrabold text-lg leading-tight">{{ $tpl['label'] }}</h3>
                            <p class="mt-1 text-white/85 text-sm leading-snug">{{ $tpl['tagline'] }}</p>
                        </div>

                        {{-- Body --}}
                        <div class="p-5 flex-1 flex flex-col">

                            {{-- Audience chip --}}
                            @if (! empty($tpl['audience']))
                                <div class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="font-medium">Best for:</span> {{ $tpl['audience'] }}
                                </div>
                            @endif

                            {{-- Headline total (only when items exist) --}}
                            @if ($hasItems)
                                <div class="mt-3 flex items-baseline gap-2">
                                    <span class="font-display text-3xl font-extrabold text-gray-900 tracking-tight tabular-nums">
                                        ₹{{ number_format((float) $totals['grand_total']) }}
                                    </span>
                                    <span class="text-xs text-gray-500">total (incl. GST)</span>
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500">
                                    Subtotal ₹{{ number_format((float) $totals['subtotal']) }}
                                    · GST ₹{{ number_format((float) $totals['total_tax']) }}
                                    @if ($gstRates->count() === 1)
                                        ({{ rtrim(rtrim(number_format($gstRates->first(), 2), '0'), '.') }}%)
                                    @elseif ($gstRates->count() > 1)
                                        (mixed: {{ $gstRates->map(fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.') . '%')->implode(', ') }})
                                    @endif
                                </div>
                            @endif

                            {{-- Mini invoice preview --}}
                            <div class="mt-4 rounded-lg ring-1 ring-gray-200 bg-gray-50/60 overflow-hidden">
                                {{-- mini "TAX INVOICE" header (styled per template style) --}}
                                <div class="px-3 py-2 {{ $styleTheme['bg'] }} {{ $styleTheme['border'] }} flex items-center justify-between">
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-bold uppercase tracking-widest {{ $styleTheme['accent'] }}">Tax invoice · {{ $styleMeta['label'] }} style</div>
                                        <div class="text-xs font-semibold {{ $styleTheme['text'] }} truncate">{{ $company->name }}</div>
                                    </div>
                                    <div class="text-[9px] {{ $styleTheme['accent'] }} whitespace-nowrap">INV-XXXX</div>
                                </div>

                                @if ($hasItems)
                                    {{-- line items table --}}
                                    <table class="w-full text-[11px]">
                                        <thead>
                                            <tr class="text-gray-500">
                                                <th class="px-3 py-1.5 text-left font-medium">Item</th>
                                                <th class="px-3 py-1.5 text-right font-medium w-12">Qty</th>
                                                <th class="px-3 py-1.5 text-right font-medium w-10">GST</th>
                                                <th class="px-3 py-1.5 text-right font-medium">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            @foreach ($items as $item)
                                                <tr class="border-t border-gray-100">
                                                    <td class="px-3 py-1.5 text-gray-800 truncate max-w-[140px]" title="{{ $item['description'] }}">{{ $item['description'] }}</td>
                                                    <td class="px-3 py-1.5 text-right font-mono text-gray-600 tabular-nums">{{ rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') }}</td>
                                                    <td class="px-3 py-1.5 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $item['gst_rate'], 2), '0'), '.') }}%</td>
                                                    <td class="px-3 py-1.5 text-right font-mono font-medium tabular-nums">₹{{ number_format((float) $item['amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50/80 border-t border-gray-200 text-[11px]">
                                            <tr>
                                                <td colspan="3" class="px-3 py-1 text-right text-gray-500">Subtotal</td>
                                                <td class="px-3 py-1 text-right font-mono tabular-nums">₹{{ number_format((float) $totals['subtotal']) }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="px-3 py-1 text-right text-gray-500">CGST + SGST</td>
                                                <td class="px-3 py-1 text-right font-mono tabular-nums">₹{{ number_format((float) $totals['total_tax']) }}</td>
                                            </tr>
                                            <tr class="border-t border-gray-300">
                                                <td colspan="3" class="px-3 py-1.5 text-right font-bold text-gray-900">Grand Total</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-bold text-gray-900 tabular-nums">₹{{ number_format((float) $totals['grand_total']) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                @else
                                    <div class="px-3 py-6 text-center text-xs text-gray-400 italic bg-white">
                                        One blank line — fill in whatever you need.
                                    </div>
                                @endif
                            </div>

                            {{-- What this template auto-fills --}}
                            @if ($hasItems)
                                <div class="mt-3 text-xs text-gray-500 leading-relaxed">
                                    Pre-fills {{ count($items) }} line {{ Str::plural('item', count($items)) }} with correct HSN/SAC codes and GST rates. Edit anything before finalising.
                                </div>
                            @endif

                            {{-- Action buttons --}}
                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <a href="{{ route('invoices.templates.preview', $key) }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-white hover:bg-gray-50 text-gray-800 text-sm font-semibold ring-1 ring-gray-300 transition"
                                   title="Opens the full PDF in a new tab with your company branding">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Preview PDF
                                </a>
                                <a href="{{ route('invoices.create', ['template' => $key]) }}"
                                   class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold shadow-sm transition group-hover:shadow-brand">
                                    Use this
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Bottom helper --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-gray-500">
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-money-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    GST calculated to the paisa
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-money-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    HSN/SAC codes pre-filled
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-money-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Edit everything after picking
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-money-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    No commitment — previews don't save
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
