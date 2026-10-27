<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JirauComment extends Model
{
    protected $fillable = [
        'card_id',
        'user_id',
        'body',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(JirauCard::class, 'card_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
