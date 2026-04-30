@props([
    'message' => null,
    'type' => 'success', // success, error, info, warning
    'auto' => true,       // auto-dismiss after a few seconds (success/info only)
    'timeout' => 6000,    // ms
])

@php
    $message = $message ?? session('status');
    if (empty($message)) return;

    $palette = [
        'success' => 'bg-money-50 border-money-200 text-money-800',
        'error'   => 'bg-red-50 border-red-200 text-red-800',
        'info'    => 'bg-brand-50 border-brand-200 text-brand-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
    ][$type] ?? 'bg-gray-50 border-gray-200 text-gray-800';

    // Errors and warnings stay visible until manually dismissed — they often
    // describe something the user needs to act on, and a 6-second auto-dismiss
    // means a distracted reader misses the message entirely.
    $shouldAutoDismiss = $auto && in_array($type, ['success', 'info'], true);

    // Errors/warnings get role="alert" for assertive screen-reader announcement.
    $role = in_array($type, ['error', 'warning'], true) ? 'alert' : 'status';
    $aria = in_array($type, ['error', 'warning'], true) ? 'assertive' : 'polite';
@endphp

<div x-data="{ show: true }"
     x-show="show"
     x-init="{{ $shouldAutoDismiss ? "setTimeout(() => show = false, {$timeout})" : '' }}"
     class="p-4 border rounded flex items-start gap-3 {{ $palette }}"
     role="{{ $role }}"
     aria-live="{{ $aria }}">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.58l-1.3-1.3a1 1 0 10-1.4 1.42l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/>
    </svg>
    <div class="flex-1">{{ $message }}</div>
    <button type="button" @click="show = false" class="text-current opacity-60 hover:opacity-100" aria-label="Dismiss notification">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
