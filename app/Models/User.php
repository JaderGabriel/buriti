<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'username', 'email', 'password', 'avatar_path', 'is_admin', 'is_active', 'last_login_at', 'last_login_ip', 'telegram_chat_id'])]
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
            'last_login_at' => 'datetime',
        ];
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
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
