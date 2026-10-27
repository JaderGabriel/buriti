<?php

namespace App\Models;

use App\Enums\JirauStaffRole;
use App\Models\Concerns\HasAttachments;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

// Campos privilegiados (is_admin, is_active, jirau_role, telegram_chat_id, last_login_*)
// só via forceFill em paths controlados — nunca mass assignment.
#[Fillable(['name', 'username', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAttachments, HasFactory, Notifiable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'jirau_role' => JirauStaffRole::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function isJirauStaff(): bool
    {
        return $this->is_active && $this->jirau_role !== null;
    }

    public function canEnterJirau(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->is_admin || $this->jirau_role !== null || $this->jiraus()->exists();
    }

    public function isAssignableOnJirau(): bool
    {
        return $this->is_active && ($this->is_admin || $this->jirau_role !== null);
    }

    /** @return Builder<User> */
    public static function assignableOnJirau(): Builder
    {
        return static::query()
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->where('is_admin', true)
                    ->orWhereNotNull('jirau_role');
            })
            ->orderBy('name');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function jiraus(): BelongsToMany
    {
        return $this->belongsToMany(Jirau::class, 'jirau_members')
            ->withPivot('invited_by')
            ->withTimestamps();
    }

    public static function findByTelegramChatId(string $chatId): ?self
    {
        $chatId = trim($chatId);

        if ($chatId === '') {
            return null;
        }

        return static::query()->where('telegram_chat_id', $chatId)->first();
    }

    public function isTelegramAdminSession(): bool
    {
        return $this->is_admin && $this->is_active && filled($this->telegram_chat_id);
    }

    public function linkTelegramChat(string $chatId): void
    {
        $chatId = trim($chatId);

        static::query()
            ->where('telegram_chat_id', $chatId)
            ->whereKeyNot($this->id)
            ->update(['telegram_chat_id' => null]);

        $this->forceFill(['telegram_chat_id' => $chatId])->save();
    }

    public function unlinkTelegramChat(): void
    {
        $this->forceFill(['telegram_chat_id' => null])->save();
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        // Usa APP_URL (inclui /public em produção) para não cair em /storage na raiz do domínio.
        return Storage::disk('public')->url($this->avatar_path);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
