<x-app-layout title="GSTR-3B summary">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Reports', 'href' => route('reports.index')],
            ['label' => 'GSTR-3B summary'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">GSTR-3B summary</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $periodLabel }} · {{ $company->name }}</p>
            </div>
            <x-export-toolbar
                :pdf="route('finance.gstr3b.export.pdf', $period->query())"
                :csv="route('finance.gstr3b.export.csv', $period->query())"
                print="window" />
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

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
                <div class="p-3 sm:p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-xs font-medium text-gray-600">Invoices in period</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $invoiceCount }}</div>
                </div>
                <div class="p-3 sm:p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-xs font-medium text-gray-600">Expenses in period</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $expenseCount }}</div>
                </div>
                <div class="p-3 sm:p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                    <div class="text-xs font-medium text-gray-600">Cash memos</div>
                    <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $cashMemoCount }}</div>
                </div>
            </div>

            {{-- ═══ Section 3.1 - Outward + RCM supplies ═══ --}}
            @php
                // [label, note, taxable, igst, cgst, sgst, cess]; null = not applicable (shown as –).
                $rows31 = [
                    ['(a)', 'Outward taxable supplies', 'other than zero rated, nil rated and exempted', $outward['taxable'], $outward['igst'], $outward['cgst'], $outward['sgst'], 0],
                    ['(b)', 'Outward taxable supplies', 'zero rated: exports, SEZ', $outward_zero['taxable'], $outward_zero['igst'], 0, 0, 0],
                    ['(c)', 'Other outward supplies', 'nil rated, exempted', $outward_nil, null, null, null, null],
                    ['(d)', 'Inward supplies', 'liable to reverse charge', $rcm_inward['taxable'], $rcm_inward['igst'], $rcm_inward['cgst'], $rcm_inward['sgst'], 0],
                    ['(e)', 'Non-GST outward supplies', null, 0, null, null, null, null],
                ];
                $heads31 = ['Taxable value', 'IGST', 'CGST', 'SGST', 'Cess'];
                $show = fn ($v) => $v === null ? '–' : rupees($v);
            @endphp
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="g3b-31">
                <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                    <div class="text-xs font-semibold text-brand-700">Section 3.1</div>
                    <h2 id="g3b-31" class="font-display text-base sm:text-lg font-bold text-gray-900">Details of outward supplies and inward supplies liable to reverse charge</h2>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Nature of supplies</th>
                                @foreach ($heads31 as $h)
                                    <th class="px-4 py-3 text-right whitespace-nowrap">{{ $h }} (₹)</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows31 as [$code, $label, $note, $taxable, $igst, $cgst, $sgst, $cess])
                                <tr>
                                    <td class="px-4 py-3"><strong>{{ $code }}</strong> {{ $label }} @if ($note)<span class="text-xs text-gray-500">({{ $note }})</span>@endif</td>
                                    @foreach ([$taxable, $igst, $cgst, $sgst, $cess] as $i => $v)
                                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap {{ $v === null || (float) $v == 0.0 ? 'text-gray-400' : ($i === 0 ? 'font-semibold text-gray-900' : 'text-gray-900') }}">{{ $show($v) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Phones: one block per row, every amount visible without scrolling. --}}
                <ul class="sm:hidden divide-y divide-gray-100">
                    @foreach ($rows31 as [$code, $label, $note, $taxable, $igst, $cgst, $sgst, $cess])
                        <li class="px-4 py-3">
                            <div class="text-sm"><strong>{{ $code }}</strong> {{ $label }}</div>
                            @if ($note)<div class="text-xs text-gray-500">{{ ucfirst($note) }}</div>@endif
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                @foreach ([$taxable, $igst, $cgst, $sgst] as $i => $v)
                                    <div class="flex items-baseline justify-between gap-2">
                                        <dt class="text-xs text-gray-500">{{ $heads31[$i] }}</dt>
                                        <dd class="tabular-nums {{ $v === null || (float) $v == 0.0 ? 'text-gray-400' : 'font-medium text-gray-900' }}">{{ $show($v) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </li>
                    @endforeach
                </ul>
                <div class="px-4 sm:px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    Matches GSTR-1: issued invoices less credit notes dated in the period, excluding drafts and cancelled invoices. Lines at 0% are in (c) as nil rated; exports and SEZ supplies are in (b).
                    Sales where your <strong>customer</strong> pays tax under reverse charge are not part of 3.1 and go in GSTR-1 only.
                    Row (e) is always zero here. Enter non-GST supplies on the portal. Cess is not computed and shows as zero.
                </div>
            </section>

            {{-- ═══ Section 4 - Eligible ITC ═══ --}}
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="g3b-4">
                <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                    <div class="text-xs font-semibold text-brand-700">Section 4</div>
                    <h2 id="g3b-4" class="font-display text-base sm:text-lg font-bold text-gray-900">Eligible ITC</h2>
                </div>
                <div class="overflow-x-auto">
                    {{-- Phones: tighter cells and no Cess column (always zero), so
                         the three tax heads fit without scrolling. Frame only:
                         the rows below are unchanged. --}}
                    <table class="w-full text-sm [&_th]:px-2.5 [&_td]:px-2.5 sm:[&_th]:px-5 sm:[&_td]:px-5 [&_th:last-child]:hidden [&_td:last-child]:hidden sm:[&_th:last-child]:table-cell sm:[&_td:last-child]:table-cell [&_td]:align-top">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
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
                {{-- s.16(2)(aa): credit on a supplier's invoice can be claimed only once it
                     appears in GSTR-2B. The figures above are from the books; this says
                     how much of that 2B confirms, so the books figure is not copied
                     into the return as it is. --}}
                @php $g2b = $itc_2b ?? null; @endphp
                @if ($g2b)
                    <div class="px-4 sm:px-5 py-4 border-t border-gray-200" aria-labelledby="g3b-2b">
                        <h3 id="g3b-2b" class="text-sm font-semibold text-gray-900">Checked against GSTR-2B</h3>
                        <p class="mt-0.5 text-xs text-gray-600">
                            From the GSTR-2B uploaded for {{ collect($g2b['uploaded'])->map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('M Y'))->join(', ', ' and ') }}.
                            @if ($g2b['not_uploaded'])
                                Not uploaded yet for {{ collect($g2b['not_uploaded'])->map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('M Y'))->join(', ', ' and ') }}.
                            @endif
                        </p>
                        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-700">Supplier invoices found in 2B</dt>
                                <dd class="font-mono tabular-nums text-money-800 font-semibold">₹{{ inr($g2b['in_2b']['total']) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-700">Reverse charge you paid <span class="text-xs text-gray-500">(not in 2B, claimable)</span></dt>
                                <dd class="font-mono tabular-nums">₹{{ inr($g2b['rcm']['total']) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-700">In your books, not in 2B yet <span class="text-xs text-gray-500">(hold until the supplier files)</span></dt>
                                <dd class="font-mono tabular-nums {{ $g2b['not_in_2b']['total'] > 0 ? 'text-accent-800 font-semibold' : '' }}">₹{{ inr($g2b['not_in_2b']['total']) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-700">Expenses and cash memos <span class="text-xs text-gray-500">(check on the GST portal)</span></dt>
                                <dd class="font-mono tabular-nums">₹{{ inr($g2b['unchecked']['total']) }}</dd>
                            </div>
                        </dl>
                        <div class="mt-3 rounded-lg ring-1 ring-money-200 bg-money-50 px-4 py-3 text-sm text-gray-800">
                            <strong>Credit confirmed for this return: ₹{{ inr($g2b['confirmed']['total']) }}</strong>
                            <span class="text-gray-600">(IGST ₹{{ inr($g2b['confirmed']['igst']) }} · CGST ₹{{ inr($g2b['confirmed']['cgst']) }} · SGST ₹{{ inr($g2b['confirmed']['sgst']) }})</span>.
                            Claiming only this, tax payable in cash would be <strong>₹{{ inr($g2b['cash_if_2b_only']['total']) }}</strong>.
                            @if ($g2b['unchecked']['total'] > 0)
                                Add the credit on expenses and cash memos only for the bills you can see in 2B on the portal.
                            @endif
                        </div>
                        @if ($g2b['missing']['count'] > 0)
                            <p class="mt-2 text-xs text-gray-600">
                                {{ $g2b['missing']['count'] }} {{ \Illuminate\Support\Str::plural('document', $g2b['missing']['count']) }} in 2B
                                (₹{{ inr($g2b['missing']['tax']) }} of tax) {{ $g2b['missing']['count'] === 1 ? 'is' : 'are' }} not in your books.
                                <a href="{{ route('finance.gstr2b', ['month' => $g2b['uploaded'][0]]) }}" class="font-semibold text-brand-700 hover:underline">Review in GSTR-2B match</a>
                            </p>
                        @endif
                    </div>
                @else
                    <div class="px-4 sm:px-5 py-3 border-t border-gray-200 text-sm text-gray-700">
                        Credit can be claimed only once it appears in GSTR-2B (Section 16(2)(aa)).
                        <a href="{{ route('finance.gstr2b', ['month' => $periodStart->format('Y-m')]) }}" class="font-semibold text-brand-700 hover:underline">Upload GSTR-2B for {{ $periodStart->format('M Y') }}</a>
                        to see how much of this credit it confirms.
                    </div>
                @endif
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    Includes cash memos with a seller GSTIN and expenses with GST input. Claim only ITC that matches GSTR-2B.
                    <span class="block mt-1">Excludes entries marked <strong>not eligible for ITC</strong>, such as blocked credit under Section 17(5) (motor vehicles, staff food and catering, personal use). Mark these on the expense or cash memo.</span>
                </div>
            </section>

            {{-- ═══ Section 6.1 - Payment of tax ═══ --}}
            @php
                // Which credit paid which tax. Shown only while it adds up to
                // the "paid through ITC" row above it.
                $matrix = $setOffMatrix ?? null;
                $matrixOk = is_array($matrix) && collect(['igst', 'cgst', 'sgst'])->every(fn ($h) =>
                    abs(($matrix['igst'][$h] ?? 0) + ($matrix['cgst'][$h] ?? 0) + ($matrix['sgst'][$h] ?? 0) - (float) $itc_used[$h]) < 0.01);
                $paise = fn ($v) => \App\Support\IndianNumber::format((float) $v, 2);
                $cellNum = 'px-2.5 sm:px-5 py-3 text-right tabular-nums whitespace-nowrap';
                $cellLabel = 'px-2.5 sm:px-5 py-3';
            @endphp
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="g3b-61">
                <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                    <div class="text-xs font-semibold text-brand-700">Section 6.1</div>
                    <h2 id="g3b-61" class="font-display text-base sm:text-lg font-bold text-gray-900">Payment of tax</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                            <tr>
                                <th class="{{ $cellLabel }} text-left">Description</th>
                                <th class="{{ $cellNum }}">IGST (₹)</th>
                                <th class="{{ $cellNum }}">CGST (₹)</th>
                                <th class="{{ $cellNum }}">SGST (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="{{ $cellLabel }} text-gray-700">Tax on outward supplies <span class="text-xs text-gray-500">(3.1(a) + (b))</span></td>
                                <td class="{{ $cellNum }}">{{ rupees($liability['igst']) }}</td>
                                <td class="{{ $cellNum }}">{{ rupees($liability['cgst']) }}</td>
                                <td class="{{ $cellNum }}">{{ rupees($liability['sgst']) }}</td>
                            </tr>
                            <tr>
                                <td class="{{ $cellLabel }} text-gray-700">Less: paid through ITC <span class="text-xs text-gray-500">(Section 49(5): IGST credit first)</span></td>
                                <td class="{{ $cellNum }} text-gray-900">−{{ rupees($itc_used['igst']) }}</td>
                                <td class="{{ $cellNum }} text-gray-900">−{{ rupees($itc_used['cgst']) }}</td>
                                <td class="{{ $cellNum }} text-gray-900">−{{ rupees($itc_used['sgst']) }}</td>
                            </tr>
                            @if ($matrixOk && array_sum(array_map('array_sum', $matrix)) > 0)
                                @foreach (['igst' => 'IGST', 'cgst' => 'CGST', 'sgst' => 'SGST'] as $credit => $creditLabel)
                                    @continue(array_sum($matrix[$credit]) <= 0)
                                    <tr class="bg-gray-50/60 text-xs text-gray-600">
                                        <td class="{{ $cellLabel }} py-2 pl-5 sm:pl-10">from {{ $creditLabel }} credit</td>
                                        @foreach (['igst', 'cgst', 'sgst'] as $head)
                                            <td class="{{ $cellNum }} py-2 {{ $matrix[$credit][$head] > 0 ? '' : 'text-gray-400' }}">{{ $matrix[$credit][$head] > 0 ? '−' . $paise($matrix[$credit][$head]) : '–' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endif
                            <tr>
                                <td class="{{ $cellLabel }} text-gray-700">Add: reverse charge on inward supplies <span class="text-xs text-gray-500">(3.1(d), payable in cash only)</span></td>
                                <td class="{{ $cellNum }}">+{{ rupees($rcm_inward['igst']) }}</td>
                                <td class="{{ $cellNum }}">+{{ rupees($rcm_inward['cgst']) }}</td>
                                <td class="{{ $cellNum }}">+{{ rupees($rcm_inward['sgst']) }}</td>
                            </tr>
                            <tr class="bg-gray-50 border-t-2 border-gray-300">
                                <td class="{{ $cellLabel }} font-bold text-gray-900">Net cash payable</td>
                                <td class="{{ $cellNum }} font-bold text-gray-900">{{ rupees($netCash['igst']) }}</td>
                                <td class="{{ $cellNum }} font-bold text-gray-900">{{ rupees($netCash['cgst']) }}</td>
                                <td class="{{ $cellNum }} font-bold text-gray-900">{{ rupees($netCash['sgst']) }}</td>
                            </tr>
                            @if ($itc_carried['total'] > 0)
                                <tr>
                                    <td class="{{ $cellLabel }} text-gray-600">ITC carried forward <span class="text-xs text-gray-500">(unused credit, available next month)</span></td>
                                    <td class="{{ $cellNum }} text-gray-700">{{ rupees($itc_carried['igst']) }}</td>
                                    <td class="{{ $cellNum }} text-gray-700">{{ rupees($itc_carried['cgst']) }}</td>
                                    <td class="{{ $cellNum }} text-gray-700">{{ rupees($itc_carried['sgst']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @if ($matrixOk && array_sum(array_map('array_sum', $matrix)) > 0)
                    <div class="px-4 sm:px-5 py-2.5 border-t border-gray-100 text-xs text-gray-500">
                        The "from … credit" lines show which credit paid each tax, in rupees and paise. IGST credit is used first, on IGST and then on CGST and SGST; CGST and SGST credit go to their own head, then to IGST.
                    </div>
                @endif

                {{-- Total --}}
                <div class="px-4 sm:px-5 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Total cash to deposit</div>
                        <div class="text-xs text-gray-500 mt-0.5">IGST + CGST + SGST · pay by PMT-06 challan on the GST portal</div>
                    </div>
                    <div class="font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ rupees($netCash['total']) }}</div>
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
