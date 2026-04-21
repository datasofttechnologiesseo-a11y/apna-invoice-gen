<?php

namespace App\Mail;

use App\Http\Controllers\InvoiceShareController;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $daysPastDue,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        // Sender = platform's verified From (so SES/Mailgun/Gmail accept it).
        // Reply-To = company's own email so the customer's reply goes home.
        $platformFrom = new Address(config('mail.from.address'), config('mail.from.name', $company->name));
        $replyTo = $company->email
            ? new Address($company->email, $company->name)
            : $platformFrom;

        $subject = $this->daysPastDue <= 0
            ? 'Friendly reminder — invoice ' . $this->invoice->invoice_number . ' is due'
            : 'Payment reminder — invoice ' . $this->invoice->invoice_number . ' is ' . $this->daysPastDue . ' days overdue';

        return new Envelope(from: $platformFrom, replyTo: [$replyTo], subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'invoice'  => $this->invoice,
                'daysPastDue' => $this->daysPastDue,
                'publicUrl' => InvoiceShareController::makePublicUrl($this->invoice),
            ],
        );
    }

    public function attachments(): array
    {
        $invoice = $this->invoice;
        $invoice->loadMissing(['items', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);

        $amountInWords = \App\Support\NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'amountInWords' => $amountInWords,
                'style' => $invoice->style ?: 'classic',
                'print' => true,
            ])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                'invoice-' . ($invoice->invoice_number ?: 'draft-' . $invoice->id) . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
