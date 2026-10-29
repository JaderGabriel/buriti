<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class MailLabel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
    ];

    /** @var list<string> */
    public const COLORS = ['blue', 'amber', 'mint', 'rose', 'lilac', 'slate'];

    protected static function booted(): void
    {
        static::creating(function (self $label): void {
            if (! filled($label->slug)) {
                $label->slug = Str::slug($label->name) ?: 'etiqueta';
            }
        });
    }

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(MailRecord::class, 'mail_record_label')->withTimestamps();
    }
}
