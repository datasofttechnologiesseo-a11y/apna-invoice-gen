<?php

namespace App\Services\Reminders;

use App\Mail\PaymentReminderMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailReminderChannel implements ReminderChannel
{
    public function isAvailable(): bool
    {
        return (bool) config('reminders.channels.email.enabled', true);
    }

    public function recipientFor(Invoice $invoice): ?string
    {
        $email = $invoice->customer?->email;
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function send(Invoice $invoice, int $daysPastDue): bool
    {
        $email = $this->recipientFor($invoice);
        if (! $email) {
            return false;
        }

        try {
            Mail::to($email)->send(new PaymentReminderMail($invoice, $daysPastDue));
            return true;
        } catch (\Throwable $e) {
            Log::error('Payment reminder email failed', [
                'invoice_id' => $invoice->id,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
