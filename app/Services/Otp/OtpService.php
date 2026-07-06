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
     * Send the code. Falls back to email when no real SMS gateway is wired up
     * in production, so sign-ups keep working before DLT/SMS is configured.
     *
     * @return array{sent: bool, dev_code: ?string, channel: string}
     */
    public function send(?string $phone, string $code, ?string $email = null): array
    {
        $driver = config('otp.driver', 'log');
        $channel = 'sms';

        // No phone to text — email is the only possible channel.
        if (blank($phone) && filled($email)) {
            $sent = $this->sendViaEmail($email, $code);

            return [
                'sent' => $sent,
                'dev_code' => $this->shouldExposeCode() ? $code : null,
                'channel' => 'email',
            ];
        }

        if ($driver === 'email') {
            $sent = $this->sendViaEmail($email, $code);
            $channel = 'email';
        } elseif ($driver === 'log') {
            // No real SMS gateway. In production, email the code so the user
            // actually receives it; in dev, log it (and we expose it in the UI).
            if (app()->isProduction() && filled($email) && config('otp.email_fallback', true)) {
                $sent = $this->sendViaEmail($email, $code);
                $channel = 'email';
            } else {
                $sent = $this->sendViaLog((string) $phone, $code);
                $channel = 'log';
            }
        } else {
            // A real gateway ('msg91', 'twilio', …) — implement once DLT-registered.
            $sent = $this->sendViaLog($phone, $code);
            $channel = 'log';
        }

        return [
            'sent' => $sent,
            'dev_code' => $this->shouldExposeCode() ? $code : null,
            'channel' => $channel,
        ];
    }

    private function sendViaLog(string $phone, string $code): bool
    {
        Log::info("OTP for {$phone}: {$code} (valid " . config('otp.ttl_minutes', 10) . ' min)');

        return true;
    }

    private function sendViaEmail(?string $email, string $code): bool
    {
        if (! filled($email)) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\OtpEmail($code));

            return true;
        } catch (\Throwable $e) {
            Log::warning('OTP email delivery failed', ['error' => $e->getMessage()]);

            return false;
        }
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
