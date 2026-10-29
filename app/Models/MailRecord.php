<?php

namespace App\Models;

use App\Enums\MailMarker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailRecord extends Model
{
    protected $fillable = [
        'account',
        'message_key',
        'rfc_message_id',
        'folder',
        'uid',
        'subject',
        'from_address',
        'marker',
    ];

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'marker' => MailMarker::class,
        ];
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(MailLabel::class, 'mail_record_label')->withTimestamps();
    }

    public function links(): HasMany
    {
        return $this->hasMany(MailLink::class);
    }

    public static function normalizeId(?string $messageId): ?string
    {
        $messageId = trim((string) $messageId);
        $messageId = trim($messageId, '<>');
        $messageId = trim($messageId);

        return $messageId !== '' ? mb_substr($messageId, 0, 250) : null;
    }

    public static function key(?string $messageId, string $folder, int $uid): string
    {
        $id = self::normalizeId($messageId);
        if ($id !== null) {
            return 'mid:'.$id;
        }

        return 'uid:'.mb_substr($folder, 0, 120).':'.$uid;
    }
}
