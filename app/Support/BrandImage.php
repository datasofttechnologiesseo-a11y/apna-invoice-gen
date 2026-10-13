<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The business's logo and signature on invoices, quotations, credit notes and
 * receipts.
 *
 * On upload the image is cleaned the way a designer would before putting it
 * on a letterhead: turned upright (phone photos), blank margins trimmed, a
 * signature's paper background made transparent, oversized images scaled
 * down, and stored as PNG. If anything about the file is unusual, the
 * original is stored as it is.
 *
 * On documents, fit() gives the exact width and height for the space
 * available, so a wide wordmark and a square emblem both sit right.
 */
class BrandImage
{
    // Enough for sharp print in the space a logo or signature gets on an A4
    // document (about 5 cm wide at 300 dpi), and light enough that invoices
    // stay quick to share on WhatsApp.
    private const LOGO_MAX = [800, 400];
    private const SIGNATURE_MAX = [600, 200];
    /** Colour distance (0-441) below which a pixel counts as background. */
    private const BG_TOLERANCE = 40;

    public static function storeLogo(UploadedFile $file): string
    {
        return self::store($file, 'logos', false);
    }

    public static function storeSignature(UploadedFile $file): string
    {
        return self::store($file, 'signatures', true);
    }

    /**
     * Display size for a stored image inside a $boxW x $boxH box, keeping its
     * shape. Null when there is no image or the file is missing.
     *
     * @return array{file: string, url: string, w: int, h: int, ratio: float}|null
     */
    public static function fit(?string $storedPath, int $boxW, int $boxH): ?array
    {
        if (! $storedPath) {
            return null;
        }
        $file = public_path('storage/' . $storedPath);
        if (! is_file($file)) {
            return null;
        }
        $size = @getimagesize($file);
        if (! $size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }
        $ratio = $size[0] / $size[1];
        $w = min($boxW, $boxH * $ratio);
        $h = $w / $ratio;

        return ['file' => $file, 'url' => asset('storage/' . $storedPath), 'w' => (int) round($w), 'h' => (int) round($h), 'ratio' => $ratio];
    }

    private static function store(UploadedFile $file, string $dir, bool $signature): string
    {
        try {
            $cleaned = self::clean($file, $signature);
        } catch (\Throwable) {
            $cleaned = null;
        }
        if ($cleaned === null) {
            return $file->store($dir, 'public');
        }
        [$bytes, $ext] = $cleaned;
        $path = $dir . '/' . Str::random(40) . '.' . $ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * The cleaned image as [bytes, extension], or null to keep the original.
     * PNG keeps transparency; a photographed (JPEG) logo is kept as JPEG when
     * that is smaller.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function clean(UploadedFile $file, bool $signature): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $img) {
            return null;
        }
        $img = self::truecolor($img);
        $img = self::upright($img, $file);

        // Work on a copy no larger than 1600 px so trimming stays quick.
        $img = self::scaleDown($img, 1600, 1600);
        $bg = self::background($img);
        $img = self::trim($img, $bg);
        if ($signature && $bg !== null) {
            self::clearBackground($img, $bg);
        }
        [$maxW, $maxH] = $signature ? self::SIGNATURE_MAX : self::LOGO_MAX;
        $img = self::scaleDown($img, $maxW, $maxH);

        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();

