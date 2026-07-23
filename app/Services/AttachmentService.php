<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function store(
        Model $attachable,
        UploadedFile $file,
        string $kind = 'document',
        ?int $uploadedBy = null,
        ?string $title = null,
    ): Attachment {
        $folder = sprintf(
            'attachments/%s/%s',
            Str::of(class_basename($attachable))->lower()->plural(),
            $attachable->getKey()
        );

        $path = $file->store($folder, 'public');

        return $attachable->attachments()->create([
            'uploaded_by' => $uploadedBy,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'kind' => $kind,
            'title' => $title,
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    public function deleteAllFor(Model $attachable): void
    {
        if (! method_exists($attachable, 'attachments')) {
            return;
        }

        $attachable->attachments()->get()->each(function (Attachment $attachment) {
            $this->delete($attachment);
        });
    }
}
