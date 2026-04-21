<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Reminders\ReminderService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'invoices:send-reminders
                            {--dry : Show what would be sent without actually sending}';

    protected $description = 'Send automatic payment-reminder emails for overdue invoices';

    public function handle(ReminderService $service): int
    {
        if (! config('reminders.enabled', true)) {
            $this->warn('Reminders are disabled (REMINDERS_ENABLED=false). Exiting.');
            return self::SUCCESS;
        }

        // Shortlist eligible invoices in one query. The scope on the Invoice
        // model is the single source of truth for what "eligible" means.
        $invoices = Invoice::eligibleForReminders()
            ->with(['customer', 'company'])
            ->get();

        $this->info("Evaluating {$invoices->count()} eligible invoice(s) for reminders…");

        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($invoices as $invoice) {
            if ($this->option('dry')) {
                $days = $service->daysPastDue($invoice);
                $label = $days > 0
                    ? "{$days} day(s) overdue"
                    : ($days === 0 ? 'due today' : abs($days) . ' day(s) until due');
                $this->line("  [dry] #{$invoice->invoice_number} — {$label}, balance ₹" . number_format((float) $invoice->balance, 2));
                continue;
            }

            $results = $service->runAutomaticFor($invoice);
            foreach ($results as $r) {
                if ($r->status === 'sent') $stats['sent']++;
                else $stats['failed']++;
            }
            if (empty($results)) $stats['skipped']++;
        }

        $this->info("Done. Sent: {$stats['sent']}, Skipped: {$stats['skipped']}, Failed: {$stats['failed']}");
        return self::SUCCESS;
    }
}
