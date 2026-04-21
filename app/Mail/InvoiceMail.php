<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $customSubject,
        public string $customBody,
        public ?string $publicUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        // We always use the platform's verified From address (config('mail.from'))
        // because modern providers (SES, Mailgun, Google Workspace SMTP) reject
        // Sending a message with a From that the account hasn't verified — that's
        // spoofing from their perspective. The customer still sees the company's
        // email in Reply-To, so their reply lands in the right inbox.
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
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'bodyText' => $this->customBody,
                'publicUrl' => $this->publicUrl,
            ],
        );
    }

    public function attachments(): array
    {
        $invoice = $this->invoice;
        $invoice->loadMissing(['items', 'customer.state', 'company.state', 'placeOfSupply']);

        $amountInWords = \App\Support\NumberToWords::indianRupees(
            (float) $invoice->grand_total,
            $invoice->currency
        );
        $style = $invoice->style ?: 'classic';
        $print = true; // ink-saver version for email

        $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'amountInWords' => $amountInWords,
                'style' => $style,
                'print' => $print,
            ])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = 'invoice-' . ($invoice->invoice_number ?: 'draft-' . $invoice->id) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
