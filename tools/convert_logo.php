<?php
// Convert Apna Invoice JPEG to transparent PNG by making near-white pixels transparent

$src = __DIR__ . '/../public/brand/apna-invoice-logo.jpg';
$dst = __DIR__ . '/../public/brand/apna-invoice-logo.png';

if (!file_exists($src)) {
    fwrite(STDERR, "Source not found: $src\n");
    exit(1);
}

$img = imagecreatefromjpeg($src);
$w = imagesx($img);
$h = imagesy($img);

$out = imagecreatetruecolor($w, $h);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefill($out, 0, 0, $transparent);

// Tolerance: pixels with R,G,B all above this threshold become transparent
$threshold = 235;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
            // Near-white → transparent
            continue;
        }

        // Soft edge: if one channel is bright, reduce alpha proportionally for smooth edges
        $minChannel = min($r, $g, $b);
        if ($minChannel >= 200) {
            $alpha = (int) round(127 * (($minChannel - 200) / 35));
            $alpha = max(0, min(127, $alpha));
            $color = imagecolorallocatealpha($out, $r, $g, $b, $alpha);
        } else {
            $color = imagecolorallocate($out, $r, $g, $b);
        }
        imagesetpixel($out, $x, $y, $color);
    }
}

imagepng($out, $dst, 9);
imagedestroy($img);
imagedestroy($out);

echo "✓ PNG created: $dst (" . number_format(filesize($dst)) . " bytes)\n";
