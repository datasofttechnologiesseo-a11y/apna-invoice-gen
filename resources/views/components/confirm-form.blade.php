@props([
    'action',
    'method' => 'POST',
    'title' => 'Are you sure?',
    'message' => null,
    'confirmLabel' => 'Confirm',
    'confirmClass' => 'bg-brand-700 hover:bg-brand-800',
    'tone' => 'default', // default | danger | warning
])

@php
    $toneIcon = [
        'danger' => ['bg' => 'bg-red-100', 'color' => 'text-red-600', 'path' => 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z'],
        'warning' => ['bg' => 'bg-amber-100', 'color' => 'text-amber-600', 'path' => 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z'],
        'default' => ['bg' => 'bg-brand-100', 'color' => 'text-brand-700', 'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ][$tone] ?? null;

    // Deterministic id per usage so the trigger button can target its dialog.
    $id = 'confirm-' . md5($action . $title . mt_rand());
@endphp

<div x-data="{ open: false }" class="inline">
    <div @click="open = true" class="inline">
        {{ $slot }}
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
            <div class="absolute inset-0 bg-black/40" @click="open = false"
                 x-transition:enter="transition-opacity ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"></div>

            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $toneIcon['bg'] }}">
                            <svg class="w-5 h-5 {{ $toneIcon['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $toneIcon['path'] }}"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 id="{{ $id }}-title" class="font-display text-lg font-bold text-gray-900">{{ $title }}</h3>
                            @if ($message)
                                <p class="mt-1 text-sm text-gray-600 leading-relaxed">{{ $message }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 flex justify-end gap-2">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 rounded">
                        Cancel
                    </button>
                    <form method="POST" action="{{ $action }}" class="inline">
                        @csrf
                        @if (strtoupper($method) !== 'POST')
                            @method($method)
                        @endif
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded shadow-sm {{ $confirmClass }}">
                            {{ $confirmLabel }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
