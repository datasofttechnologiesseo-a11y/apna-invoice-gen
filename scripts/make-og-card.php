<?php

/**
 * Generates the 1200x630 Open Graph / social share cards for Apna Invoice.
 *
 *   php scripts/make-og-card.php            # all cards
 *   php scripts/make-og-card.php pricing    # just one
 *
 * Output: public/brand/og-card.png plus public/brand/og/<key>.png
 *
 * Every page used to share one card, so a link to the GST calculator unfurled
 * exactly like a link to the pricing page - the thumbnail told a reader
 * nothing about where they were going. These keep one visual family (teal
 * gradient, gold glow, tricolour bar) and vary only the words, so the set
 * still reads as one brand.
 *
 * Uses GD with Windows TrueType fonts. Re-run after changing wording or
 * palette. Kept in the repo so the assets are reproducible, not mystery
 * binaries.
 */

$W = 1200;
$H = 630;

// Resolve fonts: prefer Segoe UI, fall back to Arial.
$fontBold = null;
$fontReg = null;
foreach (['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf'] as $f) {
    if (is_file($f)) { $fontBold = $f; break; }
}
foreach (['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/arial.ttf'] as $f) {
    if (is_file($f)) { $fontReg = $f; break; }
}
if (! $fontBold || ! $fontReg) {
    fwrite(STDERR, "No usable TrueType font found.\n");
    exit(1);
}

$margin = 84;
$maxTextWidth = $W - ($margin * 2);

/**
 * Draw text, shrinking the point size until it fits the available width.
 *
 * Without this a longer headline silently runs off the edge, and the failure
 * is invisible until someone shares the link. Returns the size used.
 */
function fitText($im, string $text, int $wanted, int $x, int $y, $colour, string $font, int $maxWidth): int
{
    $size = $wanted;
    while ($size > 12) {
        $box = imagettfbbox($size, 0, $font, $text);
        if (($box[2] - $box[0]) <= $maxWidth) {
            break;
        }
        $size--;
    }
    imagettftext($im, $size, 0, $x, $y, $colour, $font, $text);

    return $size;
}

/**
 * @param  array{eyebrow:string, headline:string[], tagline:string[]}  $card
 */
function renderCard(array $card, string $out, string $fontBold, string $fontReg, int $W, int $H, int $margin, int $maxTextWidth): void
{
    $im = imagecreatetruecolor($W, $H);
    imageantialias($im, true);

    // ---- Background: vertical teal gradient (brand-700 -> near-black teal) ----
    $top = [15, 118, 110];  // #0f766e
    $bot = [4, 33, 29];     // #04211d
    for ($y = 0; $y < $H; $y++) {
        $t = $y / $H;
        $r = (int) round($top[0] + ($bot[0] - $top[0]) * $t);
        $g = (int) round($top[1] + ($bot[1] - $top[1]) * $t);
        $b = (int) round($top[2] + ($bot[2] - $top[2]) * $t);
        $c = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $W, $y, $c);
    }

    // ---- Soft accent glow (gold), top-right ----
    $glow = imagecreatetruecolor($W, $H);
    imagealphablending($glow, false);
    $transparent = imagecolorallocatealpha($glow, 0, 0, 0, 127);
    imagefilledrectangle($glow, 0, 0, $W, $H, $transparent);
    imagealphablending($glow, true);
    for ($i = 220; $i > 0; $i -= 4) {
        $alpha = (int) round(115 - ($i / 220) * 115); // fade out at the edge
        $col = imagecolorallocatealpha($glow, 245, 180, 60, max(0, min(127, $alpha)));
        imagefilledellipse($glow, $W - 120, 90, $i, $i, $col);
    }
    imagecopy($im, $glow, 0, 0, 0, 0, $W, $H);
    imagedestroy($glow);

    $white = imagecolorallocate($im, 255, 255, 255);
    $gold = imagecolorallocate($im, 245, 185, 66);
    $slate = imagecolorallocate($im, 203, 213, 225);

    // ---- Brand wordmark (top-left) ----
    imagettftext($im, 30, 0, $margin, 96, $white, $fontBold, 'Apna Invoice');

    // ---- Eyebrow ----
    fitText($im, $card['eyebrow'], 17, $margin, 168, $gold, $fontBold, $maxTextWidth);

    // ---- Headline: one or two lines, auto-fitted ----
    // The glow sits top-right, so a headline never runs under it at this size.
    $y = 268;
    foreach (array_slice($card['headline'], 0, 2) as $line) {
        fitText($im, $line, 62, $margin, $y, $white, $fontBold, $maxTextWidth);
        $y += 84;
    }

    // ---- Tagline ----
    $y = 432;
    foreach (array_slice($card['tagline'], 0, 2) as $line) {
        fitText($im, $line, 25, $margin, $y, $slate, $fontReg, $maxTextWidth);
        $y += 42;
    }

    // ---- Footer line ----
    imagettftext($im, 20, 0, $margin, $H - 70, $white, $fontBold, 'apnainvoice.com');
    imagettftext($im, 18, 0, $margin + 230, $H - 70, $slate, $fontReg, 'by Datasoft Technologies');

    // ---- India tricolour accent bar along the very bottom ----
    $barH = 12;
    $third = (int) ($W / 3);
    imagefilledrectangle($im, 0, $H - $barH, $third, $H, imagecolorallocate($im, 255, 153, 51));
    imagefilledrectangle($im, $third, $H - $barH, 2 * $third, $H, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 2 * $third, $H - $barH, $W, $H, imagecolorallocate($im, 19, 136, 8));

    if (! is_dir(dirname($out))) {
        mkdir(dirname($out), 0775, true);
    }
    imagepng($im, $out, 9);
    imagedestroy($im);
}

