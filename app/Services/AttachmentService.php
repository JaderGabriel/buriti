<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(private AuditLogger $audit) {}

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

        $disk = $kind === 'document' ? 'local' : 'public';
        $path = $file->store($folder, $disk);

        $attachment = $attachable->attachments()->create([
            'uploaded_by' => $uploadedBy,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'kind' => $kind,
            'title' => $title,
        ]);

        $this->audit->record('attachment.created', $attachment, [
            'summary' => $attachment->original_name,
            'kind' => $attachment->kind,
            'size' => $attachment->size,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
        ]);

        return $attachment;
    }

    /**
     * Soft delete: oculta o anexo e mantém o ficheiro no disco para recuperação.
     */
    public function delete(Attachment $attachment, ?int $deletedBy = null): void
    {
        $attachment->forceFill([
            'deleted_by' => $deletedBy,
        ])->save();

        $attachment->delete();

        $this->audit->record('attachment.trashed', $attachment, [
            'summary' => $attachment->original_name,
            'kind' => $attachment->kind,
            'path' => $attachment->path,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
            'deleted_by' => $deletedBy,
        ]);
    }

    public function restore(Attachment $attachment): void
    {
        abort_unless($attachment->trashed(), 404);

        $attachment->restore();
        $attachment->forceFill(['deleted_by' => null])->save();

        $this->audit->record('attachment.restored', $attachment, [
            'summary' => $attachment->original_name,
            'kind' => $attachment->kind,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
        ]);
    }

    /**
     * Eliminação definitiva (disco + BD). Usar com cautela.
     */
    public function purge(Attachment $attachment): void
    {
        $meta = [
            'summary' => $attachment->original_name,
            'kind' => $attachment->kind,
            'path' => $attachment->path,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
        ];

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->forceDelete();

        $this->audit->record('attachment.purged', null, $meta);
    }

    public function deleteAllFor(Model $attachable, ?int $deletedBy = null): void
    {
        if (! method_exists($attachable, 'attachments')) {
            return;
        }

        $attachable->attachments()->get()->each(function (Attachment $attachment) use ($deletedBy) {
            $this->delete($attachment, $deletedBy);
        });
    }
}
