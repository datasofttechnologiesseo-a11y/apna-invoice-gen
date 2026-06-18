<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Generates and delivers mobile one-time passcodes for registration.
 *
 * Delivery is pluggable. The default 'log' driver writes the code to the log
 * (and, in non-production, returns it so the UI can show it) — this keeps the
 * whole flow testable without a live, DLT-registered SMS gateway. Wire a real
 * gateway by adding a case in send() and setting OTP_SMS_DRIVER.
 */
class OtpService
{
    /** Generate a zero-padded numeric code of the configured length. */
    public function generateCode(): string
    {
        $length = (int) config('otp.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Send the code to the phone.
     *
     * @return array{sent: bool, dev_code: ?string}
     */
    public function send(string $phone, string $code): array
    {
        $driver = config('otp.driver', 'log');

        $sent = match ($driver) {
            'log' => $this->sendViaLog($phone, $code),
            // 'msg91', 'twilio', … — implement once DLT-registered.
            default => $this->sendViaLog($phone, $code),
        };

        return [
            'sent' => $sent,
            'dev_code' => $this->shouldExposeCode() ? $code : null,
        ];
    }

    private function sendViaLog(string $phone, string $code): bool
    {
        Log::info("OTP for {$phone}: {$code} (valid " . config('otp.ttl_minutes', 10) . ' min)');

        return true;
    }

    private function shouldExposeCode(): bool
    {
        return (bool) config('otp.expose_in_dev', false) && ! app()->isProduction();
    }

    /** Normalise a raw 10-digit Indian mobile to +91XXXXXXXXXX. */
    public function normalizeIndianMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        // Drop a leading 91 or 0 if the user typed the full thing.
        $digits = preg_replace('/^(91|0)/', '', $digits);

        return '+91' . $digits;
    }

    /** Mask for display: +91 98xxx xx210. */
    public function mask(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $local = substr($digits, -10);
        if (strlen($local) !== 10) {
            return $phone;
        }

        return '+91 ' . substr($local, 0, 2) . 'xxx xx' . substr($local, -3);
    }
}
