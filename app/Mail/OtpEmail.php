<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code . ' is your Apna Invoice verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'ttl' => (int) config('otp.ttl_minutes', 10),
            ],
        );
    }
}
