<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JirauCardLink extends Model
{
    protected $fillable = [
        'card_id',
        'created_by',
        'url',
        'kind',
        'title',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(JirauCard::class, 'card_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return $this->kind === 'photos' ? 'Álbum Google Fotos' : 'Google Drive';
    }
}
