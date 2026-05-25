<?php

namespace App\Services\Reminders;

use App\Http\Controllers\InvoiceShareController;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp reminder channel — STUB.
 *
 * Production-grade automated WhatsApp delivery requires a registered WhatsApp
 * Business API provider (BSP) like Twilio, Meta Cloud API, Gupshup, or Interakt,
 * a verified business phone number, and approved template messages.
 *
 * Until a BSP is wired up, this channel is "not available" for automatic sends.
 * Users can still share invoices and reminders via the `wa.me` deep links on
 * the invoice page — that's manual-click, free, and does not require a BSP.
 *
 * To enable automation:
 * 1. Sign up with a BSP, verify your business, get API credentials.
 * 2. Add WA_BSP=<provider> plus credential env vars.
 * 3. Override `isAvailable()` to check for your credentials.
 * 4. Implement the HTTP call in `send()`.
 */
class WhatsAppReminderChannel implements ReminderChannel
{
    public function isAvailable(): bool
    {
        // Automated WhatsApp is off until credentials are configured.
        // Manual wa.me links on the invoice page still work.
        return (bool) config('reminders.channels.whatsapp.enabled', false)
            && filled(env('WA_BSP_TOKEN'));
    }

    public function recipientFor(Invoice $invoice): ?string
    {
        $phone = $invoice->customer?->phone;
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits) >= 10 ? $digits : null;
    }

    public function send(Invoice $invoice, int $daysPastDue): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $phone = $this->recipientFor($invoice);
        if (! $phone) {
            return false;
        }

        // TODO: wire to BSP of choice. Template message with vars:
        //   { customer_name, invoice_number, balance, public_url }
        Log::info('WhatsApp BSP send not implemented', [
            'invoice_id' => $invoice->id,
            'phone' => $phone,
            'url' => InvoiceShareController::makePublicUrl($invoice),
            'days_past_due' => $daysPastDue,
        ]);

        return false;
    }

    /**
     * Build a wa.me deep link with the reminder text — used by the UI for
     * manual click-to-send (100% free, no BSP needed).
     *
     * Delegates to Invoice::whatsAppShareLink('reminder') so the wording stays
     * in lock-step with the share panel and the email reminder template. The
     * 'reminder' context biases the copy toward "is N days overdue" framing.
     */
    public static function waMeLink(Invoice $invoice, int $daysPastDue = 0): ?string
    {
        // Re-read from DB so a payment recorded a second ago is reflected in
        // the balance — defense in depth against the race between "user clicks
        // remind" and "another user records payment" on the same invoice.
        $invoice->refresh();
        return $invoice->whatsAppShareLink('reminder');
    }
}
