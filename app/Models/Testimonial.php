<?php

namespace App\Models;

use App\Enums\TestimonialStatus;
use App\Support\TestimonialSeal;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'contact_id',
        'project_id',
        'company_id',
        'invited_by_user_id',
        'moderated_by_user_id',
        'status',
        'original_stars',
        'original_body',
        'original_display_name',
        'published_stars',
        'published_body',
        'published_display_name',
        'invite_token_hash',
        'invite_expires_at',
        'submitted_at',
        'submitted_ip',
        'submitted_user_agent',
        'moderated_at',
        'moderation_notes',
        'source_digest',
        'result_digest',
        'seal',
        'verification_code',
        'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestimonialStatus::class,
            'original_stars' => 'integer',
            'published_stars' => 'integer',
            'invite_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'moderated_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function scopeApprovedPublic(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where($table.'.status', TestimonialStatus::Approved)
            ->whereNotNull($table.'.published_body')
            ->whereNotNull($table.'.seal');
    }

    public function scopeSamples(Builder $query): Builder
    {
        return $query->where('is_sample', true);
    }

    public function publishedQuote(): string
    {
        return (string) ($this->published_body ?: $this->original_body ?: '');
    }

    public function isOpenInvite(): bool
    {
        return $this->status === TestimonialStatus::Invited
            && $this->invite_token_hash !== null
            && ($this->invite_expires_at === null || $this->invite_expires_at->isFuture());
    }

    public function wasMediated(): bool
    {
        if ($this->published_body === null || $this->original_body === null) {
            return false;
        }

        return $this->published_body !== $this->original_body
            || (int) $this->published_stars !== (int) $this->original_stars
            || (string) $this->published_display_name !== (string) $this->original_display_name;
    }

    public function stars(): ?int
    {
        return $this->published_stars ?? $this->original_stars;
    }

    public function displayName(): string
    {
        return (string) ($this->published_display_name ?: $this->original_display_name ?: $this->contact?->name ?: 'Cliente');
    }

    public function sourceFingerprint(): ?string
    {
        return $this->source_digest ? TestimonialSeal::fingerprint($this->source_digest) : null;
    }

    public function resultFingerprint(): ?string
    {
        return $this->result_digest ? TestimonialSeal::fingerprint($this->result_digest) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticity(): array
    {
        return TestimonialSeal::inspect($this);
    }
}
