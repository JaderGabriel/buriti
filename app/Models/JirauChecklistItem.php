<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JirauChecklistItem extends Model
{
    protected $fillable = [
        'card_id',
        'title',
        'is_done',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(JirauCard::class, 'card_id');
    }
}
