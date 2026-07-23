<?php

namespace App\Models;

use App\Enums\IdeaNoteColor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaNote extends Model
{
    /** @use HasFactory<\Database\Factories\IdeaNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'color' => IdeaNoteColor::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : 'Sem título';
    }

    public function isBlank(): bool
    {
        return trim((string) $this->title) === '' && trim((string) $this->body) === '';
    }
}
