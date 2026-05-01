<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Cash Memos</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $memos->total() }} total · {{ $company->name }}</p>
            </div>
            <div class="flex items-center gap-2 print:hidden">
                <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:text-gray-700">← All expenses</a>
                <a href="{{ route('finance.cash-memos.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm whitespace-nowrap">+ New cash memo</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
            @endif

            <div class="print:hidden">
                @include('finance.partials.tabs')
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900 print:hidden">
                <div class="font-semibold mb-1">What is a Cash Memo?</div>
                <p>A self-prepared purchase voucher used when you buy something for cash and the seller doesn't issue a formal tax invoice — common with small / unregistered vendors. Each memo also auto-creates a matching Expense entry so it flows into your P&amp;L.</p>
            </div>

            <div class="bg-white p-4 rounded-xl border border-gray-200 print:hidden">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Memo number or seller" class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <button class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Filter</button>
                    @if (request()->anyFilled(['search', 'from', 'to']))
                        <a href="{{ route('finance.cash-memos.index') }}" class="text-sm text-gray-500 hover:text-gray-900">Clear</a>
                    @endif
                </form>

                {{-- Active period banner + bulk export actions (for sending to your CA) --}}
                @php
                    $exportQuery = array_filter([
                        'search' => request('search'),
                        'from'   => request('from'),
                        'to'     => request('to'),
                    ]);
                    $hasFilter = ! empty($exportQuery);
                    $rangeLabel = $hasFilter
                        ? (request('from') && request('to')
                            ? \Carbon\Carbon::parse(request('from'))->format('d M Y') . ' → ' . \Carbon\Carbon::parse(request('to'))->format('d M Y')
                            : 'Filtered')
                        : now()->format('F Y') . ' (defaults to this month — set From/To above for any other range)';
                @endphp
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <span class="text-gray-500">Export range:</span>
                        <strong class="text-gray-900 ml-1">{{ $rangeLabel }}</strong>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('finance.cash-memos.export.pdf', $exportQuery) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded"
                           title="One-page summary PDF of every cash memo in this range — perfect for emailing to your CA">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download PDF
                        </a>
                        <a href="{{ route('finance.cash-memos.export.csv', $exportQuery) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded"
                           title="Excel-friendly CSV for Tally / books-of-accounts import">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                            Excel / CSV
                        </a>
                        <button onclick="window.print()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded"
                                title="Print the on-screen list of cash memos">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="md:hidden space-y-2">
                @forelse ($memos as $m)
                    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('finance.cash-memos.show', $m) }}" class="font-mono font-semibold text-brand-700 hover:underline">{{ $m->memo_number }}</a>
                                <div class="font-medium text-gray-900 mt-1">{{ $m->seller_name }}</div>
                                @if ($m->seller_address)
                                    <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $m->seller_address }}</div>
                                @endif
                                <div class="flex items-center gap-3 text-xs text-gray-500 mt-2">
                                    <span>{{ $m->memo_date->format('d M Y') }}</span>
                                    <span class="uppercase">{{ $m->payment_mode }}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-mono font-bold text-gray-900 tabular-nums">₹{{ number_format((float) $m->grand_total, 2) }}</div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-gray-100 flex items-center gap-3 text-xs print:hidden">
                            <a href="{{ route('finance.cash-memos.show', $m) }}" class="text-brand-700 hover:underline font-medium">View / Print</a>
                            <a href="{{ route('finance.cash-memos.pdf', $m) }}" class="text-emerald-700 hover:underline font-medium">PDF</a>
                            <span class="ml-auto">
                                <x-confirm-form
                                    :action="route('finance.cash-memos.destroy', $m)"
                                    method="DELETE"
                                    title="Delete cash memo {{ $m->memo_number }}?"
                                    message="The linked expense entry will also be removed. This cannot be undone."
                                    confirmLabel="Delete memo"
                                    confirmClass="bg-red-600 hover:bg-red-700"
                                    tone="danger">
                                    <button type="button" class="text-red-600 hover:underline">Delete</button>
                                </x-confirm-form>
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400">
                        No cash memos yet.
                        <a href="{{ route('finance.cash-memos.create') }}" class="block mt-2 text-brand-700 underline">Create your first →</a>
                    </div>
                @endforelse
            </div>

            {{-- Print-only header (period + company context). Hidden on screen, visible only on print. --}}
            @php
                $printRangeLabel = (request('from') && request('to'))
                    ? \Carbon\Carbon::parse(request('from'))->format('d M Y') . ' to ' . \Carbon\Carbon::parse(request('to'))->format('d M Y')
                    : (request('from')
                        ? 'From ' . \Carbon\Carbon::parse(request('from'))->format('d M Y')
                        : (request('to')
                            ? 'Up to ' . \Carbon\Carbon::parse(request('to'))->format('d M Y')
                            : 'All cash memos'));
                // Sum totals for the in-page rows (current page only — full-period totals come from the PDF).
                $pageTaxable = $memos->sum('taxable_value');
                $pageTax     = $memos->sum(fn ($m) => (float) $m->total_cgst + (float) $m->total_sgst + (float) $m->total_igst);
                $pageGrand   = $memos->sum('grand_total');
            @endphp
            <div class="print-only print-header" aria-hidden="true">
                <div class="print-title">CASH MEMO STATEMENT</div>
                <div class="print-meta">
                    <div><strong>{{ $company->name }}</strong>@if ($company->gstin) · GSTIN {{ $company->gstin }}@endif</div>
                    <div>Period: <strong>{{ $printRangeLabel }}</strong> · Generated {{ now()->format('d M Y · H:i') }}</div>
                    <div>{{ $memos->total() }} memo{{ $memos->total() === 1 ? '' : 's' }} total ({{ $memos->count() }} on this page)</div>
                </div>
                @if ($memos->lastPage() > 1)
                    <div class="print-warning">
                        Note: Only the {{ $memos->count() }} memo{{ $memos->count() === 1 ? '' : 's' }} on this page will print.
                        Use <strong>Download PDF</strong> for a complete statement covering all {{ $memos->total() }} memos in this period.
                    </div>
                @endif
            </div>

            {{-- Desktop table --}}
            <div class="printable hidden md:block bg-white shadow sm:rounded-lg overflow-hidden print:shadow-none print:rounded-none print:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[700px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Memo No.</th>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Purchased From</th>
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 text-left font-semibold">Mode</th>
                                <th class="px-5 py-3 text-right font-semibold print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($memos as $m)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 whitespace-nowrap font-mono font-semibold text-gray-900">{{ $m->memo_number }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ $m->memo_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ $m->seller_name }}</div>
                                        @if ($m->seller_address)
                                            <div class="text-xs text-gray-500 truncate max-w-xs">{{ $m->seller_address }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums">₹{{ number_format((float) $m->grand_total, 2) }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600 uppercase">{{ $m->payment_mode }}</td>
                                    <td class="px-5 py-3 text-right text-sm whitespace-nowrap print:hidden">
                                        <a href="{{ route('finance.cash-memos.show', $m) }}" class="text-brand-700 hover:underline font-medium" title="Open the memo on screen">View / Print</a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <a href="{{ route('finance.cash-memos.pdf', $m) }}"
                                           class="inline-flex items-center gap-1 text-emerald-700 hover:underline font-medium"
                                           title="Download this cash memo as a PDF">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            PDF
                                        </a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <x-confirm-form
                                            :action="route('finance.cash-memos.destroy', $m)"
                                            method="DELETE"
                                            title="Delete cash memo {{ $m->memo_number }}?"
                                            message="The linked expense entry will also be removed. This cannot be undone."
                                            confirmLabel="Delete memo"
                                            confirmClass="bg-red-600 hover:bg-red-700"
                                            tone="danger">
                                            <button type="button" class="text-red-600 hover:underline">Delete</button>
                                        </x-confirm-form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    No cash memos yet. <a href="{{ route('finance.cash-memos.create') }}" class="text-brand-700 underline">Create your first →</a>
                                </td></tr>
                            @endforelse
                        </tbody>
                        @if ($memos->count() > 0)
                            <tfoot class="hidden print:table-footer-group bg-gray-50 border-t-2 border-gray-900">
                                <tr>
                                    <td class="px-5 py-3 font-bold uppercase text-gray-700 text-xs" colspan="3">Page total ({{ $memos->count() }} {{ Str::plural('memo', $memos->count()) }})</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums">₹{{ number_format((float) $pageGrand, 2) }}</td>
                                    <td class="px-5 py-3" colspan="2"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="print:hidden">{{ $memos->links() }}</div>
        </div>
    </div>

    {{-- Print-only header content (hidden on screen). --}}
    <style>
        .print-only { display: none; }

        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; }

            /* 1. Hide every element on the page by default — kills navbar, footer,
                  cookie banner, sidebar, page header, filter bar, mobile cards,
                  pagination, etc. all in one stroke. */
            body * { visibility: hidden !important; }

            /* 2. Re-show only the print header and the desktop list table. */
            .print-only, .print-only *,
            .printable, .printable * { visibility: visible !important; }
            .print-only { display: block !important; }

            /* 3. Pull the printable region to the top-left of the page so it
                  doesn't sit pushed-down by the now-invisible navbar/header. */
            .print-only.print-header {
                position: absolute !important;
                top: 0 !important; left: 0 !important; right: 0 !important;
                width: 100% !important;
                padding: 0 !important; margin: 0 0 8px 0 !important;
            }
            .printable {
                position: absolute !important;
                top: 80px !important; left: 0 !important; right: 0 !important;
                width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
            }

            /* 4. Print-header typography — black ink only, compact. */
            .print-header { color: #000 !important; }
            .print-title {
                font-size: 18px; font-weight: bold;
                letter-spacing: 2px; text-align: center;
                padding-bottom: 4px; border-bottom: 2px solid #000;
                margin-bottom: 6px;
            }
            .print-meta { font-size: 10px; line-height: 1.5; color: #000; }
            .print-meta div { margin-bottom: 1px; }
            .print-warning {
                margin-top: 6px; padding: 6px 8px;
                border-left: 3px solid #888; background: #f5f5f5;
                font-size: 9.5px; font-style: italic;
            }

            /* 5. Force the desktop table visible even at narrow print widths
                  (md:block normally activates ≥768px, but print viewports vary). */
            .md\:hidden { display: none !important; }
            .md\:block  { display: block !important; }

            /* 6. Print-table styling — strip Tailwind colors/shadows, force B&W. */
            .printable table { width: 100% !important; border-collapse: collapse !important; }
            .printable thead th {
                background: #000 !important; color: #fff !important;
                padding: 5px 6px !important; font-size: 9px !important;
                text-align: left !important; text-transform: uppercase;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .printable thead th.print\:hidden,
            .printable td.print\:hidden { display: none !important; }
            .printable tbody td {
                padding: 5px 6px !important;
                border-bottom: 1px solid #ddd !important;
                font-size: 10px !important;
                color: #000 !important; background: #fff !important;
                white-space: normal !important;
            }
            .printable tfoot td {
                padding: 6px !important;
                border-top: 2px solid #000 !important;
                background: #f0f0f0 !important;
                color: #000 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .printable tbody tr:nth-child(even) td {
                background: #fafafa !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
        }
    </style>
</x-app-layout>
