<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'company_id',
        'channel', 'recipient', 'status',
        'days_past_due', 'trigger', 'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'days_past_due' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
