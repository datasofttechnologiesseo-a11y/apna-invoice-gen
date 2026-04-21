<?php

namespace App\Services\Reminders;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ReminderService
{
    /** @var array<string, ReminderChannel> */
    private array $channels;

    public function __construct()
    {
        $this->channels = [
            'email'    => new EmailReminderChannel(),
            'whatsapp' => new WhatsAppReminderChannel(),
            'sms'      => new SmsReminderChannel(),
        ];
    }

    /**
     * Return days past due as a signed integer:
     *   positive  -> invoice is overdue (past the due date)
     *   zero      -> invoice is due today
     *   negative  -> invoice is not yet due (due date in future)
     *
     * We compute the difference with absolute=true and manually set the sign
     * so the result is stable across Carbon 2 vs Carbon 3 (which changed the
     * signed-diff convention in 2024).
     */
    public function daysPastDue(Invoice $invoice, ?CarbonInterface $asOf = null): int
    {
        $ref = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $due = ($invoice->due_date ?? $invoice->invoice_date)->copy()->startOfDay();

        $days = (int) $ref->diffInDays($due, true);
        if ($ref->equalTo($due)) {
            return 0;
        }
        return $ref->greaterThan($due) ? $days : -$days;
    }

    /**
     * True if this invoice is currently eligible to receive reminders
     * (has a balance, is issued, not cancelled).
     */
    public function isEligible(Invoice $invoice): bool
    {
        return $invoice->finalized_at !== null
            && ! $invoice->isCancelled()
            && (float) $invoice->balance > 0;
    }

    /**
     * Has this invoice already received an auto-reminder at this threshold
     * on this channel? (Prevents duplicates across scheduler runs.)
     */
    public function alreadySentAutomatic(Invoice $invoice, string $channel, int $threshold): bool
    {
        return InvoiceReminder::where('invoice_id', $invoice->id)
            ->where('channel', $channel)
            ->where('days_past_due', $threshold)
            ->where('trigger', 'auto')
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * Send a reminder through the named channel. Logs the attempt regardless
     * of success. Returns the InvoiceReminder row.
     */
    public function send(Invoice $invoice, string $channel, string $trigger = 'manual', ?int $thresholdOverride = null): InvoiceReminder
    {
        $ch = $this->channels[$channel] ?? null;
        if (! $ch) {
            return $this->log($invoice, $channel, '', 'failed', 0, $trigger, "Unknown channel {$channel}");
        }

        if (! $ch->isAvailable()) {
            return $this->log($invoice, $channel, '', 'failed', 0, $trigger, "Channel {$channel} not configured.");
        }

        $recipient = $ch->recipientFor($invoice);
        if (! $recipient) {
            return $this->log($invoice, $channel, '', 'failed', 0, $trigger, "No {$channel} recipient on customer.");
        }

        $days = $thresholdOverride ?? $this->daysPastDue($invoice);
        $ok = $ch->send($invoice, max(0, $days));

        return $this->log(
            $invoice,
            $channel,
            $recipient,
            $ok ? 'sent' : 'failed',
            $days,
            $trigger,
            $ok ? null : "Channel returned failure (see logs).",
        );
    }

    /**
     * Run automation for a single invoice — walks all configured thresholds
     * and sends if the invoice has hit a new threshold that hasn't been sent yet.
     *
     * Returns the list of newly-sent InvoiceReminder records.
     *
     * @return array<InvoiceReminder>
     */
    public function runAutomaticFor(Invoice $invoice): array
    {
        if (! config('reminders.enabled', true)) return [];
        if (! $this->isEligible($invoice)) return [];

        $days = $this->daysPastDue($invoice);
        $thresholds = collect(config('reminders.thresholds', []))
            ->filter(fn ($t) => $days >= (int) $t)
            ->sort()
            ->values();

        // Walk every channel that's enabled & available.
        $enabledChannels = collect(config('reminders.channels', []))
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->keys()
            ->filter(fn ($k) => isset($this->channels[$k]) && $this->channels[$k]->isAvailable());

        $sent = [];
        foreach ($thresholds as $threshold) {
            foreach ($enabledChannels as $channel) {
                if ($this->alreadySentAutomatic($invoice, $channel, (int) $threshold)) {
                    continue;
                }
                $sent[] = $this->send($invoice, $channel, 'auto', (int) $threshold);
            }
        }

        return $sent;
    }

    private function log(Invoice $invoice, string $channel, string $recipient, string $status, int $days, string $trigger, ?string $error = null): InvoiceReminder
    {
        return InvoiceReminder::create([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $status,
            'days_past_due' => $days,
            'trigger' => $trigger,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }
}
