<x-app-layout title="GSTR-3B summary">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">GSTR-3B Summary</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $periodLabel }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('finance.gstr3b.export.pdf', $period->query()) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-money-600 hover:bg-money-700 text-white text-sm font-semibold rounded shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download PDF
                </a>
                <a href="{{ route('finance.gstr3b.export.csv', $period->query()) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded shadow-sm">
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
            @include('finance.partials.return-period', ['period' => $period, 'due' => $dueDate, 'return' => 'GSTR-3B'])

            {{-- Notice - what this is, and what it isn't --}}
            <div class="p-4 rounded-xl bg-brand-50 border border-brand-200 text-sm text-brand-900 leading-relaxed">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-brand-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        Computed from your invoices, expenses and cash memos for the period, in the GSTR-3B layout.
                        Nothing is filed with GSTN. Verify against GSTR-1 and your ITC ledger before filing on the portal.
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
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Expenses in period</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $expenseCount }}</div>
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Cash memos</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $cashMemoCount }}</div>
                </div>
            </div>

            {{-- ═══ Section 3.1 - Outward + RCM supplies ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-brand-700 to-brand-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-accent-300">Section 3.1</div>
                    <h2 class="font-display text-lg font-extrabold">Details of outward supplies and inward supplies liable to reverse charge</h2>
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
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">{{ rupees($outward['taxable']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($outward['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($outward['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($outward['sgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(b)</strong> Outward taxable supplies <span class="text-xs text-gray-500">(zero rated: exports, SEZ)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums {{ $outward_zero['taxable'] ? 'font-semibold' : 'text-gray-500' }}">{{ rupees($outward_zero['taxable']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums {{ $outward_zero['igst'] ? '' : 'text-gray-500' }}">{{ rupees($outward_zero['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(c)</strong> Other outward supplies <span class="text-xs text-gray-500">(Nil rated, exempted)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums {{ $outward_nil ? 'font-semibold' : 'text-gray-500' }}">{{ rupees($outward_nil) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                            </tr>
                            <tr class="bg-accent-50/40">
                                <td class="px-5 py-3"><strong>(d)</strong> Inward supplies <span class="text-xs text-gray-500">(liable to reverse charge)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold">{{ rupees($rcm_inward['taxable']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($rcm_inward['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($rcm_inward['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($rcm_inward['sgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(e)</strong> Non-GST outward supplies</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    Matches GSTR-1: issued invoices less credit notes dated in the period, excluding drafts and cancelled invoices. Lines at 0% are in (c) as nil rated; exports and SEZ supplies are in (b).
                    Sales where your <strong>customer</strong> pays tax under reverse charge are not part of 3.1 and go in GSTR-1 only.
                    Row (e) is always zero here. Enter non-GST supplies on the portal.
                </div>
            </section>

            {{-- ═══ Section 4 - Eligible ITC ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-money-700 to-money-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-money-200">Section 4</div>
                    <h2 class="font-display text-lg font-extrabold">Eligible ITC</h2>
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
                            <tr>
                                <td class="px-5 py-3"><strong>(A)(3)</strong> Inward supplies liable to reverse charge <span class="text-xs text-gray-500">(tax you paid on reverse-charge purchase invoices)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_rcm['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_rcm['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_rcm['sgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                            <tr class="bg-money-50/30">
                                <td class="px-5 py-3"><strong>(A)(5)</strong> All other ITC <span class="text-xs text-gray-500">(purchase invoices, expenses and cash memos)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_other['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_other['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($itc_other['sgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3"><strong>(B)</strong> ITC reversed</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">-</td>
                            </tr>
                            <tr class="bg-money-100/50 border-t-2 border-money-300">
                                <td class="px-5 py-3 font-bold">(C) Net ITC available <span class="text-xs font-normal text-gray-500">(A − B)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ rupees($itc['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ rupees($itc['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold">{{ rupees($itc['sgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-500">0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if (($itc_no_gstin ?? 0) > 0)
                    <div class="px-5 py-3 bg-accent-50 border-t border-accent-200 text-sm text-accent-900" role="alert">
                        <strong>Check ₹{{ rupees($itc_no_gstin) }} of this credit.</strong>
                        The supplier has no GSTIN on these purchase invoices or cash memos, so the credit cannot appear in GSTR-2B or be claimed.
                        Add the supplier's GSTIN, or untick "ITC eligible" on those entries.
                    </div>
                @endif
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    Includes cash memos with a seller GSTIN and expenses with GST input. Claim only ITC that matches GSTR-2B.
                    <span class="block mt-1">Excludes entries marked <strong>not eligible for ITC</strong>, such as blocked credit under Section 17(5) (motor vehicles, staff food and catering, personal use). Mark these on the expense or cash memo.</span>
                </div>
            </section>

            {{-- ═══ Section 6.1 - Payment of tax ═══ --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-accent-700 to-accent-800 text-white">
                    <div class="text-[10px] uppercase tracking-widest font-bold text-accent-200">Section 6.1</div>
                    <h2 class="font-display text-lg font-extrabold">Payment of tax</h2>
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
                                <td class="px-5 py-3 text-gray-700">Tax on outward supplies <span class="text-xs text-gray-500">(3.1(a) + (b))</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($liability['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($liability['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ rupees($liability['sgst']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-gray-700">Less: paid through ITC <span class="text-xs text-gray-500">(set-off order under Section 49(5), IGST credit first)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ rupees($itc_used['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ rupees($itc_used['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">−{{ rupees($itc_used['sgst']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-gray-700">Add: reverse charge on inward supplies <span class="text-xs text-gray-500">(3.1(d), payable in cash only)</span></td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">+{{ rupees($rcm_inward['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">+{{ rupees($rcm_inward['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">+{{ rupees($rcm_inward['sgst']) }}</td>
                            </tr>
                            <tr class="bg-accent-50 border-t-2 border-accent-300">
                                <td class="px-5 py-3 font-bold text-accent-900">Net cash payable</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ rupees($netCash['igst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ rupees($netCash['cgst']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums font-bold text-accent-900">{{ rupees($netCash['sgst']) }}</td>
                            </tr>
                            @if ($itc_carried['total'] > 0)
                                <tr>
                                    <td class="px-5 py-3 text-gray-600">ITC carried forward <span class="text-xs text-gray-500">(unused credit, available next month)</span></td>
                                    <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">{{ rupees($itc_carried['igst']) }}</td>
                                    <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">{{ rupees($itc_carried['cgst']) }}</td>
                                    <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">{{ rupees($itc_carried['sgst']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- Big total --}}
                <div class="px-5 py-5 bg-gradient-to-r from-accent-100 to-accent-50 border-t border-accent-200 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-widest font-bold text-accent-800">Total cash to deposit</div>
                        <div class="text-xs text-accent-700 mt-0.5">IGST + CGST + SGST · Pay by PMT-06 challan on the GST portal</div>
                    </div>
                    <div class="font-display text-2xl sm:text-3xl font-extrabold text-accent-900 tabular-nums">₹{{ rupees($netCash['total']) }}</div>
                </div>
            </section>

            {{-- Filing reminder --}}
            <div class="p-4 rounded-xl bg-accent-50 border border-accent-200 text-sm text-accent-900 print:hidden">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <strong>Due date:</strong> GSTR-3B for {{ $periodLabel }} is due by <strong>{{ $dueDate->format('d M Y') }}</strong>{{ $period->quarterly ? ' (quarterly filers: 22nd or 24th after the quarter, by state; pay PMT-06 by the 25th in the first two months)' : ' (20th of the next month)' }}. Late fee ₹50 a day (₹25 CGST + ₹25 SGST; ₹20 a day for a nil return), plus 18% p.a. interest on unpaid tax under Section 50 of the CGST Act.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
