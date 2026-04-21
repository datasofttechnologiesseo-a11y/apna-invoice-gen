<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fromName,
        public string $fromEmail,
        public string $subjectLine,
        public string $messageBody,
        public ?string $phone = null,
    ) {}

    public function envelope(): Envelope
    {
        // Sent from the platform's verified address; user's email goes in
        // Reply-To so hitting "reply" in the inbox reaches the sender.
        $platformFrom = new Address(config('mail.from.address'), config('mail.from.name'));

        return new Envelope(
            from: $platformFrom,
            replyTo: [new Address($this->fromEmail, $this->fromName)],
            subject: '[Contact] ' . $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: [
                'fromName' => $this->fromName,
                'fromEmail' => $this->fromEmail,
                'phone' => $this->phone,
                'subjectLine' => $this->subjectLine,
                'messageBody' => $this->messageBody,
            ],
        );
    }
}
