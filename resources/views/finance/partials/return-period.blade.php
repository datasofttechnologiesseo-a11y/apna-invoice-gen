{{-- Return period picker shared by GSTR-1 and GSTR-3B: a month, or the
     quarter containing it for QRMP (quarterly) filers. --}}
<form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-4 flex flex-wrap items-end gap-3 print:hidden">
    <div>
        <label for="rp-month" class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Return month</label>
        <input id="rp-month" type="month" name="month" value="{{ $period->month() }}" max="{{ now()->format('Y-m') }}"
               class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
    </div>
    <div>
        <label for="rp-frequency" class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Filing frequency</label>
        <select id="rp-frequency" name="frequency" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
            <option value="monthly" @selected(! $period->quarterly)>Monthly</option>
            <option value="quarterly" @selected($period->quarterly)>Quarterly (QRMP)</option>
        </select>
    </div>
    <button class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded text-sm font-semibold">Show</button>
    <div class="text-xs text-gray-500 ml-auto text-right">
        Period: <strong class="text-gray-800">{{ $period->start->format('d M Y') }} to {{ $period->end->format('d M Y') }}</strong><br>
        {{ $return }} due by <strong class="text-gray-800">{{ $due->format('d M Y') }}</strong>
    </div>
</form>
