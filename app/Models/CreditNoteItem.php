<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One invoice line credited by a credit note: the quantity returned and its
 * share of the line's taxable value and tax, frozen when the note is issued.
 */
class CreditNoteItem extends Model
{
    protected $fillable = [
        'credit_note_id', 'invoice_item_id', 'product_id',
        'hsn_sac', 'description', 'quantity', 'unit', 'gst_rate',
        'taxable_value', 'cgst', 'sgst', 'igst',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'gst_rate' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'igst' => 'decimal:2',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tax(): float
    {
        return round((float) $this->cgst + (float) $this->sgst + (float) $this->igst, 2);
    }

    /** Taxable value plus tax: what this line takes off the invoice. */
    public function total(): float
    {
        return round((float) $this->taxable_value + $this->tax(), 2);
    }
}
