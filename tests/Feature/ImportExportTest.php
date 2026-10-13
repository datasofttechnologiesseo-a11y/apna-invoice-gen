<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Excel / CSV import and the Excel / CSV / PDF / Print export every list
 * carries. Imports only ever ADD: a row matching an existing record is
 * skipped, never merged into it.
 */
class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;
    private State $ka;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka', 'code' => 'KA']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create(['state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z0']);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        foreach ($rows as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->close();

        return new UploadedFile($path, 'customers.xlsx', null, null, true);
    }

    private function csv(string $content, string $name = 'data.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    public function test_the_template_downloads_as_a_real_xlsx(): void
    {
        $res = $this->actingAs($this->user)->get(route('imports.template', 'customers'));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $res->streamedContent(), 'xlsx files are zip archives');
    }

    public function test_customers_import_from_xlsx_with_preview_and_confirm(): void
    {
        Customer::factory()->recycle($this->user)->recycle($this->company)->create([
            'name' => 'Existing Co', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->ka->id, 'email' => 'old@existing.in',
        ]);

        $file = $this->xlsx([
            ['Customer Sheet - exported from my old tool'],           // title line above the table is skipped
            ['Customer Name', 'GST No', 'State', 'Mobile', 'Email', 'Pincode'],
            ['Patel Stores', '', 'Maharashtra', '9876543210', 'patel@stores.in', '400001'],
            ['Kumar & Sons', '29AAACB2894G1ZJ', '', '9845012345', '', ''],      // state from GSTIN
            ['Existing Co Renamed', '29ABCDE1234F1ZW', 'KA', '', 'new@existing.in', ''], // duplicate by GSTIN
            ['', '', 'Goa', '', '', ''],                                        // error: no name
            ['Wrong State Ltd', '29AAACB2894G1ZJ', 'Maharashtra', '', '', ''],  // error: GSTIN/state clash
            ['Patel Stores', '', '27', '9876543210', '', ''],                   // duplicate of row 3 in file
        ]);

        $preview = $this->actingAs($this->user)->post(route('imports.preview', 'customers'), ['file' => $file]);
        $preview->assertOk()
            ->assertSee('Ready to import')
            ->assertSee('Already a customer with GSTIN 29ABCDE1234F1ZW', false)
            ->assertSee('Customer Name is required.')
            ->assertSee('belongs to Karnataka')
            ->assertSee('Same as row 3 in this file');
        $this->assertSame(1, Customer::count(), 'nothing is saved at the preview step');

        $token = $preview->viewData('token');
        $this->actingAs($this->user)->post(route('imports.confirm', 'customers'), ['token' => $token])
            ->assertRedirect(route('customers.index'));

        $this->assertSame(3, Customer::count());
        $kumar = Customer::where('name', 'Kumar & Sons')->firstOrFail();
        $this->assertSame($this->ka->id, $kumar->state_id, 'state filled in from the GSTIN');
        $this->assertSame($this->company->id, $kumar->company_id);
        $this->assertSame($this->user->id, $kumar->user_id);
        $this->assertSame('old@existing.in', Customer::where('gstin', '29ABCDE1234F1ZW')->value('email'),
            'an existing customer is never overwritten by an import');
    }

    public function test_a_preview_cannot_be_confirmed_twice_or_by_someone_else(): void
    {
        $file = $this->csv("Name,State\nOne Co,Maharashtra\n");
        $token = $this->actingAs($this->user)->post(route('imports.preview', 'customers'), ['file' => $file])->viewData('token');

        $stranger = User::factory()->create();
        Company::factory()->recycle($stranger)->create(['state_id' => $this->mh->id]);
        $stranger->forceFill(['active_company_id' => $stranger->companies()->first()->id])->save();
        $this->actingAs($stranger)->post(route('imports.confirm', 'customers'), ['token' => $token])
            ->assertRedirect(route('imports.show', 'customers'));
        $this->assertSame(0, Customer::count());

        $this->actingAs($this->user)->post(route('imports.confirm', 'customers'), ['token' => $token]);
        $this->actingAs($this->user)->post(route('imports.confirm', 'customers'), ['token' => $token]);
        $this->assertSame(1, Customer::count(), 'confirming twice does not import twice');
    }

    public function test_products_import_from_a_semicolon_csv_with_rates_and_units_checked(): void
    {
        Product::create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'Old Item', 'sku' => 'OLD-1', 'kind' => 'goods', 'hsn_sac' => '84145910', 'unit' => 'NOS', 'rate' => 10, 'gst_rate' => 18, 'is_active' => true]);

        $csv = "Item Name;Item Code;HSN/SAC;Unit;Sale Price;GST %\n"
            . "Cooling Fan;FAN-120;84145910;NOS;\"1,250.50\";18\n"
            . "Website Design;;998314;;25000;18%\n"     // SAC 99… → service, blank unit
            . "Bad Rate;;84145910;NOS;100;13\n"           // 13% is not a GST rate
            . "Old Item Copy;OLD-1;84145910;NOS;20;18\n"; // SKU already used
        $preview = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $this->csv($csv)]);
        $preview->assertOk()->assertSee('is not a GST rate')->assertSee('Item Code OLD-1 already used');

        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $preview->viewData('token')]);

        $fan = Product::where('sku', 'FAN-120')->firstOrFail();
        $this->assertEqualsWithDelta(1250.50, (float) $fan->rate, 0.001);
        $this->assertSame('goods', $fan->kind);
        $web = Product::where('name', 'Website Design')->firstOrFail();
        $this->assertSame('service', $web->kind);
        $this->assertEqualsWithDelta(10.0, (float) Product::where('sku', 'OLD-1')->value('rate'), 0.001, 'existing item untouched');
    }

    public function test_items_can_be_imported_with_opening_stock_and_a_low_stock_alert(): void
    {
        $csv = "Item Name,HSN/SAC,Sale Price,GST %,Purchase Price,Opening Stock,Low Stock Alert,Description\n"
            . "Steel Rack,94032090,4500,18,3600,40,5,Powder coated\n"
            . "Plain Item,94032090,100,18,,,,\n"           // no stock columns: not tracked
            . "Repair Visit,998719,500,18,,3,,\n"          // a service: quantity ignored
            . "Bad Stock,94032090,100,18,,-2,,\n";        // negative stock refused
        $preview = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $this->csv($csv)]);
        $preview->assertOk()->assertSee('Opening Stock cannot be negative')
            // Every column read from the file is shown, not only the first six.
            ->assertSee('Purchase Price</th>', false)->assertSee('Opening Stock</th>', false)
            ->assertSee('Low Stock Alert</th>', false)->assertSee('3600');
        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $preview->viewData('token')]);

        $rack = Product::where('name', 'Steel Rack')->firstOrFail();
        $this->assertTrue($rack->track_stock);
        $this->assertEqualsWithDelta(40, $rack->stockOnHand(), 0.001);
        $this->assertEqualsWithDelta(5, (float) $rack->low_stock_level, 0.001);
        $this->assertEqualsWithDelta(3600, (float) $rack->purchase_rate, 0.001);
        $this->assertSame('Powder coated', $rack->description, "the template's own Description column is the description, not the name");
        $this->assertFalse(Product::where('name', 'Plain Item')->firstOrFail()->track_stock);
        $this->assertFalse(Product::where('name', 'Repair Visit')->firstOrFail()->track_stock);
        $this->assertFalse(Product::where('name', 'Bad Stock')->exists());
    }

    public function test_vendors_import(): void
    {
        $preview = $this->actingAs($this->user)->post(route('imports.preview', 'vendors'), [
            'file' => $this->csv("Vendor Name,GSTIN\nDeepcool India,27AAPFU0939F1ZV\n"),
        ]);
        $this->actingAs($this->user)->post(route('imports.confirm', 'vendors'), ['token' => $preview->viewData('token')]);

        $v = Vendor::firstOrFail();
        $this->assertSame($this->mh->id, $v->state_id);
        $this->assertSame($this->company->id, $v->company_id);
    }

    public function test_a_file_without_the_required_columns_is_explained(): void
    {
        $this->actingAs($this->user)->post(route('imports.preview', 'products'), [
            'file' => $this->csv("Name,Colour\nWidget,Blue\n"),
        ])->assertSessionHasErrors('file');
    }

    public function test_every_report_exports_in_every_format(): void
    {
        foreach (array_keys(config('reports.registry')) as $report) {
            foreach (['xlsx', 'csv', 'pdf', 'print'] as $format) {
                $this->actingAs($this->user)
                    ->get(route('reports.export', ['report' => $report, 'format' => $format]))
                    ->assertOk();
            }
        }
        $this->actingAs($this->user)->get('/reports/nope/xlsx')->assertNotFound();
    }

    public function test_exports_only_contain_the_active_companys_records(): void
    {
        Customer::factory()->recycle($this->user)->recycle($this->company)->create(['name' => 'Mine Pvt Ltd', 'state_id' => $this->mh->id]);
        $other = User::factory()->create();
        $otherCo = Company::factory()->recycle($other)->create(['state_id' => $this->mh->id]);
        Customer::factory()->recycle($other)->recycle($otherCo)->create(['name' => 'Someone Elses Co', 'state_id' => $this->mh->id]);

        $this->actingAs($this->user)->get(route('reports.export', ['report' => 'customers', 'format' => 'print']))
            ->assertOk()->assertSee('Mine Pvt Ltd')->assertDontSee('Someone Elses Co');
    }

    public function test_the_export_carries_the_screens_search_filter(): void
    {
        Customer::factory()->recycle($this->user)->recycle($this->company)->create(['name' => 'Alpha Traders', 'state_id' => $this->mh->id]);
        Customer::factory()->recycle($this->user)->recycle($this->company)->create(['name' => 'Beta Stores', 'state_id' => $this->mh->id]);

        $csv = $this->actingAs($this->user)->get(route('reports.export', ['report' => 'customers', 'format' => 'csv', 'search' => 'Alpha']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Alpha Traders', $csv);
        $this->assertStringNotContainsString('Beta Stores', $csv);
        $this->assertStringContainsString('Search: ""Alpha""', $csv, 'the filter is printed on the export (CSV doubles quotes)');
    }
}
