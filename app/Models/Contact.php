<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Concerns\HasAttachments;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'company_id',
        'role',
        'preferred_channel',
        'status',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'source' => ContactSource::class,
        ];
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function companyLabel(): ?string
    {
        $label = $this->clientCompany?->name ?: $this->company;

        return filled($label) ? (string) $label : null;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContactStatus::Active);
    }

    public function scopeLeads(Builder $query): Builder
    {
        return $query->where('status', ContactStatus::Lead);
    }

    public function alphabetLetter(): string
    {
        return self::letterFromName($this->name);
    }

    public static function letterFromName(?string $name): string
    {
        $normalized = Str::upper(Str::ascii(trim((string) $name)));
        $letter = mb_substr($normalized, 0, 1);

        return preg_match('/^[A-Z]$/', $letter) === 1 ? $letter : '#';
    }

    public function whatsappUrl(): ?string
    {
        $digits = PhoneNumber::digits($this->phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    public function telUrl(): ?string
    {
        $digits = PhoneNumber::digits($this->phone);

        return $digits ? 'tel:+'.$digits : null;
    }
}
