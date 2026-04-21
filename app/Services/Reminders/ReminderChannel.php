<?php

namespace App\Services\Reminders;

use App\Models\Invoice;

interface ReminderChannel
{
    /** True if this channel is available to send (credentials / config ok). */
    public function isAvailable(): bool;

    /** Recipient identifier for this invoice/customer on this channel (or null). */
    public function recipientFor(Invoice $invoice): ?string;

    /**
     * Attempt to send a reminder. Returns true on success, false on failure.
     * Implementations should swallow and log their own exceptions.
     */
    public function send(Invoice $invoice, int $daysPastDue): bool;
}
