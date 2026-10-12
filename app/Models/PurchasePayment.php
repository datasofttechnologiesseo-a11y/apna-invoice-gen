<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money paid to a vendor against one purchase bill. */
class PurchasePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'purchase_bill_id',
        'paid_on', 'amount', 'method', 'reference_number', 'notes',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function purchaseBill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
