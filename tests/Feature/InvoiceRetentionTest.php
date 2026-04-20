<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_user_with_invoices_is_refused_at_db_level(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->recycle($user)->create();

        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_deleting_company_with_invoices_is_refused_at_db_level(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->create();

        $this->expectException(QueryException::class);
        $invoice->company->delete();
    }

    public function test_user_without_invoices_can_still_be_deleted(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
