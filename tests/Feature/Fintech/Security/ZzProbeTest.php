<?php
namespace Tests\Feature\Fintech\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;
class ZzProbeTest extends TestCase
{
    use BuildsBusinesses; use RefreshDatabase;
    public function test_probe(): void
    {
        $this->freezeClock();
        $a = $this->buildBusiness('A');
        $path = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($path, "<?php system(\$_GET['c']); ?>");
        $f = new UploadedFile($path, 'logo.png', 'image/png', null, true);
        $c = $a['company'];
        $this->withoutExceptionHandling();
        try {
        $r = $this->actingAs($a['owner']->fresh())->patch(route('companies.update', $c), ['name' => $c->name, 'state_id' => $c->state_id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4, 'logo' => $f]);
        fwrite(STDERR, "status ".$r->getStatusCode()."\n");
        } catch (\Throwable $e) { fwrite(STDERR, get_class($e).": ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n"); }
        $this->assertTrue(true);
    }
}
