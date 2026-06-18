@props([
    // Primary CTA — defaults to "New invoice", which is the most-pressed
    // button in the app for the target audience (SMEs, freelancers).
    'href' => route('invoices.create'),
    'label' => 'New invoice',
])

{{--
    Mobile-first floating action button. Common mobile-app pattern:
    a primary brand-coloured circle in the bottom-right thumb zone, hidden
    on lg+ where the regular page-header CTA already exists.

    Self-contained — no Alpine, no JS, no z-index conflicts with the existing
    nav (z-10) or admin sidebar overlay (z-30/40). Sits at z-30 so it's above
    table content but below modals.
--}}
<a href="{{ $href }}"
   aria-label="{{ $label }}"
   class="lg:hidden fixed bottom-5 right-5 z-30 inline-flex items-center justify-center w-14 h-14 rounded-full bg-saffron-500 hover:bg-saffron-600 text-white shadow-lg ring-4 ring-white/60 active:scale-95 transition transform">
    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
</a>
