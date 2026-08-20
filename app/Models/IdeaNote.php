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
        'company_id',
        'contact_id',
        'sort_order',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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

    public function referenceLabel(): ?string
    {
        $parts = [];
        if ($this->company) {
            $parts[] = $this->company->displayName();
        }
        if ($this->contact) {
            $parts[] = $this->contact->name;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
