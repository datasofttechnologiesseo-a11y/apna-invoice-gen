{{--
    Keyboard shortcuts list, opened with "?" (or from the + New menu). Lives
    inside the app layout's x-data, which owns the `shortcuts` flag and the
    key handler. Kept in step with layouts/app (onKey) and the invoice
    form's own keys (invoices/edit, onShortcut).
--}}
@php
    $groups = [
        ['Anywhere', [
            [['/'], 'Search'],
            [['Ctrl', 'K'], 'Search (Cmd+K on a Mac)'],
            [['Alt', 'N'], 'New invoice'],
            [['Alt', 'P'], 'New purchase invoice', 'purchases.create'],
            [['Alt', 'R'], 'Payment received (unpaid invoices)', 'invoices.index'],
            [['?'], 'This list'],
            [['Esc'], 'Close a menu or dialog'],
        ]],
        ['Invoice form', [
            [['F2'], 'Add a line'],
            [['Ctrl', 'S'], 'Save draft'],
            [['F9'], 'Save and preview PDF'],
        ]],
    ];
    $kbd = 'inline-flex items-center justify-center min-w-[1.75rem] h-7 px-1.5 rounded border border-gray-300 bg-gray-50 font-mono text-xs text-gray-800 shadow-[0_1px_0_rgba(0,0,0,0.08)]';
@endphp
<div x-show="shortcuts" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center sm:p-4 print:hidden">
    <div x-show="shortcuts" x-transition.opacity @click="shortcuts = false" class="absolute inset-0 bg-gray-900/40" aria-hidden="true"></div>
    <div x-show="shortcuts" x-transition
         role="dialog" aria-modal="true" aria-labelledby="shortcuts-title"
         class="relative w-full sm:max-w-md max-h-[85vh] overflow-y-auto bg-white rounded-t-2xl sm:rounded-xl shadow-2xl pb-[env(safe-area-inset-bottom)]">
        <div class="flex items-center justify-between gap-2 px-5 pt-4 pb-3 border-b border-gray-100">
            <h2 id="shortcuts-title" class="font-display text-lg font-bold text-gray-900">Keyboard shortcuts</h2>
            <button type="button" @click="shortcuts = false" aria-label="Close"
                    class="inline-flex items-center justify-center w-10 h-10 -mr-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-5">
            @foreach ($groups as [$heading, $rows])
                <section>
                    <h3 class="text-xs font-semibold text-gray-500">{{ $heading }}</h3>
                    <dl class="mt-2 divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            @continue(isset($row[2]) && ! \App\Support\Team::can($row[2]))
                            <div class="flex items-center justify-between gap-4 py-2">
                                <dt class="text-sm text-gray-800">{{ $row[1] }}</dt>
                                <dd class="shrink-0 flex items-center gap-1">
                                    @foreach ($row[0] as $k => $key)
                                        @if ($k > 0)<span class="text-xs text-gray-400" aria-hidden="true">+</span>@endif
                                        <kbd class="{{ $kbd }}">{{ $key }}</kbd>
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
            <p class="text-xs text-gray-500">The shortcuts under Anywhere do not work while you are typing in a field.</p>
        </div>
    </div>
</div>