/**
 * One entry per page worth promoting.
 *
 * The headline is the page's own promise, not the product's - somebody who
 * shares the calculator is recommending a calculator, and the card should say
 * so. Kept to two short lines because the thumbnail is the size of a stamp in
 * a WhatsApp thread.
 */
$cards = [
    // The default card, referenced by config/seo.php. Unchanged.
    'og-card' => [
        'eyebrow' => 'FREE  ·  GST READY  ·  MADE IN INDIA',
        'headline' => ['Free GST invoices', 'in 60 seconds.'],
        'tagline' => [
            'Auto CGST / SGST / IGST  ·  HSN & SAC  ·  UPI QR',
            'Share on WhatsApp.  No card. Unlimited during beta.',
        ],
    ],
    'og/gst-calculator' => [
        'eyebrow' => 'FREE TOOL  ·  NO SIGN-UP NEEDED',
        'headline' => ['GST calculator', 'for India.'],
        'tagline' => [
            'Add or remove GST at 5, 12, 18 and 28%.',
            'CGST / SGST or IGST, split automatically.',
        ],
    ],
    'og/gst-invoice-format' => [
        'eyebrow' => 'RULE 46  ·  FORMAT + FREE TEMPLATE',
        'headline' => ['GST invoice format,', 'done properly.'],
        'tagline' => [
            'Every mandatory field, explained in plain English.',
            'Make one free, no card, no watermark.',
        ],
    ],
    'og/billing-software' => [
        'eyebrow' => 'FREE DURING BETA  ·  MADE IN INDIA',
        'headline' => ['Free billing software', 'for Indian business.'],
        'tagline' => [
            'Invoices, customers, products, P&L and GST returns.',
            'No per-invoice charge. No feature locks.',
        ],
    ],
    'og/cash-memo-format' => [
        'eyebrow' => 'CASH MEMO  ·  FORMAT + FREE MAKER',
        'headline' => ['Cash memo format,', 'ready to print.'],
        'tagline' => [
            'For sales without GST, and for purchase vouchers.',
            'Make one free in under a minute.',
        ],
    ],
    'og/credit-note-format' => [
        'eyebrow' => 'SECTION 34  ·  FORMAT + RULES',
        'headline' => ['GST credit note', 'format and rules.'],
        'tagline' => [
            'Returns, rate corrections and post-sale discounts.',
            'GSTR-1 compliant, with the 30 November deadline.',
        ],
    ],
    'og/pricing' => [
        'eyebrow' => 'PRICING  ·  NO CARD REQUIRED',
        'headline' => ['Free during beta.', 'Beta users keep it.'],
        'tagline' => [
            'Unlimited invoices, customers and PDF exports.',
            'For Indian freelancers, MSMEs, SMEs and startups.',
        ],
    ],
    'og/how-to-use' => [
        'eyebrow' => 'STEP BY STEP  ·  UNDER TWO MINUTES',
        'headline' => ['How to make a', 'GST invoice free.'],
        'tagline' => [
            'Set up your business, add a customer, bill them.',
            'Then share it on WhatsApp in one tap.',
        ],
    ],
];

$only = $argv[1] ?? null;
$written = 0;

foreach ($cards as $key => $card) {
    if ($only !== null && $key !== $only && basename($key) !== $only) {
        continue;
    }
    $out = __DIR__.'/../public/brand/'.$key.'.png';
    renderCard($card, $out, $fontBold, $fontReg, $W, $H, $margin, $maxTextWidth);
    printf("  %-34s %6.0f KB\n", $key.'.png', filesize($out) / 1024);
    $written++;
}

echo "\n{$written} card(s) written.\n";
