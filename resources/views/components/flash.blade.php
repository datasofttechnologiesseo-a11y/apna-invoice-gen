@props([
    'message' => null,
    'type' => 'success', // success, error, info, warning
    'auto' => true,       // auto-dismiss after a few seconds (success/info only)
    'timeout' => 6000,    // ms
])

@php
    // Auto-detect from session if no explicit message passed:
    //   session('status')  → success
    //   session('error')   → error (controllers use redirect()->with('error', ...) for things like "books locked")
    //   session('warning') → warning
    //   session('info')    → info
    if ($message === null) {
        if (session('error')) {
            $message = session('error');
            $type = 'error';
        } elseif (session('warning')) {
            $message = session('warning');
            $type = 'warning';
        } elseif (session('info')) {
            $message = session('info');
            $type = 'info';
        } else {
            $message = session('status');
        }
    }
    if (empty($message)) return;

    $palette = [
        'success' => 'bg-money-50 border-money-200 text-money-800',
        'error'   => 'bg-danger-50 border-danger-200 text-danger-800',
        'info'    => 'bg-brand-50 border-brand-200 text-brand-800',
        'warning' => 'bg-accent-50 border-accent-200 text-accent-800',
    ][$type] ?? 'bg-gray-50 border-gray-200 text-gray-800';

    // Errors and warnings stay visible until manually dismissed - they often
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
     class="p-4 border rounded-lg flex items-start gap-3 text-sm {{ $palette }}"
     role="{{ $role }}"
     aria-live="{{ $aria }}">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        @if (in_array($type, ['error', 'warning'], true))
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        @elseif ($type === 'info')
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
        @else
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.58l-1.3-1.3a1 1 0 10-1.4 1.42l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/>
        @endif
    </svg>
    <div class="flex-1">{{ $message }}</div>
    <button type="button" @click="show = false" class="text-current opacity-75 hover:opacity-100 p-2 -m-2" aria-label="Dismiss notification">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
