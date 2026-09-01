{{--
    HSN/SAC search link - opens the official GST portal's HSN/SAC lookup
    in a small popup window. Drop this partial next to any HSN/SAC field
    so users can look up codes without leaving the form.

    Usage: @include('partials.hsn-search-link')

    Optional:
        $size   - Tailwind size for the SVG (default 'w-3.5 h-3.5')
        $label  - alternative text label shown after the icon (default: none, icon-only)
--}}
@php
    $size ??= 'w-3.5 h-3.5';
    $label ??= null;
@endphp
<a href="https://services.gst.gov.in/services/searchhsnsac"
   target="_blank" rel="noopener"
   onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
   class="inline-flex items-center gap-1 text-brand-700 hover:text-brand-800"
   aria-label="Search HSN/SAC code on the official GST portal"
   title="Search HSN/SAC code on the official GST portal">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
    @if ($label)<span class="text-xs">{{ $label }}</span>@endif
</a>
