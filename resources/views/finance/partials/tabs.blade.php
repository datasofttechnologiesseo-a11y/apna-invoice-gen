@php
    $current = request()->routeIs('finance.cash-memos.*') ? 'memos'
        : (request()->routeIs('finance.expenses*') ? 'expenses'
        : (request()->routeIs('finance.aging*') ? 'aging'
        : (request()->routeIs('finance.gstr3b*') ? 'gstr3b' : 'pnl')));
    $periodQs = request()->only(['period', 'from', 'to']);
@endphp
<nav class="bg-white border border-gray-200 rounded-xl p-1 inline-flex flex-wrap gap-1 print:hidden" aria-label="Finance sections">
    <a href="{{ route('finance.index', $periodQs) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors {{ $current === 'pnl' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 16l4-4 3 3 5-7"/></svg>
        P&amp;L Dashboard
    </a>
    <a href="{{ route('finance.expenses', $periodQs) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors {{ $current === 'expenses' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14-4H5m14 8H5m14 4H5"/></svg>
        Expenses
    </a>
    <a href="{{ route('finance.cash-memos.index') }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors {{ $current === 'memos' ? 'bg-brand-700 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Cash Memos
    </a>
    <span class="self-center mx-1 text-gray-300">·</span>
    <a href="{{ route('finance.aging') }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors {{ $current === 'aging' ? 'bg-accent-700 text-white' : 'text-accent-700 hover:bg-accent-50' }}"
       title="Receivables aging — who owes you what, bucketed by days overdue">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Aging
    </a>
    <a href="{{ route('finance.gstr3b') }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors {{ $current === 'gstr3b' ? 'bg-money-700 text-white' : 'text-money-700 hover:bg-money-50' }}"
       title="GSTR-3B summary — your monthly return data, computed from books">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
        GSTR-3B
    </a>
</nav>
