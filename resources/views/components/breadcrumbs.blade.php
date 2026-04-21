@props([
    'items' => [], // [ ['label' => 'Invoices', 'href' => route(...)], ['label' => 'Edit']  ]
])

@if (! empty($items))
    <nav aria-label="Breadcrumb" class="text-xs text-gray-500 mb-3">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li>
                <a href="{{ route('dashboard') }}" class="hover:text-brand-700 transition inline-flex items-center" aria-label="Dashboard">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </a>
            </li>
            @foreach ($items as $i => $item)
                <li class="flex items-center gap-1.5" @if ($i === count($items) - 1) aria-current="page" @endif>
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    @if (! empty($item['href']) && $i !== count($items) - 1)
                        <a href="{{ $item['href'] }}" class="hover:text-brand-700 transition">{{ $item['label'] }}</a>
                    @else
                        <span class="text-gray-700 font-medium">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
