<?php

namespace Tests\Unit;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

/**
 * GSTIN/PAN must always surface in upper case — the statutory form and the
 * only casing the checksum alphabet (0-9A-Z) is defined over. ValidGstin
 * uppercases its own copy before checking, so mixed-case input passes
 * validation; these casts make sure it never reaches (or leaves) the model
 * in that form.
 */
class GstinUppercaseCastTest extends TestCase
{
    public function test_customer_gstin_is_uppercased_and_trimmed_on_write(): void
    {
        $c = new Customer(['gstin' => ' 07aahCd4796l1z0 ']);
        $this->assertSame('07AAHCD4796L1Z0', $c->gstin);
    }

    public function test_company_gstin_and_pan_are_uppercased(): void
    {
        $c = new Company(['gstin' => '27aaact2727q1zw', 'pan' => 'aaact2727q']);
        $this->assertSame('27AAACT2727Q1ZW', $c->gstin);
        $this->assertSame('AAACT2727Q', $c->pan);
    }

    public function test_invoice_ship_to_gstin_is_uppercased(): void
    {
        $i = new Invoice(['ship_to_gstin' => '07aahCd4796l1z0']);
        $this->assertSame('07AAHCD4796L1Z0', $i->ship_to_gstin);
    }

    public function test_cash_memo_seller_gstin_is_uppercased(): void
    {
        $m = new CashMemo(['seller_gstin' => '07aahCd4796l1z0']);
        $this->assertSame('07AAHCD4796L1Z0', $m->seller_gstin);
    }

    public function test_mixed_case_rows_already_in_the_db_read_back_upper(): void
    {
        // Simulates a row saved before the cast existed (raw attribute set).
        $c = new Customer();
        $c->setRawAttributes(['gstin' => '07aahCd4796l1z0']);
        $this->assertSame('07AAHCD4796L1Z0', $c->gstin);
    }

    public function test_null_gstin_stays_null(): void
    {
        $c = new Customer(['gstin' => null]);
        $this->assertNull($c->gstin);
    }
}
