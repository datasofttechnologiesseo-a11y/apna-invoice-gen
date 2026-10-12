<x-app-layout title="Dashboard">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                {{-- Personalised greeting, first name + honorific "ji" (polite,
                     pan-Indian, age-neutral). Falls back to the greeting alone. --}}
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                    @if ($firstName)
                        {{ $greeting }}, {{ $firstName }} ji
                    @else
                        {{ $greeting }}
                    @endif
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ now()->format('l, d M Y') }}
                    @if ($todayIssued > 0)
                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                        <span>{{ $todayIssued }} {{ \Illuminate\Support\Str::plural('invoice', $todayIssued) }} issued today</span>
                    @endif
                    @if ($todayCollected > 0)
                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                        <span>₹{{ rupees($todayCollected) }} received today</span>
                    @endif
                    @if ($festival)
                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                        <span>{{ $festival }} from the Apna Invoice team</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if ($isFirstRun)
                    <a href="{{ route('invoices.create', ['sample' => 1]) }}" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-md transition whitespace-nowrap">Sample invoice</a>
                @endif
                <a href="{{ route('invoices.create') }}" class="inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm transition whitespace-nowrap">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New invoice
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @unless ($setupComplete)
                {{-- Setup checklist is dismissible: users who want to invoice right away
                     can hide it. The choice is remembered in this browser. Business and
                     customer setup stay reachable from Settings and the in-form nudges. --}}
                <div x-data="{ show: (() => { try { return !localStorage.getItem('hideSetupChecklist'); } catch (e) { return true; } })() }" x-show="show" x-cloak
                     class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                    @php
                        // Plain language, no accounting jargon, and one obvious
                        // next action rather than three competing buttons.
                        $steps = [
                            [
                                'done'  => $setup['business'],
                                'title' => 'Your business details',
                                'sub'   => 'Business name and state are enough to start.',
                                'time'  => '30 sec',
                                'href'  => route('company.edit'),
                                'cta'   => 'Add details',
                            ],
                            [
                                'done'  => $setup['customer'],
                                'title' => 'Add your first customer',
                                'sub'   => 'Saved details fill in on every invoice.',
                                'time'  => '20 sec',
                                'href'  => route('customers.create'),
                                'cta'   => 'New customer',
                            ],
                            [
                                'done'  => $setup['first_invoice'],
                                'title' => 'Create your first invoice',
                                'sub'   => 'CGST, SGST and IGST are calculated for you. Share it on WhatsApp.',
                                'time'  => '1 min',
                                'href'  => route('invoices.create'),
                                'cta'   => 'New invoice',
                            ],
                        ];
                        // Only the first unfinished step gets the solid button.
                        $currentStep = collect($steps)->search(fn ($step) => ! $step['done']);
                        $stepsLeft = collect($steps)->reject(fn ($step) => $step['done'])->count();
                    @endphp

                    <div class="px-5 py-4 sm:px-6 bg-brand-50/60 border-b border-gray-100 flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-brand-700">Getting started</div>
                            <h2 class="mt-1 font-display text-lg sm:text-xl font-extrabold text-gray-900">
                                {{ $stepsLeft === 1 ? 'One step left before your first invoice' : "{$stepsLeft} steps to your first invoice" }}
                            </h2>
                            <p class="mt-1 text-gray-600 text-sm">
                                <span class="text-gray-700">सिर्फ़ {{ $stepsLeft }} आसान कदम</span>
                                <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                about two minutes in all.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-full ring-4 ring-brand-100 bg-white text-brand-700 flex items-center justify-center font-display font-extrabold text-base md:text-xl">
                                {{ $setupProgress }}%
                            </div>
                            <button type="button"
                                    @click="try { localStorage.setItem('hideSetupChecklist','1') } catch (e) {}; show=false"
                                    class="inline-flex items-center justify-center w-10 h-10 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition"
                                    aria-label="Hide getting-started checklist"
                                    title="Hide checklist">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($steps as $index => $item)
                            @php $isCurrent = $index === $currentStep; @endphp
                            <div @class([
                                'flex items-center gap-4 px-5 py-4 sm:px-6 transition',
                                'opacity-60' => ! $item['done'] && ! $isCurrent,
                            ])>
                                <div @class([
                                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0 font-display font-extrabold',
                                    'bg-money-700 text-white' => $item['done'],
                                    'bg-brand-700 text-white' => ! $item['done'] && $isCurrent,
                                    'bg-white text-gray-500 border border-gray-300' => ! $item['done'] && ! $isCurrent,
                                ])>
                                    @if ($item['done'])
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <span class="sr-only">Done</span>
                                    @else
                                        {{ $index + 1 }}
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-semibold {{ $item['done'] ? 'text-money-700' : 'text-brand-700' }}">Step {{ $index + 1 }}</span>
                                        @unless ($item['done'])
                                            <span class="text-xs text-gray-600 px-1.5 rounded bg-gray-100">{{ $item['time'] }}</span>
                                        @endunless
                                    </div>
                                    <div @class([
                                        'font-semibold mt-0.5',
                                        'text-gray-500 line-through' => $item['done'],
                                        'text-gray-900' => ! $item['done'],
                                    ])>{{ $item['title'] }}</div>
                                    @unless ($item['done'])
                                        <div class="text-sm text-gray-500 mt-0.5">{{ $item['sub'] }}</div>
                                    @endunless
                                </div>
                                <a href="{{ $item['href'] }}" @class([
                                    'inline-flex items-center h-10 px-4 text-sm font-semibold rounded-md transition whitespace-nowrap',
                                    'text-gray-600 hover:text-gray-900 hover:bg-gray-100' => $item['done'],
                                    'text-white bg-brand-700 hover:bg-brand-800 shadow-sm' => ! $item['done'] && $isCurrent,
                                    'text-gray-800 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => ! $item['done'] && ! $isCurrent,
                                ])>{{ $item['done'] ? 'Edit' : $item['cta'] }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endunless

            @if ($isFirstRun)
                {{-- Nothing billed yet. The checklist above already says what to do;
                     this only shows what the page becomes after the first bill. --}}
                <div class="rounded-xl border border-dashed border-gray-300 bg-white/60 px-5 py-5 sm:px-6">
                    <div class="text-sm font-semibold text-gray-700">After your first invoice, this page shows</div>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ([
                            ['t' => 'Money owed to you', 'd' => 'Outstanding balance and overdue invoices'],
                            ['t' => 'Payments received', 'd' => 'This month and month by month'],
                            ['t' => 'GST returns', 'd' => 'GSTR-1 and GSTR-3B due dates, ready for your CA'],
                        ] as $preview)
                            <div class="rounded-lg bg-gray-50 px-4 py-3">
                                <div class="text-sm font-semibold text-gray-700">{{ $preview['t'] }}</div>
                                <div class="mt-0.5 text-xs text-gray-500 leading-relaxed">{{ $preview['d'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="font-display font-bold text-gray-900">Need help getting started?</div>
                        <p class="text-sm text-gray-600 mt-0.5">Our support team can help you set up your account.</p>
                    </div>
                    <a href="{{ route('pages.contact') }}"
                       class="flex-shrink-0 inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold text-gray-800 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition">
                        Contact support
                    </a>
                </div>
            @else
                @php
                    $month = now()->format('F');
                    $monthRange = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];
                    $tile = 'group block rounded-xl bg-white ring-1 ring-gray-200 hover:ring-brand-300 p-4 sm:p-5 transition';
                    $tileLabel = 'text-sm font-medium text-gray-600';
                    $tileValue = 'mt-1 font-display text-xl sm:text-2xl font-extrabold text-gray-900 tabular-nums whitespace-nowrap';
                @endphp

                {{-- Key figures. One row, one amount style (full rupees), and each
                     figure is the total of the report its tile opens. --}}
                <section aria-label="Key figures" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    @php $netSales = (float) $stats['invoiced_this_month'] - (float) $stats['credited_this_month']; @endphp
                    <a href="{{ route('invoices.index', $monthRange) }}" class="{{ $tile }}" title="View {{ $month }}'s invoices">
                        <div class="{{ $tileLabel }}">Sales this month</div>
                        <div class="{{ $tileValue }}" title="₹{{ rupees($netSales) }}">₹{{ rupees($netSales) }}</div>
                        <div class="mt-1 text-xs text-gray-500">
                            @if ($netSales > 0)
                                {{-- A share, not a second rupee figure: the next tile is the
                                     money received, and two similar amounts side by side read
                                     as the same number shown twice. --}}
                                <span title="₹{{ rupees($stats['paid_this_month']) }} of it received">{{ round($stats['paid_this_month'] / $netSales * 100) }}% of it collected</span>
                                <span class="block">Incl. GST, net of credit notes</span>
                            @else
                                No invoices in {{ $month }} yet
                            @endif
                        </div>
                    </a>
                    <a href="{{ route('reports.show', ['report' => 'receipts'] + $monthRange) }}" class="{{ $tile }}" title="Payments received in {{ $month }}">
                        <div class="{{ $tileLabel }}">Received this month</div>
                        <div class="{{ $tileValue }}">₹{{ rupees($stats['received_this_month']) }}</div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $stats['received_count_this_month'] }} {{ \Illuminate\Support\Str::plural('payment', $stats['received_count_this_month']) }} in {{ $month }}
                        </div>
                    </a>
                    <a href="{{ route('finance.aging') }}" class="{{ $tile }}" title="Receivables aging">
                        <div class="{{ $tileLabel }}">Outstanding receivables</div>
                        <div class="{{ $tileValue }}">₹{{ rupees($stats['outstanding']) }}</div>
                        <div class="mt-1 text-xs {{ $stats['outstanding_overdue'] > 0 ? 'text-danger-700 font-medium' : 'text-gray-500' }}">
                            {{ $stats['outstanding_overdue'] > 0 ? '₹' . rupees($stats['outstanding_overdue']) . ' overdue' : 'Nothing overdue' }}
                        </div>
                    </a>
                    <a href="{{ route('reports.show', ['report' => 'payables-aging']) }}" class="{{ $tile }}" title="Payables aging">
                        <div class="{{ $tileLabel }}">Payables</div>
                        <div class="{{ $tileValue }}">₹{{ rupees($stats['payables']) }}</div>
                        <div class="mt-1 text-xs {{ $stats['payables_overdue'] > 0 ? 'text-danger-700 font-medium' : 'text-gray-500' }}">
                            @if ($stats['payables_overdue'] > 0)
                                ₹{{ rupees($stats['payables_overdue']) }} overdue
                            @elseif ($stats['payables_count'] > 0)
                                {{ $stats['payables_count'] }} unpaid {{ \Illuminate\Support\Str::plural('purchase invoice', $stats['payables_count']) }}
                            @else
                                Nothing to pay
                            @endif
                        </div>
                    </a>
                </section>

            @endif

            @if (! $isFirstRun || count($actionItems) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Needs attention: one list, most urgent first. It only says
                     "nothing" when nothing is overdue, due or low. --}}
                <section class="{{ $gstCalendar ? 'lg:col-span-2' : 'lg:col-span-3' }} min-w-0 bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="attention-title">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                        <h2 id="attention-title" class="font-semibold text-gray-900">Needs attention</h2>
                        @if (count($actionItems) > 0)
                            <span class="text-xs text-gray-500">{{ count($actionItems) }} {{ \Illuminate\Support\Str::plural('item', count($actionItems)) }}</span>
                        @endif
                    </div>
                    @if (count($actionItems) > 0)
                        <ul class="divide-y divide-gray-100">
                            @foreach ($actionItems as $item)
                                @php
                                    $toneClasses = [
                                        'danger' => ['ring' => 'ring-danger-100', 'bg' => 'bg-danger-50', 'text' => 'text-danger-700'],
                                        'accent' => ['ring' => 'ring-accent-100', 'bg' => 'bg-accent-50', 'text' => 'text-accent-700'],
                                        'brand' => ['ring' => 'ring-brand-100', 'bg' => 'bg-brand-50', 'text' => 'text-brand-700'],
                                    ][$item['tone']] ?? ['ring' => 'ring-gray-100', 'bg' => 'bg-gray-50', 'text' => 'text-gray-600'];
                                    $iconPath = [
                                        'overdue' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                        'draft'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                        'stock'   => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                                        'gst'     => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                                    ][$item['icon']] ?? 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                                @endphp
                                <li class="flex items-center gap-3 sm:gap-4 px-5 py-3">
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg ring-1 {{ $toneClasses['ring'] }} {{ $toneClasses['bg'] }} {{ $toneClasses['text'] }} shrink-0" aria-hidden="true">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 text-sm">{{ $item['title'] }}</div>
                                        <div class="text-xs {{ $item['tone'] === 'danger' ? 'text-danger-700' : 'text-gray-500' }}">{{ $item['sub'] }}</div>
                                    </div>
                                    <a href="{{ $item['href'] }}" class="inline-flex items-center justify-center h-10 sm:h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold transition whitespace-nowrap">
                                        {{ $item['cta'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="px-5 py-6 flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-50 ring-1 ring-gray-200 text-gray-500 shrink-0" aria-hidden="true">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Nothing needs attention</div>
                                <div class="text-xs text-gray-500">No overdue invoices, returns due this week or items low on stock.</div>
                            </div>
                        </div>
                    @endif
                </section>

                @if ($gstCalendar)
                    {{-- Last month's GST returns in filing order. Dates from ReturnPeriod. --}}
                    @php
                        $due = function ($date) {
                            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
                            return match (true) {
                                $days < 0 => ['Was due ' . $date->format('d M'), 'text-gray-500'],
                                $days === 0 => ['Due today', 'text-danger-700 font-medium'],
                                default => ['Due ' . $date->format('d M') . ' · in ' . $days . ' ' . \Illuminate\Support\Str::plural('day', $days), $days <= 3 ? 'text-accent-800 font-medium' : 'text-gray-500'],
                            };
                        };
                        [$d1, $c1] = $due($gstCalendar['gstr1_due']);
                        [$d3, $c3] = $due($gstCalendar['gstr3b_due']);
                        $twoBReady = now()->gte($gstCalendar['gstr2b_from']);
                    @endphp
                    <section class="min-w-0 bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="gst-title">
                        <div class="px-5 py-3.5 border-b border-gray-100">
                            <h2 id="gst-title" class="font-semibold text-gray-900">GST returns · {{ $gstCalendar['label'] }}</h2>
                            <p class="text-xs text-gray-500">{{ $gstCalendar['invoices'] }} {{ \Illuminate\Support\Str::plural('invoice', $gstCalendar['invoices']) }} issued in the period</p>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <li>
                                <a href="{{ route('finance.gstr1', $gstCalendar['query']) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">GSTR-1 <span class="font-normal text-gray-500">· sales</span></div>
                                        <div class="text-xs {{ $c1 }}">{{ $d1 }}</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('finance.gstr2b', ['month' => $gstCalendar['query']['month']]) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">GSTR-2B match <span class="font-normal text-gray-500">· purchases</span></div>
                                        @if ($gstCalendar['gstr2b_uploaded'])
                                            <div class="text-xs text-gray-500">Uploaded</div>
                                        @elseif ($twoBReady)
                                            <div class="text-xs text-accent-800 font-medium">Available on the portal. Not uploaded yet.</div>
                                        @else
                                            <div class="text-xs text-gray-500">On the portal from {{ $gstCalendar['gstr2b_from']->format('d M') }}</div>
                                        @endif
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('finance.gstr3b', $gstCalendar['query']) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-gray-900">GSTR-3B <span class="font-normal text-gray-500">· ₹{{ rupees($gstCalendar['cash']) }} to pay</span></div>
                                        <div class="text-xs {{ $c3 }}">{{ $d3 }}</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                        </ul>
                    </section>
                @endif
            </div>
            @endif

            @if ($setupComplete)
                {{-- Recent invoices --}}
                <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden" aria-labelledby="recent-title">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                        <h2 id="recent-title" class="font-semibold text-gray-900">Recent invoices</h2>
                        <a href="{{ route('invoices.index') }}" class="text-sm text-brand-700 hover:text-brand-800 font-semibold">View all</a>
                    </div>
                    @if ($recent->isEmpty())
                        <div class="px-5 py-10 text-center">
                            <p class="text-sm text-gray-500">Issued and draft invoices are listed here.</p>
                            <a href="{{ route('invoices.create') }}" class="mt-4 inline-flex items-center h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md transition">New invoice</a>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($recent as $inv)
                                <li>
                                    <a href="{{ route('invoices.show', $inv) }}" class="flex items-center gap-3 sm:gap-4 px-5 py-3 hover:bg-gray-50 transition">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $inv->customer?->name ?? 'No customer' }}</div>
                                            <div class="text-xs text-gray-500">
                                                <span class="font-mono">{{ $inv->displayNumber() }}</span>
                                                · {{ $inv->invoice_date?->format('d M Y') }}
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-semibold text-gray-900 tabular-nums">₹{{ rupees($inv->grand_total) }}</div>
                                            <x-status-badge :status="$inv->status" :label="$inv->statusLabel()" class="mt-0.5" />
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @unless ($isFirstRun)
                {{-- Trends. Two switches, each sitting on what it controls:
                     Sales/Purchases swaps both cards, Monthly/Yearly changes only
                     the bars. The ring is a snapshot of today and says so.

                     All four bar charts are rendered server-side and toggled with
                     x-show, so switching is instant and costs no extra request. The
                     pair without x-cloak is what a reader gets if Alpine never
                     boots - sales, monthly, the sensible default. --}}
                <section x-data="dashboardCharts()" x-init="boot()" id="insights" class="scroll-mt-24" aria-label="Trends">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                        <h2 class="font-semibold text-gray-900">Trends</h2>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="inline-flex rounded-md ring-1 ring-gray-300 overflow-hidden text-sm bg-white"
                                 role="group" aria-label="Show sales or purchases">
                                <button type="button" @click="setView('sales')" :aria-pressed="(view === 'sales').toString()"
                                        class="h-10 sm:h-9 px-4 border-r border-gray-300 font-medium transition"
                                        :class="view === 'sales' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-50'">Sales</button>
                                <button type="button" @click="setView('purchases')" :aria-pressed="(view === 'purchases').toString()"
                                        class="h-10 sm:h-9 px-4 font-medium transition"
                                        :class="view === 'purchases' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-50'">Purchases</button>
                            </div>

                            <div class="inline-flex rounded-md ring-1 ring-gray-300 overflow-hidden text-sm bg-white"
                                 role="group" aria-label="Show by month or by financial year">
                                <button type="button" @click="setSpan('monthly')" :aria-pressed="(span === 'monthly').toString()"
                                        class="h-10 sm:h-9 px-4 border-r border-gray-300 font-medium transition"
                                        :class="span === 'monthly' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-50'">Monthly</button>
                                <button type="button" @click="setSpan('yearly')" :aria-pressed="(span === 'yearly').toString()"
                                        class="h-10 sm:h-9 px-4 font-medium transition"
                                        :class="span === 'yearly' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-50'">Yearly</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-5 gap-6">
                        <div class="lg:col-span-2 min-w-0">
                            <div x-show="view === 'sales'">
                                <x-donut-chart
                                    title="Collection status"
                                    subtitle="Issued invoices net of credit notes, as of today"
                                    center-label="Billed"
                                    empty="No issued invoices yet."
                                    :href="route('finance.aging')"
                                    href-label="Receivables aging"
                                    :segments="[
                                        [
                                            'label' => 'Collected',
                                            'amount' => $receivables['collected'],
                                            'color' => '#16a34a',
                                            'href' => route('reports.show', ['report' => 'receipts']),
                                            'note' => 'Received against issued invoices',
                                        ],
                                        [
                                            'label' => 'Due',
                                            'amount' => $receivables['due'],
                                            'color' => '#f59e0b',
                                            'href' => route('invoices.index', ['status' => 'outstanding']),
                                            'note' => 'Unpaid, not yet past the due date',
                                        ],
                                        [
                                            'label' => 'Overdue',
                                            'amount' => $receivables['overdue'],
                                            'color' => '#dc2626',
                                            'href' => route('finance.aging'),
                                            'note' => 'Unpaid and past the due date',
                                        ],
                                    ]" />
                            </div>

                            <div x-show="view === 'purchases'" x-cloak>
                                <x-donut-chart
                                    title="Spend by category"
                                    subtitle="Last 12 months · including GST"
                                    center-label="Spent"
                                    empty="No purchases or expenses recorded yet."
                                    :href="route('finance.expenses')"
                                    href-label="All expenses"
                                    :segments="$spendByCategory" />
                            </div>
                        </div>

                        <div class="lg:col-span-3 min-w-0">
                            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-4 flex-wrap">
                                    <div>
                                        {{-- Initial text is the no-JS default; x-text
                                             takes over the moment Alpine boots. --}}
                                        <div class="text-sm font-semibold text-gray-900"
                                             x-text="heading">Invoiced and collected</div>
                                        <div class="text-xs text-gray-500 mt-0.5"
                                             x-text="caption">Last 6 months · select a bar to open that period</div>
                                    </div>
                                    <a href="{{ route('finance.index') }}"
                                       :href="view === 'sales' ? @js(route('finance.index')) : @js(route('finance.expenses'))"
                                       class="text-sm font-semibold text-brand-700 hover:text-brand-800 whitespace-nowrap"
                                       x-text="view === 'sales' ? 'Profit & loss' : 'Expenses'">Profit &amp; loss</a>
                                </div>

                                <div class="mt-4">
                                    <div x-show="combo === 'sales-monthly'">
                                        <x-monthly-bars
                                            :rows="$chartSeries['sales']['monthly']"
                                            label-a="Invoiced" label-b="Collected"
                                            empty="No invoices or receipts in the last 6 months." />
                                    </div>
                                    <div x-show="combo === 'sales-yearly'" x-cloak>
                                        <x-monthly-bars
                                            :rows="$chartSeries['sales']['yearly']"
                                            label-a="Invoiced" label-b="Collected"
                                            empty="No invoices in the last 3 financial years." />
                                    </div>
                                    <div x-show="combo === 'purchases-monthly'" x-cloak>
                                        <x-monthly-bars
                                            :rows="$chartSeries['purchases']['monthly']"
                                            label-a="Spend" label-b="GST paid"
                                            color-a="#fcd34d" color-b="#b45309"
                                            empty="No purchases in the last 6 months." />
                                    </div>
                                    <div x-show="combo === 'purchases-yearly'" x-cloak>
                                        <x-monthly-bars
                                            :rows="$chartSeries['purchases']['yearly']"
                                            label-a="Spend" label-b="GST paid"
                                            color-a="#fcd34d" color-b="#b45309"
                                            empty="No purchases in the last 3 financial years." />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @endunless

            @if ($setupComplete)
                {{-- Quiet footer row: the referral offer and the guide. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @if (\App\Support\Team::can('referrals.index'))
                        <a href="{{ route('referrals.index') }}" class="flex items-center gap-3 rounded-xl bg-white ring-1 ring-gray-200 hover:ring-brand-300 px-4 py-3 transition">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-brand-50 text-brand-700 shrink-0" aria-hidden="true">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                            </span>
                            <span class="min-w-0 text-sm">
                                <span class="block font-semibold text-gray-900">Refer a friend</span>
                                <span class="block text-xs text-gray-500">
                                    @if (auth()->user()->hasLifetimeFreeAccess())
                                        You have lifetime free access. Thank you for spreading the word.
                                    @else
                                        Get lifetime free access when a friend you refer issues their first invoice.
                                    @endif
                                </span>
                            </span>
                        </a>
                    @endif
                    @if (\App\Support\Team::can('help'))
                        <a href="{{ route('help') }}" class="flex items-center gap-3 rounded-xl bg-white ring-1 ring-gray-200 hover:ring-brand-300 px-4 py-3 transition">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 text-gray-600 shrink-0" aria-hidden="true">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <span class="min-w-0 text-sm">
                                <span class="block font-semibold text-gray-900">How to use Apna Invoice</span>
                                <span class="block text-xs text-gray-500">From setting up your business to filing GST returns.</span>
                            </span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Asked after the first issued invoice, then weekly until the review is given. --}}
    <x-review-invite :show="$showReviewInvite" />

    <x-quick-action-fab />

@push('scripts')
<script>
    /**
     * The Sales/Purchases and Monthly/Yearly switches on the trends panel.
     *
     * Every combination is already in the DOM, so this only decides which one
     * is visible - no fetching, no redrawing, no chart library. The choice is
     * remembered per browser, because someone who runs a purchase-heavy
     * business should not have to click Purchases on every single visit.
     */
    function dashboardCharts() {
        return {
            view: 'sales',
            span: 'monthly',

            boot: function () {
                // localStorage throws outright in some privacy modes, so every
                // read and write here is allowed to fail into the default.
                try {
                    var v = localStorage.getItem('dashboard.chart.view');
                    var s = localStorage.getItem('dashboard.chart.span');
                    if (v === 'sales' || v === 'purchases') { this.view = v; }
                    if (s === 'monthly' || s === 'yearly') { this.span = s; }
                } catch (e) { /* first-party storage blocked; defaults stand */ }
            },

            remember: function (key, value) {
                try { localStorage.setItem('dashboard.chart.' + key, value); } catch (e) {}
            },

            setView: function (value) {
                this.view = value;
                this.remember('view', value);
            },

            setSpan: function (value) {
                this.span = value;
                this.remember('span', value);
            },

            /**
             * One string instead of two comparisons.
             *
             * The bar blocks used to test view and span in one expression,
             * ANDed together. That looks harmless and is not: && short-circuits,
             * so an effect whose view half was false never read span, never
             * registered it as a dependency, and never ran again when the
             * Monthly/Yearly switch moved. Reading both halves through one
             * getter means both are always tracked.
             */
            get combo() {
                return this.view + '-' + this.span;
            },

            get heading() {
                return this.view === 'sales' ? 'Invoiced and collected' : 'Spend and GST paid';
            },

            get caption() {
                var window = this.span === 'monthly'
                    ? 'Last 6 months'
                    : 'Last 3 financial years';
                var target = this.view === 'sales' ? 'invoices' : 'expenses';
                return window + ' · select a bar to open that period’s ' + target;
            }
        };
    }
</script>
@endpush
</x-app-layout>
