<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a bank statement: money in (credit) or out (debit), and what
 * it has been matched to in the books.
 */
class BankStatementLine extends Model
{
    protected $fillable = [
        'company_id', 'bank_statement_id', 'txn_date', 'description', 'reference', 'debit', 'credit', 'balance',
        'status', 'matched_type', 'matched_id', 'matched_at', 'line_hash',
    ];

    protected $casts = [
        'txn_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matched(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }

    public function amount(): float
    {
        return (float) $this->credit > 0 ? (float) $this->credit : (float) $this->debit;
    }
}
