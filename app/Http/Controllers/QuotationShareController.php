<?php

namespace App\Http\Controllers;

use App\Mail\QuotationMail;
use App\Models\AuditLog;
use App\Models\Quotation;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sharing for Quotations: signed public link, email-with-PDF, and the public
 * stream that the customer (no login) opens. Mirrors InvoiceShareController so
 * the operator's mental model is identical between invoices and quotes.
 */
class QuotationShareController extends Controller
{
    private const SHARE_TTL_DAYS = 30;

    /**
     * Email the quotation to the customer with the PDF attached.
     * Sending a quote also auto-promotes it from draft → sent (and assigns the
     * quote number) so the customer can't receive an unnumbered "Draft" PDF.
     */
    public function email(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeOwner($request, $quotation);

        // Don't allow sending an already-converted or declined quote.
        abort_if(in_array($quotation->status, ['converted', 'declined']), 422,
            'This quotation has already been ' . $quotation->status . ' and cannot be re-shared.');

        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // First-send: assign the quote number and flip to "sent".
        if ($quotation->isDraft()) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($quotation) {
                $company = $quotation->company()->lockForUpdate()->first();
                $number = $company->bumpQuoteCounter($quotation->quote_date);
                $quotation->update([
                    'quote_number' => $number,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            });
            $quotation->refresh();
        }

        $ccEmails = $this->parseEmails($data['cc'] ?? '');

        try {
            $publicUrl = $this->publicUrl($quotation);

            $mail = Mail::to($data['to']);
            if (! empty($ccEmails)) {
                $mail->cc($ccEmails);
            }
            $mail->send(new QuotationMail($quotation, $data['subject'], $data['body'], $publicUrl));
        } catch (\Throwable $e) {
            Log::error('Quotation email failed', ['quotation_id' => $quotation->id, 'exception' => $e]);
            return back()->withErrors(['email' => "We couldn't send the email right now. Please try again in a moment or contact support if the problem persists."]);
        }

        AuditLog::record('quotation.emailed',
            "Quotation {$quotation->quote_number} emailed to {$data['to']}",
            $quotation,
            ['to' => $data['to'], 'cc' => $ccEmails]
        );

        return back()->with('status', "Quotation emailed to {$data['to']}.");
    }

    /**
     * Public signed view — stream the PDF directly to whoever opens the link.
     * Used by recipients who tap the WhatsApp / email link without an account.
     */
    public function publicView(Request $request, Quotation $quotation): Response
    {
        // Signed URL middleware enforces signature + expiry. Once the quote is
        // declined, kill the link too so a stale forward can't re-open it.
        abort_if($quotation->isDeclined(), 410, 'This quotation has been declined and is no longer available.');

        $quotation->load(['items.product:id,name', 'customer.state', 'company.state']);
        $amountInWords = NumberToWords::indianRupees((float) $quotation->grand_total, $quotation->currency);
        // Default ink-saver for the public link too — recipients almost always
        // print rather than re-share digitally.
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('quotations.pdf', compact('quotation', 'amountInWords', 'print'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = ($quotation->quote_number ?: 'quotation-draft-' . $quotation->id) . '.pdf';
        return $pdf->stream($filename);
    }

    public function publicLink(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorizeOwner($request, $quotation);
        return response()->json([
            'url' => $this->publicUrl($quotation),
            'expires_in_days' => self::SHARE_TTL_DAYS,
        ]);
    }

    public static function makePublicUrl(Quotation $quotation): string
    {
        return URL::signedRoute(
            'quotations.public',
            ['quotation' => $quotation->id],
            now()->addDays(self::SHARE_TTL_DAYS)
        );
    }

    private function publicUrl(Quotation $quotation): string
    {
        return self::makePublicUrl($quotation);
    }

    private function authorizeOwner(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
    }

    private function parseEmails(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }
}
