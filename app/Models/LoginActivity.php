<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'successful',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $inner) use ($user) {
                    $inner->whereNull('user_id')->where('email', $user->email);
                });
        });
    }

    public function browserSummary(): string
    {
        $ua = (string) $this->user_agent;

        return $ua === '' ? '—' : Str::limit($ua, 72);
    }
}