        $jpg = '';
        $wasJpeg = in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true);
        if ($wasJpeg && ! $signature) {
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            ob_start();
            imagejpeg($flat, null, 90);
            $jpg = (string) ob_get_clean();
            imagedestroy($flat);
        }
        imagedestroy($img);

        if ($png === '') {
            return null;
        }

        return ($jpg !== '' && strlen($jpg) < strlen($png)) ? [$jpg, 'jpg'] : [$png, 'png'];
    }

    private static function truecolor(\GdImage $img): \GdImage
    {
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);

        return $img;
    }

    /** Phone photos carry their rotation in EXIF instead of in the pixels. */
    private static function upright(\GdImage $img, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $img;
        }
        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, imagecolorallocatealpha($img, 255, 255, 255, 127));

        return $rotated ? self::truecolor($rotated) : $img;
    }

    private static function scaleDown(\GdImage $img, int $maxW, int $maxH): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxW / $w, $maxH / $h);
        if ($scale >= 1) {
            return $img;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $out;
    }

    /**
     * The background, read from the four corners: 'transparent', an [r, g, b]
     * colour when the corners agree, or null when they do not (a photo or a
     * logo that runs to the edge), in which case nothing is trimmed.
     */
    private static function background(\GdImage $img): string|array|null
    {
        $w = imagesx($img) - 1;
        $h = imagesy($img) - 1;
        $corners = array_map(fn ($p) => imagecolorsforindex($img, imagecolorat($img, $p[0], $p[1])), [[0, 0], [$w, 0], [0, $h], [$w, $h]]);
        if (count(array_filter($corners, fn ($c) => $c['alpha'] >= 110)) >= 3) {
            return 'transparent';
        }
        $ref = $corners[0];
        foreach ($corners as $c) {
            if (self::distance($c, $ref) > self::BG_TOLERANCE) {
                return null;
            }
        }

        return [
            (int) round(array_sum(array_column($corners, 'red')) / 4),
            (int) round(array_sum(array_column($corners, 'green')) / 4),
            (int) round(array_sum(array_column($corners, 'blue')) / 4),
        ];
    }

    private static function isBackground(array $c, string|array $bg): bool
    {
        if ($c['alpha'] >= 110) {
            return true;
        }

        return is_array($bg) && self::distance($c, ['red' => $bg[0], 'green' => $bg[1], 'blue' => $bg[2]]) <= self::BG_TOLERANCE;
    }

    private static function distance(array $a, array $b): float
    {
        return sqrt(($a['red'] - $b['red']) ** 2 + ($a['green'] - $b['green']) ** 2 + ($a['blue'] - $b['blue']) ** 2);
    }

    /** Cut the blank margin around the artwork, keeping a small breathing space. */
    private static function trim(\GdImage $img, string|array|null $bg): \GdImage
    {
        if ($bg === null) {
            return $img;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $content = fn (int $x, int $y) => ! self::isBackground(imagecolorsforindex($img, imagecolorat($img, $x, $y)), $bg);
        $rowHas = function (int $y) use ($w, $content) {
            for ($x = 0; $x < $w; $x++) {
                if ($content($x, $y)) {
                    return true;
                }
            }

            return false;
        };
        $colHas = function (int $x, int $top, int $bottom) use ($content) {
            for ($y = $top; $y <= $bottom; $y++) {
                if ($content($x, $y)) {
                    return true;
                }
            }

            return false;
        };

        $top = 0;
        while ($top < $h && ! $rowHas($top)) {
            $top++;
        }
        if ($top >= $h) {
            return $img; // nothing but background: leave it alone
        }
        $bottom = $h - 1;
        while ($bottom > $top && ! $rowHas($bottom)) {
            $bottom--;
        }
        $left = 0;
        while ($left < $w && ! $colHas($left, $top, $bottom)) {
            $left++;
        }
        $right = $w - 1;
        while ($right > $left && ! $colHas($right, $top, $bottom)) {
            $right--;
        }

        $pad = (int) round(max($right - $left, $bottom - $top) * 0.03);
        $left = max(0, $left - $pad);
        $top = max(0, $top - $pad);
        $right = min($w - 1, $right + $pad);
        $bottom = min($h - 1, $bottom + $pad);
        $cw = $right - $left + 1;
        $ch = $bottom - $top + 1;
        if ($cw === $w && $ch === $h) {
            return $img;
        }
        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopy($out, $img, 0, 0, $left, $top, $cw, $ch);
        imagedestroy($img);

        return $out;
    }

    /** A signature photographed on paper: the paper becomes transparent, the ink stays. */
    private static function clearBackground(\GdImage $img, string|array $bg): void
    {
        if (! is_array($bg)) {
            return; // already transparent
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $ref = ['red' => $bg[0], 'green' => $bg[1], 'blue' => $bg[2]];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $d = self::distance($c, $ref);
                if ($d <= self::BG_TOLERANCE) {
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $c['red'], $c['green'], $c['blue'], 127));
                } elseif ($d < self::BG_TOLERANCE * 2.5) {
                    // Soft edge of the stroke: partly transparent, so it does not show a halo.
                    $alpha = (int) round(127 * (1 - ($d - self::BG_TOLERANCE) / (self::BG_TOLERANCE * 1.5)));
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $c['red'], $c['green'], $c['blue'], max($c['alpha'], $alpha)));
                }
            }
        }
    }
}
