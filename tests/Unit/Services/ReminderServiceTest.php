<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Services\Reminders\ReminderService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lock in the sign convention of ReminderService::daysPastDue —
 * this has bitten us before when Carbon changed diffInDays semantics.
 */
class ReminderServiceTest extends TestCase
{
    private ReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReminderService();
    }

    private function invoiceWithDueDate(?string $dueDate, string $invoiceDate = '2026-01-01'): Invoice
    {
        // Construct without hitting the DB — we only need the cast attributes.
        $inv = new Invoice();
        $inv->forceFill([
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
        ]);
        return $inv;
    }

    public function test_due_today_returns_zero(): void
    {
        $inv = $this->invoiceWithDueDate('2026-04-15');
        $days = $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 10:00'));
        $this->assertSame(0, $days);
    }

    public function test_overdue_returns_positive(): void
    {
        $inv = $this->invoiceWithDueDate('2026-04-10');
        $days = $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 10:00'));
        $this->assertSame(5, $days);
    }

    public function test_not_yet_due_returns_negative(): void
    {
        $inv = $this->invoiceWithDueDate('2026-04-20');
        $days = $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 10:00'));
        $this->assertSame(-5, $days);
    }

    public function test_far_overdue(): void
    {
        $inv = $this->invoiceWithDueDate('2026-01-01');
        $days = $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 10:00'));
        $this->assertSame(104, $days);
    }

    public function test_falls_back_to_invoice_date_when_no_due_date(): void
    {
        $inv = $this->invoiceWithDueDate(null, '2026-04-10');
        $days = $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 10:00'));
        $this->assertSame(5, $days);
    }

    public function test_time_of_day_ignored(): void
    {
        // Both 00:01 and 23:59 on the due date count as "due today".
        $inv = $this->invoiceWithDueDate('2026-04-15');
        $this->assertSame(0, $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 00:01')));
        $this->assertSame(0, $this->service->daysPastDue($inv, Carbon::parse('2026-04-15 23:59')));
    }
}
