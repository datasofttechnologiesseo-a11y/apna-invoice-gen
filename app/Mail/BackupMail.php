<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param string $zipPath Absolute path to the backup ZIP (temp file). */
    public function __construct(
        public User $user,
        public string $zipPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name') . ' — Your weekly data backup',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup',
            with: [
                'user' => $this->user,
                'generatedAt' => now(),
            ],
        );
    }

    public function attachments(): array
    {
        $filename = 'apna-invoice-backup-' . now()->format('Y-m-d') . '.zip';

        return [
            Attachment::fromPath($this->zipPath)
                ->as($filename)
                ->withMime('application/zip'),
        ];
    }
}
