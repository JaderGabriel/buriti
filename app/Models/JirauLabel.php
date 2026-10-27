<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JirauLabel extends Model
{
    protected $fillable = [
        'jirau_id',
        'name',
        'color',
    ];

    public function jirau(): BelongsTo
    {
        return $this->belongsTo(Jirau::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(JirauCard::class, 'jirau_card_label', 'label_id', 'card_id');
    }
}
