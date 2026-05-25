<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pre-sale price proposal sent to a prospective customer. NOT a tax invoice
 * and intentionally NOT GST-compliant for filing — quotations don't go on
 * GSTR-1, GSTR-3B, or any return. The PDF renders the same line-item table
 * as an invoice for clarity (so the customer sees the price they'll pay) but
 * is titled "Quotation" with a "this is not a tax invoice" disclaimer.
 *
 * Once accepted, can be converted ONCE to a Tax Invoice via
 * QuotationController::convertToInvoice. The quote stays read-only after
 * conversion and links forward to the resulting invoice.
 */
class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'customer_id',
        'quote_number', 'quote_date', 'valid_until',
        'subject', 'reference', 'delivery_period',
        'is_interstate',
        'currency', 'exchange_rate',
        'subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total',
        'status',
        'sent_at', 'accepted_at', 'declined_at', 'decline_reason',
        'converted_to_invoice_id', 'converted_at',
        'notes', 'terms', 'style',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'is_interstate' => 'boolean',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'round_off' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        // Deterministic line order. quotation_items doesn't carry a sort_order
        // column yet (would need a migration), so id is the best we have —
        // since update() deletes + recreates items in array order, id mirrors
        // the row position as the user last saw it.
        return $this->hasMany(QuotationItem::class)->orderBy('id');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    // ─── State helpers ──────────────────────────────────────────────────

    public function isDraft(): bool        { return $this->status === 'draft'; }
    public function isSent(): bool         { return $this->status === 'sent'; }
    public function isAccepted(): bool     { return $this->status === 'accepted'; }
    public function isDeclined(): bool     { return $this->status === 'declined'; }
    public function isConverted(): bool    { return $this->status === 'converted'; }

    /**
     * A quote is "expired" if its valid_until has passed AND it hasn't been
     * accepted, declined, or converted. We compute this on the fly so we
     * don't need a cron job to flip the status.
     */
    public function isExpired(): bool
    {
        if (in_array($this->status, ['accepted', 'declined', 'converted'])) {
            return false;
        }
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /** Effective status for display — collapses "expired" check for sent/draft. */
    public function effectiveStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    /** Whether the quote can still be edited (drafts only — sent quotes are locked). */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /** Whether the quote can be converted to an invoice. */
    public function canBeConverted(): bool
    {
        // Allow conversion from any non-final state EXCEPT already-converted or declined
        return ! $this->isConverted() && ! $this->isDeclined();
    }

    public function displayNumber(): string
    {
        return $this->quote_number ?: 'Draft #' . $this->id;
    }

    /**
     * Days remaining until the quote expires. Returns null if no validity is
     * set, negative when already expired, 0 on the last day. Used by the UI
     * to render a coloured countdown ("Expires in 3 days!"), so the caller
     * can flip styling on `< 0`, `< 3`, `< 7`.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->valid_until->startOfDay(), false);
    }

    /**
     * Status-aware share message — same single-source-of-truth pattern as
     * Invoice::shareMessageText() so WhatsApp and email defaults stay in sync.
     *
     * Branches:
     *   • Accepted → thank-you + "we'll proceed"
     *   • Declined → minimal "noted, get in touch if you change your mind"
     *   • Expired  → "this quote has expired; let us know if you'd like a fresh quote"
     *   • Sent/Draft → standard "find attached" with validity countdown
     */
    public function shareMessageText(): string
    {
        $cust = $this->customer;
        $company = $this->company;
        $name = trim($cust?->name ?? '') ?: 'there';
        $business = trim($company?->name ?? '') ?: 'Us';
        $number = $this->quote_number ?: ('#' . $this->id);
        $grand = number_format((float) $this->grand_total, 2);
        $url = ! $this->isDraft() && ! $this->isDeclined()
            ? \App\Http\Controllers\QuotationShareController::makePublicUrl($this)
            : null;

        $lines = ["Hi {$name},", ''];

        if ($this->isAccepted() || $this->isConverted()) {
            $lines[] = "Thank you for accepting Quotation {$number}.";
            $lines[] = "We'll proceed with the next steps and keep you posted.";
            $lines[] = '';
            $lines[] = "Appreciate your business. — {$business}";
            return implode("\n", $lines);
        }

        if ($this->isDeclined()) {
            $lines[] = "Noted — Quotation {$number} has been declined. Do reach out anytime if you'd like a fresh proposal.";
            $lines[] = '';
            $lines[] = "Thanks, — {$business}";
            return implode("\n", $lines);
        }

        if ($this->isExpired()) {
            $lines[] = "Quotation {$number} for ₹{$grand} has expired.";
            $lines[] = "Happy to share an updated quote whenever you're ready.";
            $lines[] = '';
            $lines[] = "Thanks, — {$business}";
            return implode("\n", $lines);
        }

        $lines[] = "Sharing Quotation {$number} for ₹{$grand}.";
        if ($this->valid_until) {
            $lines[] = "Valid until: " . $this->valid_until->format('d M Y') . ".";
        }
        if ($url) {
            $lines[] = '';
            $lines[] = "View & download: {$url}";
        }
        $lines[] = '';
        $lines[] = "Let me know if you'd like to proceed or have any questions.";
        $lines[] = "Thanks, — {$business}";

        return implode("\n", $lines);
    }

    public function whatsAppShareLink(): ?string
    {
        $phone = $this->customer?->phone;
        if (! $phone) return null;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 10) return null;
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($this->shareMessageText());
    }
}
