<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">GSTR-3B Summary</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $periodLabel }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('finance.gstr3b.export.pdf', ['month' => $periodStart->format('Y-m')]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download PDF
                </a>
                <a href="{{ route('finance.gstr3b.export.csv', ['month' => $periodStart->format('Y-m')]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                    Excel / CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Finance section tabs --}}
            <div class="print:hidden">
                @include('finance.partials.tabs')
            </div>

            {{-- Period selector --}}
            <form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-4 flex flex-wrap items-end gap-3 print:hidden">
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Filing month</label>
                    <input type="month" name="month" value="{{ $periodStart->format('Y-m') }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" max="{{ now()->format('Y-m') }}">
                </div>
                <button class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Recalculate</button>
                <div class="text-xs text-gray-500 ml-auto">
                    Defaults to last month (current GSTR-3B filing window). Due 20th of next month.
                </div>
            </form>

            {{-- Notice — what this is, and what it isn't --}}
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-900 leading-relaxed">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-blue-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <strong>What this is:</strong> a return-ready summary computed from your invoices, expenses, and cash memos for the selected month — laid out to mirror the GSTR-3B form on the GST portal.
                        <br><strong>What this is not:</strong> a filing tool. We don't submit to GSTN. Verify against your GSTR-1 and ITC ledgers before you (or your CA) file on the portal. The figures are accurate as far as your books go.
                    </div>
                </div>
            </div>

            {{-- ─── Counters strip ─── --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Invoices in period</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $invoiceCount }}</div>
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Expenses logged</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $expenseCount }}</div>
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Cash memos</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $cashMemoCount }}</div>
                </div>
            </div>

            {{-- ═══ Section 3.1 — Outward + RCM supplies ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-brand-700 to-brand-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-accent-300">Section 3.1</div>
                    <h3 class="font-display text-lg font-extrabold">Details of outward supplies and inward supplies liable to reverse charge</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[700px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Nature of supplies</th>
                                <th class="px-5 py-3 text-right font-semibold">Taxable value (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">IGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">CGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">SGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">Cess (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-money-50/30">
                                <td class="px-5 py-3"><strong>(a)</strong> Outward taxable supplies <span class="text-xs text-gray-500">(other than zero rated, nil rated and exempted)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">{{ number_format($outward['taxable'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['sgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(b)</strong> Outward taxable supplies <span class="text-xs text-gray-500">(zero rated)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(c)</strong> Other outward supplies <span class="text-xs text-gray-500">(Nil rated, exempted)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                            </tr>
                            <tr class="bg-amber-50/40">
                                <td class="px-5 py-3"><strong>(d)</strong> Inward supplies <span class="text-xs text-gray-500">(liable to reverse charge)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">{{ number_format($rcm_outward['taxable'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($rcm_outward['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($rcm_outward['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($rcm_outward['sgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(e)</strong> Non-GST outward supplies</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    Rows (b), (c), (e) are zero because the app currently doesn't track exports / nil-rated / non-GST supplies. If you have these, fill them on the GST portal manually.
                </div>
            </section>

            {{-- ═══ Section 4 — Eligible ITC ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-money-700 to-money-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-money-200">Section 4</div>
                    <h3 class="font-display text-lg font-extrabold">Eligible ITC</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[600px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Details</th>
                                <th class="px-5 py-3 text-right font-semibold">IGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">CGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">SGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">Cess (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-money-50/30">
                                <td class="px-5 py-3"><strong>(A)(5)</strong> All other ITC <span class="text-xs text-gray-500">(from your expenses + cash memos)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($itc['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($itc['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($itc['sgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(B)</strong> ITC reversed</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                            </tr>
                            <tr class="bg-money-100/50 border-t-2 border-money-300">
                                <td class="px-5 py-3 font-bold">(C) Net ITC available <span class="text-xs font-normal text-gray-500">(A − B)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ number_format($itc['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ number_format($itc['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ number_format($itc['sgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-400">0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    ITC is the GST you paid on purchases (cash memos with seller GSTIN + expenses with GST input). Cross-verify against GSTR-2B on the portal — only matched ITC is claimable.
                </div>
            </section>

            {{-- ═══ Section 6.1 — Payment of tax ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-accent-700 to-accent-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-accent-200">Section 6.1</div>
                    <h3 class="font-display text-lg font-extrabold">Payment of tax</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[500px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Description</th>
                                <th class="px-5 py-3 text-right font-semibold">IGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">CGST (₹)</th>
                                <th class="px-5 py-3 text-right font-semibold">SGST (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-5 py-3 text-gray-700">Total tax payable <span class="text-xs text-gray-500">(from outward supplies)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($outward['sgst'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-gray-700">Less: ITC available</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ number_format($itc['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ number_format($itc['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ number_format($itc['sgst'], 2) }}</td>
                            </tr>
                            <tr class="bg-accent-50 border-t-2 border-accent-300">
                                <td class="px-5 py-3 font-bold text-accent-900">Net cash payable</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ number_format($netCash['igst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ number_format($netCash['cgst'], 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ number_format($netCash['sgst'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Big total --}}
                <div class="px-5 py-5 bg-gradient-to-r from-accent-100 to-saffron-50 border-t border-accent-200 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-widest font-bold text-accent-800">Total cash to deposit</div>
                        <div class="text-xs text-accent-700 mt-0.5">Sum of IGST + CGST + SGST · Pay via PMT-06 challan on the GST portal</div>
                    </div>
                    <div class="font-display text-2xl sm:text-3xl font-extrabold text-accent-900 tabular-nums">₹{{ number_format($netCash['total'], 2) }}</div>
                </div>
            </section>

            {{-- Filing reminder --}}
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900 print:hidden">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <strong>Filing window:</strong> GSTR-3B for {{ $periodLabel }} is due by <strong>{{ $periodEnd->copy()->addMonth()->day(20)->format('d M Y') }}</strong> (20th of the next month). Late filing attracts ₹50/day late fee per Act + 18% p.a. interest on unpaid tax under Section 50 CGST.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
