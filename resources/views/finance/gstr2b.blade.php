<x-app-layout title="GSTR-2B match">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Reports', 'href' => route('reports.index')],
            ['label' => 'GSTR-2B match'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">GSTR-2B match</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $periodLabel }} · {{ $company->name }}</p>
            </div>
            @if ($result['summary']['uploaded'])
                <x-export-toolbar class="self-start" :csv="route('finance.gstr2b.export', ['month' => $period])" print="window" />
            @endif
        </div>
    </x-slot>

    @php
        $s = $result['summary'];
        $cards = [
            ['key' => 'matched', 'label' => 'Matched', 'tone' => 'money', 'hint' => 'Same amounts in 2B and in your books. Safe to claim.'],
            ['key' => 'mismatch', 'label' => 'Amounts differ', 'tone' => 'accent', 'hint' => 'Same invoice, different amount. Check the bill or ask the supplier to correct it.'],
            ['key' => 'missing', 'label' => 'Missing in your books', 'tone' => 'brand', 'hint' => 'Reported by the supplier, not in your books. Add it to claim the credit.'],
            ['key' => 'not_in_2b', 'label' => 'Not in 2B yet', 'tone' => 'danger', 'hint' => 'In your books, not yet filed by the supplier. Hold the credit and follow up.'],
        ];
        $tones = [
            'money' => 'ring-money-200 bg-money-50 text-money-800',
            'accent' => 'ring-accent-200 bg-accent-50 text-accent-800',
            'brand' => 'ring-brand-200 bg-brand-50 text-brand-800',
            'danger' => 'ring-danger-200 bg-danger-50 text-danger-800',
        ];
        $num = 'px-4 py-2.5 text-right tabular-nums whitespace-nowrap';
        // Matching is to the paisa, so this screen shows paise.
        $paise = fn ($v) => \App\Support\IndianNumber::format((float) $v, 2);
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="print:hidden">@include('finance.partials.tabs')</div>

            {{-- Upload --}}
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5" x-data="{ name: '' }">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">{{ $s['uploaded'] ? 'Upload again' : 'Upload GSTR-2B' }}</h2>
                        <ol class="mt-2 text-sm text-gray-600 list-decimal pl-5 space-y-1">
                            <li>On the GST portal, open <strong>Returns &rarr; Returns Dashboard</strong> and pick the month.</li>
                            <li>Under <strong>GSTR-2B</strong>, download the JSON or Excel file.</li>
                            <li>Upload the file here as downloaded. It is matched with your purchase invoices and not sent anywhere.</li>
                        </ol>
                        @if ($s['uploaded'])
                            <p class="mt-3 text-xs text-gray-500">Current file: {{ $s['documents'] }} documents, uploaded {{ \Illuminate\Support\Carbon::parse($s['uploaded_at'])->format('d M Y, h:i A') }}.</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('finance.gstr2b.upload') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        @if ($errors->any())
                            <div class="p-3 rounded bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                        @endif
                        <label class="block border-2 border-dashed border-gray-300 hover:border-brand-500 rounded-xl p-5 text-center cursor-pointer transition">
                            <input type="file" name="file" accept=".json,.zip,.xlsx" required class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                            <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span class="mt-2 block text-sm font-semibold text-gray-800" x-text="name || 'Choose GSTR-2B file'"></span>
                            <span class="block text-xs text-gray-500">JSON, .zip or Excel (.xlsx)</span>
                        </label>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="g2b-month" class="text-sm font-medium text-gray-700 block mb-1">Month (used for Excel files)</label>
                                <input id="g2b-month" type="month" name="month" value="{{ $period }}" max="{{ now()->format('Y-m') }}"
                                       class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                            </div>
                            <button type="submit" class="ml-auto inline-flex items-center h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Upload and match</button>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Period switcher --}}
            <form method="GET" class="flex flex-wrap items-center gap-2 text-sm print:hidden">
                <label for="g2b-view" class="text-gray-600">Show month</label>
                <input id="g2b-view" type="month" name="month" value="{{ $period }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()"
                       class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                @foreach ($uploadedPeriods->take(6) as $p)
                    <a href="{{ route('finance.gstr2b', ['month' => $p]) }}"
                       class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $p === $period ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $p)->format('M Y') }}</a>
                @endforeach
            </form>

            @if (! $s['uploaded'])
                <div class="p-6 rounded-2xl bg-white ring-1 ring-gray-200 text-center text-sm text-gray-600">
                    No GSTR-2B uploaded for {{ $periodLabel }}. Upload the file above to run the match.
                    @if ($s['counts']['not_in_2b'] || $s['counts']['no_gstin'])
                        <div class="mt-2 text-xs text-gray-500">{{ $s['counts']['not_in_2b'] + $s['counts']['no_gstin'] }} purchase invoices in this month are waiting to be matched.</div>
                    @endif
                </div>
            @else
                {{-- Summary --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($cards as $c)
                        <a href="#g2b-{{ $c['key'] }}" class="block p-4 rounded-xl ring-1 {{ $tones[$c['tone']] }} hover:shadow-sm transition">
                            <div class="text-sm font-semibold">{{ $c['label'] }}</div>
                            <div class="mt-1 font-display text-2xl font-extrabold tabular-nums">{{ $s['counts'][$c['key']] }}</div>
                            <div class="text-xs font-semibold tabular-nums">GST ₹{{ $paise($s['tax'][$c['key']]) }}</div>
                            <div class="mt-1 text-xs leading-snug opacity-90">{{ $c['hint'] }}</div>
                        </a>
                    @endforeach
                </div>
                <div class="text-sm text-gray-600">
                    ITC available in this GSTR-2B: <strong class="tabular-nums text-gray-900">₹{{ $paise($s['itc_in_2b']) }}</strong>
                    @if ($s['counts']['no_gstin'])
                        · {{ $s['counts']['no_gstin'] }} purchase {{ \Illuminate\Support\Str::plural('invoice', $s['counts']['no_gstin']) }} from suppliers without a GSTIN (GST ₹{{ $paise($s['tax']['no_gstin']) }}) cannot appear in 2B.
                    @endif
                </div>

                @php
                    $sections = [
                        ['key' => 'mismatch', 'title' => 'Amounts differ', 'open' => true],
                        ['key' => 'missing', 'title' => 'Missing in your books', 'open' => true],
                        ['key' => 'not_in_2b', 'title' => 'Not in 2B yet', 'open' => true],
                        ['key' => 'matched', 'title' => 'Matched', 'open' => false],
                        ['key' => 'no_gstin', 'title' => 'Supplier has no GSTIN', 'open' => false],
                        ['key' => 'notes', 'title' => 'Supplier credit / debit notes', 'open' => false],
                    ];
                @endphp
                @foreach ($sections as $sec)
                    @php $rows = $result[$sec['key']]; @endphp
                    @continue(! count($rows))
                    <details id="g2b-{{ $sec['key'] }}" class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" @if ($sec['open']) open @endif>
                        <summary class="px-5 py-3 cursor-pointer select-none flex items-center justify-between gap-3 hover:bg-gray-50">
                            <span class="font-semibold text-gray-900">{{ $sec['title'] }}</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ count($rows) }}</span>
                        </summary>
                        <div class="overflow-x-auto border-t border-gray-100">
                            <table class="w-full text-sm min-w-[860px]">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">Supplier</th>
                                        <th class="px-4 py-2.5 text-left">Document</th>
                                        <th class="px-4 py-2.5 text-right whitespace-nowrap">2B taxable (₹)</th>
                                        <th class="px-4 py-2.5 text-right whitespace-nowrap">2B GST (₹)</th>
                                        <th class="px-4 py-2.5 text-right whitespace-nowrap">Books taxable (₹)</th>
                                        <th class="px-4 py-2.5 text-right whitespace-nowrap">Books GST (₹)</th>
                                        <th class="px-4 py-2.5 text-right whitespace-nowrap" title="2B GST less books GST">GST difference (₹)</th>
                                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($rows as $row)
                                        @php $e = $row['entry'] ?? null; $b = $row['bill'] ?? null; @endphp
                                        <tr>
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-gray-900 truncate max-w-[16rem]">{{ $e?->supplier_name ?? $b?->vendor?->name ?? '–' }}</div>
                                                <div class="text-xs font-mono text-gray-500">{{ $e?->supplier_gstin ?? $b?->vendor?->gstin ?? 'No GSTIN' }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <div class="font-mono">{{ $e?->document_number ?? $b?->bill_number }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ ($e?->document_date ?? $b?->bill_date)?->format('d M Y') }}
                                                    @if ($e && $e->kind !== 'invoice') · {{ $e->kind === 'debit_note' ? 'Debit note' : 'Credit note' }}@endif
                                                    @if ($e && $e->reverse_charge) · Reverse charge @endif
                                                    @if ($e && ! $e->itc_available) · <span class="text-danger-700 font-semibold">ITC not available</span>@endif
                                                </div>
                                            </td>
                                            @php
                                                // 2B less books: what the supplier reported minus what you booked.
                                                $diff = $row['tax_diff'] ?? null;
                                            @endphp
                                            <td class="{{ $num }}">{{ $e ? $paise($e->taxable_value) : '–' }}</td>
                                            <td class="{{ $num }}">{{ $e ? $paise($e->tax()) : '–' }}</td>
                                            <td class="{{ $num }}">{{ $b ? $paise($b->subtotal) : '–' }}</td>
                                            <td class="{{ $num }}">{{ $b ? $paise($b->total_tax) : '–' }}</td>
                                            <td class="{{ $num }} {{ $diff !== null && abs($diff) > 0.009 ? 'text-accent-800 font-semibold' : 'text-gray-400' }}">
                                                @if ($diff === null)
                                                    –
                                                @elseif (abs($diff) < 0.005)
                                                    0.00
                                                @else
                                                    {{ $diff > 0 ? '+' : '−' }}{{ $paise(abs($diff)) }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                @if ($b)
                                                    <a href="{{ route('purchases.show', $b) }}" class="text-brand-700 hover:underline text-sm font-semibold">Open</a>
                                                @elseif ($sec['key'] === 'missing')
                                                    <a href="{{ route('purchases.create', ['bill_number' => $e->document_number, 'bill_date' => $e->document_date?->toDateString(), 'vendor_gstin' => $e->supplier_gstin, 'vendor_name' => $e->supplier_name]) }}"
                                                       class="inline-flex items-center h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold">+ Add purchase invoice</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
