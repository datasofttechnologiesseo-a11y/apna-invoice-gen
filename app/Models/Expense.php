<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'entry_date', 'category', 'vendor_name', 'description',
        'amount', 'gst_amount', 'is_interstate', 'itc_eligible',
        'payment_method', 'reference_number', 'notes',
        'cash_memo_id', 'purchase_bill_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'is_interstate' => 'boolean',
        'itc_eligible' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashMemo(): BelongsTo
    {
        return $this->belongsTo(CashMemo::class);
    }

    public function categoryLabel(): string
    {
        return config('expense_categories.' . $this->category . '.label', ucfirst($this->category));
    }

    public function categoryColor(): string
    {
        return config('expense_categories.' . $this->category . '.color', '#6b7280');
    }

    /** Cash actually paid out (pre-GST amount + GST component) */
    public function cashOutflow(): float
    {
        return (float) $this->amount + (float) $this->gst_amount;
    }

    /**
     * GST on a purchase cannot be more than 40% of its value - the highest
     * slab. A slip like GST 18,000 on a 1,000 bill would otherwise go straight
     * into GSTR-3B as input credit.
     */
    public static function assertGstWithinSlab(float $amount, float $gst): void
    {
        if ($gst > round($amount * 0.40, 2) + 0.5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'gst_amount' => 'GST of ₹' . inr($gst) . ' is more than the highest rate (40%) on ₹' . inr($amount) . '. '
                    . 'Enter the amount before GST in Amount and only the tax in GST.',
            ]);
        }
    }
}
