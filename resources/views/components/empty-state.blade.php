@props([
    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'title',
    'description' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'secondaryHref' => null,
    'secondaryLabel' => null,
])

<div class="p-10 sm:p-14 text-center">
    <div class="mx-auto w-16 h-16 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}"/>
        </svg>
    </div>
    <h3 class="mt-5 font-display text-lg font-bold text-gray-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto leading-relaxed">{{ $description }}</p>
    @endif
    @if ($actionHref && $actionLabel)
        <div class="mt-6 flex items-center justify-center gap-3 flex-wrap">
            <a href="{{ $actionHref }}"
               class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm transition">
                {{ $actionLabel }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @if ($secondaryHref && $secondaryLabel)
                <a href="{{ $secondaryHref }}" class="text-sm text-gray-600 hover:text-brand-700 font-medium">
                    {{ $secondaryLabel }}
                </a>
            @endif
        </div>
    @endif
</div>
