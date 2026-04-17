<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = ['code', 'name', 'gst_code', 'is_union_territory'];

    protected $casts = [
        'is_union_territory' => 'boolean',
    ];
}
