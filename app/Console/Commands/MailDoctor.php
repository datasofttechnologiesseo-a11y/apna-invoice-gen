<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Answer "why did no email arrive?" without guessing.
 *
 * This exists because of a password-reset report. The reset flow itself was
 * fine - the token was issued, the link was well formed, the page said "we
 * have emailed your reset link" - and no email ever arrived, because
 * MAIL_MAILER was `log`. Every message had been written to
 * storage/logs/laravel.log for weeks and the screen said "sent" each time.
 *
 * That failure is invisible from the browser by design: the app cannot tell a
 * mail that was delivered from one that was written to a file, and it must not
 * leak its own configuration to a signed-out visitor. So the check lives here,
 * on the server, where whoever deployed it can run it.
 *
 * Two things are checked, because they are the two that silently break links
 * in email: where mail goes, and what host the links in it point at.
 */
class MailDoctor extends Command
{
    protected $signature = 'mail:check
        {--to= : also send a real test message to this address}';

    protected $description = 'Report where outbound mail actually goes, and whether emailed links will work';

    /** Mailers that accept a message and deliver it nowhere a person can read. */
    private const NON_DELIVERING = ['log', 'array', 'null'];

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $appUrl = (string) config('app.url');
        $from = (string) config('mail.from.address');
        $queue = (string) config('queue.default');

        $this->line('');
        $this->line('  <options=bold>Outbound mail</>');
        $this->table(['Setting', 'Value'], [
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', (string) config("mail.mailers.{$mailer}.host", '—')],
            ['MAIL_PORT', (string) config("mail.mailers.{$mailer}.port", '—')],
            ['MAIL_FROM_ADDRESS', $from],
            ['APP_URL', $appUrl],
            ['QUEUE_CONNECTION', $queue],
            ['Environment', app()->environment()],
        ]);

        $problems = [];

        if (in_array($mailer, self::NON_DELIVERING, true)) {
            $problems[] = $mailer === 'log'
                ? 'MAIL_MAILER is "log": nothing is sent. Every message, including password-reset links, is written to storage/logs/laravel.log. Set MAIL_MAILER=smtp with real credentials.'
                : "MAIL_MAILER is \"{$mailer}\": messages are accepted and discarded. Set MAIL_MAILER=smtp with real credentials.";
        }

        // A reset link is built from APP_URL. Point it at a development host
        // and the mail sends perfectly and the link is useless to the reader.
        if (Str::contains($appUrl, ['localhost', '127.0.0.1', '::1'])) {
            $problems[] = "APP_URL is \"{$appUrl}\": links inside emails will point at this machine and will not open for anyone else. Set APP_URL to the public https:// address.";
        } elseif (app()->isProduction() && Str::startsWith($appUrl, 'http://')) {
            $problems[] = "APP_URL is \"{$appUrl}\": emailed links will be plain http in production. Use https://.";
        }

        if (Str::endsWith($from, ['@example.com', '@example.org'])) {
            $problems[] = "MAIL_FROM_ADDRESS is \"{$from}\", a placeholder domain. Most providers reject or spam-file it. Use an address verified with your mail provider.";
        }

        foreach ($problems as $problem) {
            $this->warn('  ! ' . $problem);
        }

        if ($problems === []) {
            $this->info('  Configuration looks deliverable.');
        }

        $to = $this->option('to');
        if ($to) {
            if (in_array($mailer, self::NON_DELIVERING, true)) {
                $this->warn("  Sending the test anyway, but with MAIL_MAILER={$mailer} it will not reach {$to}.");
            }

            $this->line('');
            $this->line("  Sending a test message to {$to} …");

            try {
                Mail::raw(
                    "This is a test from apna-invoice (mail:check).\n\n"
                    . "If you are reading it in an inbox, outbound mail works and password-reset\n"
                    . "links will arrive. Emailed links are built from APP_URL, currently {$appUrl}.",
                    fn ($message) => $message->to($to)->subject('Apna Invoice — mail delivery test')
                );
                $this->info('  Handed to the mailer without error.');
            } catch (\Throwable $e) {
                // The exception text is the whole point here - an SMTP refusal
                // says which credential or host is wrong.
                $this->error('  The mailer refused it: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->line('');

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
