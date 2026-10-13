<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_bill_id', 'product_id', 'sort_order',
        'description', 'hsn_sac',
        'quantity', 'unit', 'rate', 'discount', 'amount',
        'gst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchaseBill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class);
    }

    /** The saved item this line brings into stock, when linked. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
