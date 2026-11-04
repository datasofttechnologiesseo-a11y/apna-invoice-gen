{{-- One row of the reports hub: name, what it answers, a star to pin it,
     and one Download menu. Used twice per report (its group and, once
     pinned, Favourites), so ids carry a suffix. --}}
@php
    $d = $row['downloads'];
    $hasMenu = $d || $row['items'];
@endphp
<li class="flex items-center gap-2 sm:gap-3 px-2 sm:px-3 py-2.5" x-show="matches('{{ $row['id'] }}')">
    <button type="button" @click="toggleFav('{{ $row['id'] }}')"
            class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-md text-lg leading-none hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
            :class="isFav('{{ $row['id'] }}') ? 'text-accent-500' : 'text-gray-300 hover:text-gray-500'"
            :aria-pressed="isFav('{{ $row['id'] }}') ? 'true' : 'false'" aria-pressed="false"
            aria-label="Pin {{ $row['name'] }} to favourites">
        <span aria-hidden="true" x-text="isFav('{{ $row['id'] }}') ? '★' : '☆'">☆</span>
    </button>
    <div class="min-w-0 flex-1">
        <a href="{{ $row['href'] }}" class="font-semibold text-gray-900 hover:text-brand-700 hover:underline underline-offset-2">{{ $row['name'] }}</a>
        <div class="text-xs text-gray-500 mt-0.5">{{ $row['purpose'] }}</div>
    </div>
    @if ($hasMenu)
        <div class="shrink-0 flex flex-col items-end">
            <x-export-toolbar label="Download"
                :xlsx="$d['xlsx'] ?? null" :csv="$d['csv'] ?? null" :pdf="$d['pdf'] ?? null" :print="$d['print'] ?? null"
                :items="$row['items']" />
            @if ($row['note'])
                <span class="hidden sm:block mt-0.5 text-[11px] text-gray-500">{{ $row['note'] }}</span>
            @endif
        </div>
    @endif
</li>
