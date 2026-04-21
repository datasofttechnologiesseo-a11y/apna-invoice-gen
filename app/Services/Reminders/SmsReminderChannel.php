<?php

namespace App\Services\Reminders;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * SMS reminder channel — STUB.
 *
 * In India, transactional SMS requires DLT (Distributed Ledger Technology)
 * registration of the entity, sender ID and each template with your telecom
 * operator (JIO/Airtel/VI/BSNL). Without DLT approval, the SMS will be blocked
 * or tagged as spam.
 *
 * Recommended gateways once DLT is approved:
 *   - MSG91 (api.msg91.com)
 *   - Twilio India (twilio.com)
 *   - Fast2SMS (fast2sms.com)
 *   - TextLocal
 *
 * To enable:
 * 1. Register entity + sender ID + templates with DLT via your telecom.
 * 2. Sign up with a gateway, get an API key.
 * 3. Set SMS_PROVIDER, SMS_API_KEY (and provider-specific env vars).
 * 4. Override `isAvailable()` and implement `send()`.
 */
class SmsReminderChannel implements ReminderChannel
{
    public function isAvailable(): bool
    {
        return (bool) config('reminders.channels.sms.enabled', false)
            && filled(env('SMS_API_KEY'));
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

        Log::info('SMS gateway send not implemented', [
            'invoice_id' => $invoice->id,
            'days_past_due' => $daysPastDue,
        ]);

        return false;
    }
}
