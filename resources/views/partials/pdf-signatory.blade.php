{{-- "For <business>", the signature (or room to sign by hand), then the
     signatory line: the usual order on an Indian business document. --}}
@php $pdfSig = \App\Support\BrandImage::fit($c->signature_path, 150, 46); @endphp
<table style="width: 190px; margin-left: auto; border-collapse: collapse;">
    <tr><td style="padding: 0; text-align: center; font-size: 9px; font-weight: bold;">For {{ $c->name }}</td></tr>
    <tr><td style="padding: 0; height: 50px; vertical-align: bottom; text-align: center;">
        @if ($pdfSig)
            <img src="{{ $pdfSig['file'] }}" alt="Authorised signature" width="{{ $pdfSig['w'] }}" height="{{ $pdfSig['h'] }}" style="width: {{ $pdfSig['w'] }}px; height: {{ $pdfSig['h'] }}px;">
        @endif
    </td></tr>
    <tr><td style="padding: 3px 0 0; text-align: center; border-top: 1px solid {{ $line ?? '#9ca3af' }}; font-size: 8px; color: #6b7280;">Authorised signatory</td></tr>
</table>
