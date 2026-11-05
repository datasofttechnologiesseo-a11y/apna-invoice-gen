{{--
    HSN/SAC search link - opens the official GST portal's HSN/SAC lookup
    in a small popup window. Drop this partial next to any HSN/SAC field
    so users can look up codes without leaving the form.

    Usage: @include('partials.hsn-search-link')

    Optional:
        $size   - Tailwind size for the SVG (default 'w-3.5 h-3.5')
        $label  - text shown after the icon (default 'Find code'; false for icon only)

    Always labelled by default: an icon alone was missed by users looking
    for the HSN search.
--}}
@php
    $size ??= 'w-3.5 h-3.5';
    $label ??= 'Find code';
@endphp
<a href="https://services.gst.gov.in/services/searchhsnsac"
   target="_blank" rel="noopener"
   onclick="window.open(this.href, 'hsn_sac_search', 'width=1100,height=750,resizable=yes,scrollbars=yes'); return false;"
   class="inline-flex items-center gap-1 {{ $label ? 'py-2.5 -my-2.5' : 'p-2.5 -m-2.5' }} rounded text-brand-700 hover:text-brand-800 hover:underline underline-offset-2 normal-case font-medium whitespace-nowrap"
   aria-label="Search HSN/SAC code on the official GST portal"
   title="Search HSN/SAC code on the official GST portal">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $size }}" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
    @if ($label)<span class="text-xs">{{ $label }}</span>@endif
</a>
