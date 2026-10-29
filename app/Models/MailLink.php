<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MailLink extends Model
{
    protected $fillable = [
        'mail_record_id',
        'linkable_type',
        'linkable_id',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(MailRecord::class, 'mail_record_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function kind(): string
    {
        return match ($this->linkable_type) {
            Project::class => 'project',
            Jirau::class => 'jirau',
            IdeaNote::class => 'idea',
            default => 'other',
        };
    }

    public function kindLabel(): string
    {
        return match ($this->kind()) {
            'project' => 'Projeto',
            'jirau' => 'Jirau',
            'idea' => 'Ideia',
            default => 'Vínculo',
        };
    }

    public function displayName(): string
    {
        $target = $this->linkable;
        if ($target instanceof Project) {
            return $target->name;
        }
        if ($target instanceof Jirau) {
            return $target->name;
        }
        if ($target instanceof IdeaNote) {
            return $target->displayTitle();
        }

        return 'Registo '.$this->linkable_id;
    }
}
