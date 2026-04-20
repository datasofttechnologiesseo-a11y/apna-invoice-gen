@props([
    'id',
    'name',
    'autocomplete' => 'current-password',
    'required' => true,
])

<div class="relative mt-1" x-data="{ show: false }">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="password"
        :type="show ? 'text' : 'password'"
        autocomplete="{{ $autocomplete }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'block w-full pr-10 border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm']) }}>
    <button
        type="button"
        @click="show = !show"
        :aria-label="show ? 'Hide password' : 'Show password'"
        :aria-pressed="show"
        tabindex="-1"
        class="absolute top-1/2 right-2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 focus:text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1 rounded">
        <svg x-show="!show" class="block w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <svg x-show="show" x-cloak class="block w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>
    </button>
</div>
