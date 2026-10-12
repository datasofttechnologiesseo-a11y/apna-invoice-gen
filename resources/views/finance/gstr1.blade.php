<x-app-layout title="GSTR-1 summary">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Reports', 'href' => route('reports.index')],
            ['label' => 'GSTR-1 summary'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">GSTR-1 summary</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $period->label() }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <x-export-toolbar
                    :items="[
                        ['label' => 'Excel for the offline tool', 'hint' => 'One sheet per table', 'href' => route('finance.gstr1.export.xlsx', $period->query())],
                        ['label' => 'CSV, invoice by invoice', 'hint' => 'With credit notes and HSN', 'href' => route('invoices.gstr1', ['from' => $period->start->toDateString(), 'to' => $period->end->toDateString()])],
                    ]"
                    print="window" />
                <a href="{{ route('finance.gstr1.export.json', $period->query()) }}"
                   class="inline-flex items-center gap-1.5 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm"
                   title="Upload on the GST portal: Returns > GSTR-1 > Prepare offline > Upload">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    JSON for the portal
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $docTotal = array_sum(array_column($docs, 'total'));
        $cancelled = array_sum(array_column($docs, 'cancelled'));
        $num = 'px-4 py-2.5 text-right font-mono tabular-nums whitespace-nowrap';
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="print:hidden">
                @include('finance.partials.tabs')
            </div>

            @include('finance.partials.return-period', ['period' => $period, 'due' => $dueDate, 'return' => 'GSTR-1'])

            <div class="p-4 rounded-xl bg-brand-50 border border-brand-200 text-sm text-brand-900 leading-relaxed">
                Issued invoices and credit notes for the period. Drafts are excluded; cancelled invoices count only in Table 13.
                B2B is based on the GSTIN on the invoice when it was issued. Totals tie to your <a href="{{ route('finance.gstr3b', $period->query()) }}" class="font-semibold underline underline-offset-2">GSTR-3B</a>.
            </div>

            {{-- Counters --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ([
                    ['Invoices issued', $summary['invoices']],
                    ['Credit notes', $summary['credit_notes']],
                    ['Cancelled', $cancelled],
                    ['Documents (Table 13)', $docTotal],
                ] as [$label, $value])
                    <div class="p-3 sm:p-4 rounded-xl bg-white ring-1 ring-gray-200 text-center">
                        <div class="text-xs font-medium text-gray-600">{{ $label }}</div>
                        <div class="mt-1 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Table-wise summary --}}
            @php
                // Labels from the builder say "Rs"; the screen says ₹ everywhere.
                $tableLabel = fn (string $l) => str_replace(['Rs. ', 'Rs '], '₹', $l);
            @endphp
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="g1-tables">
                <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                    <h2 id="g1-tables" class="font-display text-base sm:text-lg font-bold text-gray-900">Summary by GSTR-1 table</h2>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Table</th>
                                <th class="px-4 py-2.5 text-left">Section</th>
                                <th class="px-4 py-2.5 text-right">Records</th>
                                <th class="px-4 py-2.5 text-right whitespace-nowrap">Taxable value (₹)</th>
                                <th class="px-4 py-2.5 text-right whitespace-nowrap">IGST (₹)</th>
                                <th class="px-4 py-2.5 text-right whitespace-nowrap">CGST (₹)</th>
                                <th class="px-4 py-2.5 text-right whitespace-nowrap">SGST/UTGST (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tables as $key => $t)
                                <tr class="{{ $t['records'] ? '' : 'text-gray-500' }}">
                                    <td class="px-4 py-2.5 font-semibold whitespace-nowrap">{{ $t['table'] }}</td>
                                    <td class="px-4 py-2.5">{{ $tableLabel($t['label']) }}</td>
                                    <td class="{{ $num }}">{{ $t['records'] }}</td>
                                    <td class="{{ $num }}">{{ rupees($t['taxable']) }}</td>
                                    <td class="{{ $num }}">{{ rupees($t['igst']) }}</td>
                                    <td class="{{ $num }}">{{ rupees($t['cgst']) }}</td>
                                    <td class="{{ $num }}">{{ rupees($t['sgst']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Phones: one block per table, every amount on screen. --}}
                <ul class="sm:hidden divide-y divide-gray-100">
                    @foreach ($tables as $key => $t)
                        <li class="px-4 py-3 {{ $t['records'] ? '' : 'text-gray-500' }}">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="text-sm min-w-0"><strong>{{ $t['table'] }}</strong> {{ $tableLabel($t['label']) }}</div>
                                <div class="text-xs text-gray-500 shrink-0">{{ $t['records'] }} {{ \Illuminate\Support\Str::plural('record', $t['records']) }}</div>
                            </div>
                            @if ($t['records'])
                                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    @foreach (['taxable' => 'Taxable', 'igst' => 'IGST', 'cgst' => 'CGST', 'sgst' => 'SGST/UTGST'] as $k => $l)
                                        <div class="flex items-baseline justify-between gap-2">
                                            <dt class="text-xs text-gray-500">{{ $l }}</dt>
                                            <dd class="tabular-nums font-medium text-gray-900">₹{{ rupees($t[$k]) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="px-4 sm:px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                    4B: tax is payable by your customer, so IGST/CGST/SGST show as zero. Credit notes on B2C invoices up to ₹1 lakh are netted in Table 7.
                    Not included (enter on the portal if applicable): deemed exports (6C), advances (11), amendments (9A/9C/10).
                </div>
            </section>

            {{-- Ties to GSTR-3B --}}
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="g1-3b">
                <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                    <div class="text-xs font-semibold text-brand-700">Cross-check</div>
                    <h2 id="g1-3b" class="font-display text-base sm:text-lg font-bold text-gray-900">Net figures for GSTR-3B</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[600px]">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold">GSTR-3B row</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Taxable value (₹)</th>
                                <th class="px-4 py-2.5 text-right font-semibold">IGST (₹)</th>
                                <th class="px-4 py-2.5 text-right font-semibold">CGST (₹)</th>
                                <th class="px-4 py-2.5 text-right font-semibold">SGST (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-2.5"><strong>3.1(a)</strong> Outward taxable supplies <span class="text-xs text-gray-500">(4A + 5 + 7 − 9B, excluding reverse charge)</span></td>
                                <td class="{{ $num }} font-semibold">{{ rupees($summary['taxable']) }}</td>
                                <td class="{{ $num }}">{{ rupees($summary['igst']) }}</td>
                                <td class="{{ $num }}">{{ rupees($summary['cgst']) }}</td>
                                <td class="{{ $num }}">{{ rupees($summary['sgst']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5"><strong>3.1(b)</strong> Zero rated <span class="text-xs text-gray-500">(6A exports + 6B SEZ, net of 9B)</span></td>
                                <td class="{{ $num }}">{{ rupees($summary['zero_taxable']) }}</td>
                                <td class="{{ $num }}">{{ rupees($summary['zero_igst']) }}</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5"><strong>3.1(c)</strong> Nil rated <span class="text-xs text-gray-500">(Table 8)</span></td>
                                <td class="{{ $num }}">{{ rupees($summary['nil']) }}</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                            </tr>
                            <tr class="text-gray-600">
                                <td class="px-4 py-2.5">Reverse-charge sales <span class="text-xs text-gray-500">(tax paid by your customer, not in 3B)</span></td>
                                <td class="{{ $num }}">{{ rupees($summary['rcm_taxable']) }}</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                                <td class="{{ $num }} text-gray-400">–</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Detail tables --}}
            @php
                $sections = [
                    ['B2B invoices (4A, 4B, 6B SEZ)', $b2b, ['GSTIN' => 'gstin', 'Receiver' => 'name', 'Invoice' => 'number', 'Date' => 'date', 'Type' => 'inv_typ', 'POS' => 'pos', 'RCM' => 'rcm', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst', 'CGST' => 'cgst', 'SGST' => 'sgst']],
                    ['B2C large (5)', $b2cl, ['Invoice' => 'number', 'Date' => 'date', 'Invoice value' => 'value', 'POS' => 'pos', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst']],
                    ['Exports (6A)', $exp, ['Type' => 'exp_typ', 'Invoice' => 'number', 'Date' => 'date', 'Invoice value' => 'value', 'Port' => 'port_code', 'Shipping bill' => 'shipping_bill_number', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst']],
                    ['B2C others (7)', $b2cs, ['Type' => 'type', 'POS' => 'pos', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst', 'CGST' => 'cgst', 'SGST' => 'sgst']],
                    ['Credit notes, registered (9B)', $cdnr, ['GSTIN' => 'gstin', 'Note' => 'number', 'Date' => 'date', 'Against invoice' => 'invoice', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst', 'CGST' => 'cgst', 'SGST' => 'sgst']],
                    ['Credit notes, unregistered (9B)', $cdnur, ['Type' => 'ur_type', 'Note' => 'number', 'Date' => 'date', 'Against invoice' => 'invoice', 'POS' => 'pos', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst']],
                    ['HSN summary (12)', $hsn, ['Tab' => 'tab', 'HSN/SAC' => 'hsn', 'Description' => 'description', 'UQC' => 'uqc', 'Qty' => 'qty', 'Rate %' => 'rate', 'Taxable' => 'taxable', 'IGST' => 'igst', 'CGST' => 'cgst', 'SGST' => 'sgst']],
                    ['Documents issued (13)', $docs, ['Nature' => 'nature', 'From' => 'from', 'To' => 'to', 'Total' => 'total', 'Cancelled' => 'cancelled']],
                ];
                $money = ['taxable', 'igst', 'cgst', 'sgst', 'value'];
            @endphp
            @foreach ($sections as [$title, $rows, $cols])
                <details class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden group" @if (count($rows) && $loop->first) open @endif>
                    <summary class="px-5 py-3 cursor-pointer select-none flex items-center justify-between gap-3 hover:bg-gray-50">
                        <span class="font-semibold text-gray-900">{{ $title }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ count($rows) ? 'bg-brand-50 text-brand-800' : 'bg-gray-100 text-gray-500' }}">{{ count($rows) }} {{ \Illuminate\Support\Str::plural('row', count($rows)) }}</span>
                    </summary>
                    @if (count($rows))
                        <div class="overflow-x-auto border-t border-gray-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                                    <tr>
                                        @foreach ($cols as $label => $key)
                                            <th class="px-4 py-2 font-semibold whitespace-nowrap {{ in_array($key, $money, true) || in_array($key, ['rate', 'qty', 'total', 'cancelled'], true) ? 'text-right' : 'text-left' }}">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach (array_slice($rows, 0, 300) as $r)
                                        <tr>
                                            @foreach ($cols as $key)
                                                @php $v = $r[$key] ?? ''; @endphp
                                                @if (in_array($key, $money, true))
                                                    <td class="px-4 py-2 text-right font-mono tabular-nums whitespace-nowrap">{{ rupees((float) $v) }}</td>
                                                @elseif (in_array($key, ['rate', 'qty', 'total', 'cancelled'], true))
                                                    <td class="px-4 py-2 text-right font-mono tabular-nums">{{ 0 + (float) $v }}</td>
                                                @elseif ($v instanceof \DateTimeInterface)
                                                    <td class="px-4 py-2 whitespace-nowrap">{{ $v->format('d M Y') }}</td>
                                                @else
                                                    <td class="px-4 py-2 {{ $key === 'description' || $key === 'name' ? 'max-w-[16rem] truncate' : 'whitespace-nowrap' }}" title="{{ $v }}">{{ $v }}</td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($rows) > 300)
                                <div class="px-5 py-2 text-xs text-gray-500 border-t border-gray-100">Showing the first 300 rows. The Excel download has all {{ count($rows) }}.</div>
                            @endif
                        </div>
                    @else
                        <div class="px-5 py-4 text-sm text-gray-500 border-t border-gray-100">No records in this table for the period.</div>
                    @endif
                </details>
            @endforeach
        </div>
    </div>
</x-app-layout>
