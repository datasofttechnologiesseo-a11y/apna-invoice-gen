<?php

namespace App\Support;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class UpiQr
{
    /**
     * Build a UPI deep-link string per NPCI spec:
     * upi://pay?pa=...&pn=...&am=...&tn=...&cu=INR
     */
    public static function buildLink(string $upiId, string $payeeName, float $amount, string $note = ''): string
    {
        $params = array_filter([
            'pa' => $upiId,
            'pn' => $payeeName,
            'am' => number_format($amount, 2, '.', ''),
            'tn' => $note,
            'cu' => 'INR',
        ]);
        return 'upi://pay?' . http_build_query($params);
    }

    /**
     * Render a UPI payment QR as an SVG data-URI (works inline in HTML and dompdf).
     */
    public static function svgDataUri(string $upiId, string $payeeName, float $amount, string $note = '', int $size = 220): string
    {
        $link = self::buildLink($upiId, $payeeName, $amount, $note);
        $svg = QrCode::size($size)->margin(1)->format('svg')->generate($link);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
