<?php

/**
 * Generates the 1200x630 Open Graph / social share card for Apna Invoice.
 *
 *   php scripts/make-og-card.php
 *
 * Output: public/brand/og-card.png  (the image referenced by config/seo.php).
 *
 * Uses GD with Windows TrueType fonts. Re-run after changing the wording or
 * palette. Kept in the repo so the asset is reproducible, not a mystery binary.
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

$im = imagecreatetruecolor($W, $H);
imageantialias($im, true);

// ---- Background: vertical navy gradient (brand-800 -> near-black navy) ----
$top = [30, 58, 138];   // #1e3a8a
$bot = [5, 10, 26];     // #050a1a
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

// Colors for text
$white  = imagecolorallocate($im, 255, 255, 255);
$gold   = imagecolorallocate($im, 245, 185, 66);
$slate  = imagecolorallocate($im, 203, 213, 225); // slate-300

$margin = 84;

// ---- Brand wordmark (top-left) ----
imagettftext($im, 30, 0, $margin, 96, $white, $fontBold, 'Apna Invoice');

// ---- Eyebrow ----
imagettftext($im, 17, 0, $margin, 168, $gold, $fontBold, 'FREE  ·  GST READY  ·  MADE IN INDIA');

// ---- Headline (two lines) ----
imagettftext($im, 62, 0, $margin, 268, $white, $fontBold, 'Free GST invoices');
imagettftext($im, 62, 0, $margin, 352, $white, $fontBold, 'in 60 seconds.');

// ---- Tagline ----
imagettftext($im, 25, 0, $margin, 432, $slate, $fontReg, 'Auto CGST / SGST / IGST  ·  HSN & SAC  ·  UPI QR');
imagettftext($im, 25, 0, $margin, 474, $slate, $fontReg, 'Share on WhatsApp.  No card. Unlimited during beta.');

// ---- Footer line ----
imagettftext($im, 20, 0, $margin, $H - 70, $white, $fontBold, 'apnainvoice.com');
imagettftext($im, 18, 0, $margin + 230, $H - 70, $slate, $fontReg, 'by Datasoft Technologies');

// ---- India tricolour accent bar along the very bottom ----
$barH = 12;
$third = (int) ($W / 3);
$saffron = imagecolorallocate($im, 255, 153, 51);
$ind_white = imagecolorallocate($im, 255, 255, 255);
$green = imagecolorallocate($im, 19, 136, 8);
imagefilledrectangle($im, 0, $H - $barH, $third, $H, $saffron);
imagefilledrectangle($im, $third, $H - $barH, 2 * $third, $H, $ind_white);
imagefilledrectangle($im, 2 * $third, $H - $barH, $W, $H, $green);

// ---- Save ----
$out = __DIR__ . '/../public/brand/og-card.png';
imagepng($im, $out, 9);
imagedestroy($im);

echo "Wrote {$out} (" . filesize($out) . " bytes)\n";
