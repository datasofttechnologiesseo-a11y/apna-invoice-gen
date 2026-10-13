<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'invoice_id',
        'credit_note_number', 'credit_note_date',
        'amount', 'taxable_value',
        'total_cgst', 'total_sgst', 'total_igst',
        'reason', 'notes',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'amount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The invoice lines this note credits, when the owner picked them. Notes
     * without lines (price reductions, and every note issued before lines
     * existed) are spread pro rata over the invoice instead.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('id');
    }

    /**
     * Whether the credit_note_items table exists yet. Until the migration has
     * run on a database, every note is read as it always was (no lines), so
     * GSTR-1/3B and the credit note screens keep working during a deploy.
     */
    public static function linesAvailable(): bool
    {
        static $available = null;

        return $available ??= \Illuminate\Support\Facades\Schema::hasTable('credit_note_items');
    }

    /** The note's lines, or none where the lines table does not exist yet. */
    public function lines(): \Illuminate\Support\Collection
    {
        return self::linesAvailable() ? $this->items : collect();
    }

    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    /** Goods came back: the note may put stock back for its lines. */
    public function isSalesReturn(): bool
    {
        return $this->reason === 'sales_return';
    }

    public function reasonLabel(): string
    {
        return config('credit_note_reasons.' . $this->reason . '.label', ucfirst(str_replace('_', ' ', $this->reason)));
    }
}
