<?php

namespace App\Mail;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public string $customSubject,
        public string $customBody,
        public ?string $publicUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->quotation->company;

        // From must be the platform's verified address (SES/Mailgun reject anything
        // else as spoofing). Customer replies via Reply-To still land at the company.
        $platformFrom = new Address(config('mail.from.address'), config('mail.from.name', $company->name));
        $replyTo = $company->email
            ? new Address($company->email, $company->name)
            : $platformFrom;

        return new Envelope(
            from: $platformFrom,
            replyTo: [$replyTo],
            subject: $this->customSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation',
            with: [
                'quotation' => $this->quotation,
                'bodyText' => $this->customBody,
                'publicUrl' => $this->publicUrl,
            ],
        );
    }

    public function attachments(): array
    {
        $quotation = $this->quotation;
        $quotation->loadMissing(['items', 'customer.state', 'company.state']);

        $amountInWords = \App\Support\NumberToWords::indianRupees(
            (float) $quotation->grand_total,
            $quotation->currency
        );

        // Ink-saver attachment so the customer's first instinct (print + sign)
        // doesn't burn through their toner.
        $pdf = Pdf::loadView('quotations.pdf', [
                'quotation' => $quotation,
                'amountInWords' => $amountInWords,
                'print' => true,
            ])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = ($quotation->quote_number ?: 'quotation-draft-' . $quotation->id) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
