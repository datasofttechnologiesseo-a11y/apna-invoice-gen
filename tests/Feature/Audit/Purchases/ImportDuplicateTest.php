<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\Company;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Audit repro: an import is "add-only, duplicates skipped". The check
 * against existing records treats the same name as a duplicate (vendor by
 * GSTIN or name, item by code or name), but the check within one file keys
 * on GSTIN-else-name / code-else-name only, so the same vendor or item twice
 * in one sheet is created twice - which the same sheet uploaded again would
 * refuse.
 */
class ImportDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create(['state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now()]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'data.csv', 'text/csv', null, true);
    }

    public function test_the_same_vendor_twice_in_one_sheet_is_imported_once(): void
    {
        $csv = "Vendor Name,GSTIN,State\nDeepcool India,27AAPFU0939F1ZV,\nDeepcool India,,Maharashtra\n";
        $token = $this->actingAs($this->user)->post(route('imports.preview', 'vendors'), ['file' => $this->csv($csv)])->assertOk()->viewData('token');
        $this->actingAs($this->user)->post(route('imports.confirm', 'vendors'), ['token' => $token]);

        $this->assertSame(1, $this->company->vendors()->where('name', 'Deepcool India')->count(),
            'Two vendors named "Deepcool India" created from one sheet; the second would be skipped as "Already a vendor named" on any later import');
    }

    public function test_the_same_item_name_twice_in_one_sheet_is_imported_once(): void
    {
        $csv = "Item Name,Item Code,HSN/SAC,Sale Price,GST %,Opening Stock\nCooling fan,FAN-1,84145910,500,18,10\nCooling fan,FAN-01,84145910,500,18,10\n";
        $token = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $this->csv($csv)])->assertOk()->viewData('token');
        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $token]);

        $this->assertSame(1, Product::where('name', 'Cooling fan')->count(),
            'Two items named "Cooling fan" (each with 10 opening stock) created from one sheet');
    }
}
