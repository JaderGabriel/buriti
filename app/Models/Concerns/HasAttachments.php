<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function trashedAttachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->onlyTrashed()->latest('deleted_at');
    }

    public function documentAttachments(): MorphMany
    {
        return $this->attachments()->where('kind', 'document');
    }

    public function mediaAttachments(): MorphMany
    {
        return $this->attachments()->whereIn('kind', ['media', 'photo']);
    }

    public function photoAttachments(): MorphMany
    {
        return $this->attachments()->where('kind', 'photo');
    }
}
