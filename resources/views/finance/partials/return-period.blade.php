{{-- Return period picker shared by GSTR-1 and GSTR-3B: a month, or the
     quarter containing it for QRMP (quarterly) filers. Changing a field
     reloads the page; ‹ › step one return period back or forward. --}}
@php
    $step = $period->quarterly ? 3 : 1;
    $unit = $period->quarterly ? 'quarter' : 'month';
    $prev = \App\Support\ReturnPeriod::for($period->start->copy()->subMonthsNoOverflow($step), $period->quarterly);
    $next = \App\Support\ReturnPeriod::for($period->start->copy()->addMonthsNoOverflow($step), $period->quarterly);
    // The month box stops at this month, so the arrows do too.
    $hasNext = $next->start->lte(now()->startOfMonth());
    $keep = request()->except(['month', 'frequency']);
    $stepUrl = fn ($p) => url()->current() . '?' . http_build_query(array_merge($keep, $p->query()));
    $arrow = 'inline-flex items-center justify-center h-10 w-10 rounded-md bg-white ring-1 ring-inset ring-gray-300 text-gray-700 shrink-0';
@endphp
<form method="GET" x-data @change="$el.requestSubmit ? $el.requestSubmit() : $el.submit()"
      class="bg-white rounded-xl ring-1 ring-gray-200 p-3 sm:p-4 flex flex-wrap items-end gap-3 print:hidden">
    @foreach ($keep as $name => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <div>
        <label for="rp-month" class="text-sm font-medium text-gray-700 block mb-1">Return month</label>
        <div class="flex items-center gap-1.5">
            <a href="{{ $stepUrl($prev) }}" class="{{ $arrow }} hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
               aria-label="Previous {{ $unit }}: {{ $prev->label() }}" title="{{ $prev->label() }}">
                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <input id="rp-month" type="month" name="month" value="{{ $period->month() }}" max="{{ now()->format('Y-m') }}"
                   class="h-10 w-40 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
            @if ($hasNext)
                <a href="{{ $stepUrl($next) }}" class="{{ $arrow }} hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                   aria-label="Next {{ $unit }}: {{ $next->label() }}" title="{{ $next->label() }}">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="{{ $arrow }} text-gray-300 ring-gray-200" aria-hidden="true" title="No later {{ $unit }} yet">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </div>
    <div>
        <label for="rp-frequency" class="text-sm font-medium text-gray-700 block mb-1">Filing frequency</label>
        <select id="rp-frequency" name="frequency" class="h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
            <option value="monthly" @selected(! $period->quarterly)>Monthly</option>
            <option value="quarterly" @selected($period->quarterly)>Quarterly (QRMP)</option>
        </select>
    </div>
    <noscript>
        <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md bg-white ring-1 ring-inset ring-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50">Apply</button>
    </noscript>
    <div class="w-full sm:w-auto text-sm text-gray-500 sm:ml-auto sm:text-right">
        Period: <strong class="text-gray-800">{{ $period->start->format('d M Y') }} to {{ $period->end->format('d M Y') }}</strong><br>
        {{ $return }} due by <strong class="text-gray-800">{{ $due->format('d M Y') }}</strong>
    </div>
</form>
