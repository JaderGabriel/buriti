<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'credential_id', 'public_key', 'sign_count', 'name', 'aaguid', 'last_used_at'])]
class WebauthnCredential extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
