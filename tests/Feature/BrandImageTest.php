<?php

namespace Tests\Feature;

use App\Support\BrandImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded logos and signatures are cleaned so they sit well on invoices:
 * blank margins trimmed, a signature's paper made transparent, stored as PNG.
 */
class BrandImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function upload(\GdImage $img, string $name, string $type = 'png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'brand') . '.' . $type;
        $type === 'jpg' ? imagejpeg($img, $path, 92) : imagepng($img, $path);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function stored(string $path): \GdImage
    {
        return imagecreatefromstring(Storage::disk('public')->get($path));
    }

    public function test_a_logo_with_wide_white_margins_is_trimmed_to_the_artwork(): void
    {
        $img = imagecreatetruecolor(1000, 800);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 350, 325, 649, 474, imagecolorallocate($img, 22, 124, 136)); // 300 x 150 artwork

        $path = BrandImage::storeLogo($this->upload($img, 'logo.png'));

        $this->assertMatchesRegularExpression('/\.(png|jpg)$/', $path);
        $out = $this->stored($path);
        $this->assertLessThan(340, imagesx($out), 'trimmed to the artwork plus a small margin');
        $this->assertGreaterThanOrEqual(300, imagesx($out));
        $this->assertLessThan(190, imagesy($out));
    }

    public function test_a_signature_photographed_on_paper_gets_a_transparent_background(): void
    {
        $img = imagecreatetruecolor(1200, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 236, 234, 228)); // off-white paper
        $ink = imagecolorallocate($img, 20, 30, 90);
        imagesetthickness($img, 6);
        imageline($img, 300, 350, 600, 250, $ink);
        imageline($img, 600, 250, 900, 330, $ink);

        $path = BrandImage::storeSignature($this->upload($img, 'sign.jpg', 'jpg'));
        $out = $this->stored($path);

        $this->assertLessThanOrEqual(600, imagesx($out));
        $corner = imagecolorsforindex($out, imagecolorat($out, 0, 0));
        $this->assertSame(127, $corner['alpha'], 'paper is transparent');
        $cx = (int) (imagesx($out) / 2);
        $hasInk = false;
        for ($y = 0; $y < imagesy($out); $y++) {
            $c = imagecolorsforindex($out, imagecolorat($out, $cx, $y));
            if ($c['alpha'] < 60 && $c['blue'] > $c['red']) {
                $hasInk = true;
                break;
            }
        }
        $this->assertTrue($hasInk, 'the ink is kept');
    }

    public function test_a_transparent_logo_is_trimmed_and_stays_transparent(): void
    {
        $img = imagecreatetruecolor(600, 600);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledellipse($img, 300, 300, 200, 200, imagecolorallocate($img, 230, 120, 30));

        $out = $this->stored(BrandImage::storeLogo($this->upload($img, 'mark.png')));

        $this->assertLessThan(230, imagesx($out));
        $this->assertSame(127, imagecolorsforindex($out, imagecolorat($out, 0, 0))['alpha']);
    }

    public function test_a_blank_or_unreadable_file_is_still_stored(): void
    {
        $blank = imagecreatetruecolor(200, 100);
        imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));
        $out = $this->stored(BrandImage::storeLogo($this->upload($blank, 'blank.png')));
        $this->assertSame(200, imagesx($out), 'nothing to trim, left as it is');

        $junk = tempnam(sys_get_temp_dir(), 'brand') . '.png';
        file_put_contents($junk, 'not an image');
        $path = BrandImage::storeLogo(new UploadedFile($junk, 'broken.png', null, null, true));
        Storage::disk('public')->assertExists($path);
    }

    public function test_fit_keeps_the_shape_inside_the_box(): void
    {
        Storage::fake('public');
        $dir = public_path('storage/logos');
        @mkdir($dir, 0777, true);
        $file = $dir . '/fit-test-' . uniqid() . '.png';
        imagepng(imagecreatetruecolor(800, 200), $file); // 4:1 wordmark
        try {
            $fit = BrandImage::fit('logos/' . basename($file), 200, 56);
            $this->assertSame([200, 50], [$fit['w'], $fit['h']]);
            $fit = BrandImage::fit('logos/' . basename($file), 64, 64);
            $this->assertSame([64, 16], [$fit['w'], $fit['h']]);
            $this->assertNull(BrandImage::fit('logos/missing.png', 64, 64));
        } finally {
            @unlink($file);
        }
    }
}
