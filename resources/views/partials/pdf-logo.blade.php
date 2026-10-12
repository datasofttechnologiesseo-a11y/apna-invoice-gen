{{-- The business logo at the top of a printed document, sized to fit: a wide
     wordmark up to 200 x 52 px, a square or tall emblem up to 110 x 60 px. --}}
@php
    $pdfLogo = \App\Support\BrandImage::fit($c->logo_path, 200, 52);
    if ($pdfLogo && $pdfLogo['ratio'] < 2.2) {
        $pdfLogo = \App\Support\BrandImage::fit($c->logo_path, 110, 60);
    }
@endphp
@if ($pdfLogo)
    <img src="{{ $pdfLogo['file'] }}" alt="{{ $c->name }} logo" width="{{ $pdfLogo['w'] }}" height="{{ $pdfLogo['h'] }}" style="display: block; width: {{ $pdfLogo['w'] }}px; height: {{ $pdfLogo['h'] }}px; margin-bottom: 6px;">
@endif
