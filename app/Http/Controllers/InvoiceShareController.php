<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class InvoiceShareController extends Controller
{
    /** How long a public share link stays valid. */
    private const SHARE_TTL_DAYS = 30;

    /**
     * Email the invoice to the customer (PDF attached).
     */
    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwner($request, $invoice);
        abort_if($invoice->isDraft(), 422, 'Finalize the invoice before emailing it.');

        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ccEmails = $this->parseEmails($data['cc'] ?? '');

        try {
            $publicUrl = $this->publicUrl($invoice);

            $mail = Mail::to($data['to']);
            if (! empty($ccEmails)) {
                $mail->cc($ccEmails);
            }
            $mail->send(new InvoiceMail($invoice, $data['subject'], $data['body'], $publicUrl));
        } catch (\Throwable $e) {
            // Log the real exception (with stack) for debugging, but surface a
            // generic message — detailed SMTP/provider errors leak infra hints.
            Log::error('Invoice email failed', ['invoice_id' => $invoice->id, 'exception' => $e]);
            return back()->withErrors(['email' => "We couldn't send the email right now. Please try again in a moment or contact support if the problem persists."]);
        }

        return back()->with('status', "Invoice emailed to {$data['to']}.");
    }

    /**
     * Cancel a finalized invoice. Preserves the record (GST audit trail)
     * but blocks further payments.
     */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwner($request, $invoice);
        abort_unless($invoice->canBeCancelled(), 422, 'This invoice cannot be cancelled.');

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'],
            ]);
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Invoice cancelled. A record is kept for GST audit.');
    }

    /**
     * Public signed view — accessible without login by whoever has the link.
     * Handy for WhatsApp / email sharing: customer opens PDF directly.
     */
    public function publicView(Request $request, Invoice $invoice): Response
    {
        // Signed URL middleware handles the signature check (applied in routes).
        // Cancelled invoices must NOT be downloadable through the public link —
        // otherwise a customer could still open an old WhatsApp link and treat
        // the cancelled bill as payable.
        abort_if($invoice->isCancelled(), 410, 'This invoice has been cancelled and is no longer available.');

        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);
        $style = $invoice->style ?: 'classic';
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style', 'print'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = 'invoice-' . $invoice->filenameSafeNumber() . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Return the public signed URL the owner can copy/share. Used by the
     * UI "Copy link" button (returns JSON for convenience).
     */
    public function publicLink(Request $request, Invoice $invoice): \Illuminate\Http\JsonResponse
    {
        $this->authorizeOwner($request, $invoice);

        return response()->json([
            'url' => $this->publicUrl($invoice),
            'expires_in_days' => self::SHARE_TTL_DAYS,
        ]);
    }

    public static function makePublicUrl(Invoice $invoice): string
    {
        return URL::signedRoute(
            'invoices.public',
            ['invoice' => $invoice->id],
            now()->addDays(self::SHARE_TTL_DAYS)
        );
    }

    private function publicUrl(Invoice $invoice): string
    {
        return self::makePublicUrl($invoice);
    }

    private function authorizeOwner(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    /** Parse a comma-separated list of email addresses, trimmed & validated. */
    private function parseEmails(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }
}
