<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_user_id', 'referee_user_id',
        'referral_code', 'reward_status',
        'signed_up_at', 'rewarded_at', 'notes',
    ];

    protected $casts = [
        'signed_up_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }
}
