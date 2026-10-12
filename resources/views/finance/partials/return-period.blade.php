{{-- Return period picker shared by GSTR-1 and GSTR-3B: a month, or the
     quarter containing it for QRMP (quarterly) filers. --}}
<form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-3 sm:p-4 flex flex-wrap items-end gap-3 print:hidden">
    <div>
        <label for="rp-month" class="text-sm font-medium text-gray-700 block mb-1">Return month</label>
        <input id="rp-month" type="month" name="month" value="{{ $period->month() }}" max="{{ now()->format('Y-m') }}"
               class="h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
    </div>
    <div>
        <label for="rp-frequency" class="text-sm font-medium text-gray-700 block mb-1">Filing frequency</label>
        <select id="rp-frequency" name="frequency" class="h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
            <option value="monthly" @selected(! $period->quarterly)>Monthly</option>
            <option value="quarterly" @selected($period->quarterly)>Quarterly (QRMP)</option>
        </select>
    </div>
    <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md bg-white ring-1 ring-inset ring-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50">Apply</button>
    <div class="w-full sm:w-auto text-sm text-gray-500 sm:ml-auto sm:text-right">
        Period: <strong class="text-gray-800">{{ $period->start->format('d M Y') }} to {{ $period->end->format('d M Y') }}</strong><br>
        {{ $return }} due by <strong class="text-gray-800">{{ $due->format('d M Y') }}</strong>
    </div>
</form>
