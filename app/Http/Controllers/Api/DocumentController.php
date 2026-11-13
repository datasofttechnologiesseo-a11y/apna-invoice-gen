<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\CashMemoController as WebCashMemo;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FinanceController as WebFinance;
use App\Http\Controllers\InvoiceController as WebInvoice;
use App\Http\Controllers\QuotationController as WebQuotation;
use App\Http\Resources\InvoiceResource;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceDuplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Documents the web makes that the app also needs: duplicating an invoice,
 * the thermal slip, invoice templates, and the receipt / quotation / expense
 * voucher PDFs. Files come from the web controllers themselves (the same
 * templates, ownership checks and file names); the routes carry the web
 * route names so team roles apply exactly as on the web.
 */
class DocumentController extends Controller
{
    /** POST invoices/{invoice}/duplicate: a fresh editable draft (201, the invoice). */
    public function duplicateInvoice(Request $request, Invoice $invoice, InvoiceDuplicator $duplicator): JsonResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        $copy = $duplicator->duplicate($invoice, $request->user());

        return (new InvoiceResource($copy->load(['items', 'customer.state', 'company.state', 'payments'])))
            ->additional(['message' => 'Invoice copied to a new draft. Check the date and details, then save.'])
            ->response()->setStatusCode(201);
    }

    /**
     * GET invoices/{invoice}/print: the web's 80 mm thermal slip as HTML, for
     * the app to print (e.g. expo-print) - no browser toolbar, no auto-print.
     * A draft prints with its "DRAFT - not a tax invoice" box, as on the web.
     */
    public function invoiceThermal(Request $request, Invoice $invoice): JsonResponse
    {
        $request->query->set('format', 'thermal');
        $html = app(WebInvoice::class)->printView($request, $invoice)->with('embedded', true)->render();

        return response()->json([
            'format' => 'thermal',
            'paper_width_mm' => 80,
            'content_width_mm' => 72,
            'html' => $html,
        ]);
    }

    /** GET invoices/templates: the starting points of the web's "Browse templates", and the PDF styles. */
    public function invoiceTemplates(Request $request, InvoiceCalculator $calculator): JsonResponse
    {
        $request->user()->ensureCompany();

        return response()->json([
            'data' => collect(config('invoice_templates'))->map(fn ($tpl, $key) => [
                'key' => $key,
                'label' => $tpl['label'],
                'tagline' => $tpl['tagline'] ?? null,
                'description' => $tpl['description'] ?? null,
                'audience' => $tpl['audience'] ?? null,
                'tag' => $tpl['tag'] ?? null,
                'style' => $tpl['style'] ?? 'classic',
                'currency' => $tpl['currency'] ?? null,
                // Pre-fill for a new invoice (send style with POST /api/invoices).
                'items' => $calculator->recalculate(new Invoice, $tpl['items'] ?? [], isInterstate: false)['items'],
            ])->values(),
            'meta' => [
                'styles' => collect(config('invoice_styles'))->map(fn ($s, $key) => [
                    'key' => $key, 'label' => $s['label'], 'accent' => $s['accent'] ?? null,
                ])->values(),
            ],
        ]);
    }

    /** GET invoices/templates/{template}/preview: a sample PDF in the template's style. */
    public function invoiceTemplatePreview(Request $request, string $template)
    {
        return app(WebInvoice::class)->templatePreview($request, $template);
    }

    /** GET payments/{payment}/receipt: the payment receipt PDF. */
    public function paymentReceipt(Request $request, Payment $payment)
    {
        return app(WebInvoice::class)->receipt($request, $payment);
    }

    /** GET quotations/{quotation}/pdf (?color=1 for the colour version). */
    public function quotationPdf(Request $request, Quotation $quotation)
    {
        return app(WebQuotation::class)->pdf($request, $quotation);
    }

    /**
     * GET expenses/{expense}/pdf: the expense voucher. An expense written by a
     * cash memo gets the cash memo's PDF; one mirroring a purchase bill has no
     * voucher (the web opens the bill instead), so that is a 422 naming the bill.
     */
    public function expensePdf(Request $request, Expense $expense)
    {
        abort_unless($expense->user_id === $request->user()->id, 403);

        if ($expense->cash_memo_id) {
            return app(WebCashMemo::class)->pdf($request, $expense->cashMemo()->firstOrFail());
        }
        if ($expense->purchase_bill_id) {
            return response()->json([
                'message' => 'This expense comes from a purchase bill. Open the bill for its details.',
                'purchase_bill_id' => $expense->purchase_bill_id,
            ], 422);
        }

        return app(WebFinance::class)->expensePdf($request, $expense);
    }
}
