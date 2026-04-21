<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Indian financial year runs 1 April → 31 March. These tests lock in
 * the boundary behaviour so a future refactor can't silently misclassify
 * an invoice dated March 31 as belonging to the next FY.
 */
class CompanyFinancialYearTest extends TestCase
{
    public function test_april_1_is_new_fy(): void
    {
        [$start, $end] = Company::financialYearFor(Carbon::parse('2025-04-01'));
        $this->assertSame(2025, $start);
        $this->assertSame(2026, $end);
    }

    public function test_march_31_is_previous_fy(): void
    {
        [$start, $end] = Company::financialYearFor(Carbon::parse('2026-03-31'));
        $this->assertSame(2025, $start);
        $this->assertSame(2026, $end);
    }

    public function test_mid_year_dates(): void
    {
        [$start] = Company::financialYearFor(Carbon::parse('2025-06-15'));
        $this->assertSame(2025, $start);
        [$start] = Company::financialYearFor(Carbon::parse('2026-01-15'));
        $this->assertSame(2025, $start);
    }

    public function test_legacy_format_when_no_template(): void
    {
        $company = new Company([
            'invoice_prefix' => 'ACME',
            'invoice_counter' => 41,
            'invoice_number_padding' => 4,
        ]);
        $this->assertSame('ACME-0042', $company->nextInvoiceNumber());
    }

    public function test_fy_template_resolves(): void
    {
        $company = new Company([
            'invoice_number_format' => 'INV/{FY}/{N}',
            'invoice_counter' => 0,
            'invoice_counter_fy' => 2025,
            'invoice_number_padding' => 4,
        ]);
        $this->assertSame('INV/2025-26/0001', $company->nextInvoiceNumber('2025-06-15'));
    }

    public function test_fy_short_template(): void
    {
        $company = new Company([
            'invoice_number_format' => 'ACME-{FY_SHORT}/{N}',
            'invoice_counter' => 99,
            'invoice_counter_fy' => 2025,
            'invoice_number_padding' => 4,
        ]);
        $this->assertSame('ACME-25-26/0100', $company->nextInvoiceNumber('2025-06-15'));
    }

    public function test_fy_rollover_preview(): void
    {
        // Counter is from FY 2024-25 (stored fy start = 2024). If we preview
        // an invoice dated 2025-04-02 (FY 2025-26), the preview should
        // reset to #0001 — not continue from #0042.
        $company = new Company([
            'invoice_number_format' => 'INV/{FY}/{N}',
            'invoice_counter' => 41,
            'invoice_counter_fy' => 2024,
            'invoice_number_padding' => 4,
        ]);
        $this->assertSame('INV/2025-26/0001', $company->nextInvoiceNumber('2025-04-02'));
    }
}
