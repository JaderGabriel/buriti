<?php

namespace App\Services\Jirau;

use App\Models\Attachment;
use App\Models\JirauCard;
use App\Models\JirauCardLink;
use App\Models\User;
use App\Services\AttachmentService;
use App\Support\JirauSafeLink;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class JirauEvidenceService
{
    public const MAX_PHOTOS = 8;

    public const MAX_LINKS = 5;

    public const MAX_PHOTO_KB = 4096;

    /** @var list<string> */
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    public const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private AttachmentService $attachments) {}

    /** @return list<UploadedFile> */
    public static function filesFromRequest(Request $request, string $key = 'photos'): array
    {
        $files = $request->file($key, []);
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [],
            fn ($file) => $file instanceof UploadedFile,
        ));
    }

    public function canManage(?User $user, JirauCard $card): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        $card->loadMissing('jirau');
        if (! $card->jirau?->is_active) {
            return false;
        }

        if ($user->is_admin || $user->jirau_role !== null) {
            return true;
        }

        return $card->isInbox() && (int) $card->created_by === (int) $user->id;
    }

    public function assertManage(?User $user, JirauCard $card): void
    {
        abort_unless($this->canManage($user, $card), 403, 'Sem permissão para anexar evidências neste cartão.');
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function attachPhotos(JirauCard $card, User $actor, array $files): void
    {
        $current = $card->photoAttachments()->count();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if ($current >= self::MAX_PHOTOS) {
                throw ValidationException::withMessages([
                    'photos' => 'No máximo '.self::MAX_PHOTOS.' fotos ou prints por cartão.',
                ]);
            }

            $this->assertSafePhoto($file);
            $this->attachments->store($card, $file, 'photo', $actor->id);
            $current++;
        }
    }

    /**
     * @param  list<string>  $urls
     */
    public function attachLinks(JirauCard $card, User $actor, array $urls): void
    {
        $current = $card->links()->count();

        foreach ($urls as $raw) {
            $parsed = JirauSafeLink::parse((string) $raw);
            if ($parsed === null) {
                throw ValidationException::withMessages([
                    'drive_url' => 'Use só um link https do Google Drive ou de um álbum do Google Fotos.',
                ]);
            }

            if ($current >= self::MAX_LINKS) {
                throw ValidationException::withMessages([
                    'drive_url' => 'No máximo '.self::MAX_LINKS.' links por cartão.',
                ]);
            }

            $exists = $card->links()->where('url', $parsed['url'])->exists();
            if ($exists) {
                continue;
            }

            $card->links()->create([
                'created_by' => $actor->id,
                'url' => $parsed['url'],
                'kind' => $parsed['kind'],
            ]);
            $current++;
        }
    }

    public function deletePhoto(JirauCard $card, Attachment $attachment, User $actor): void
    {
        abort_unless((int) $attachment->attachable_id === (int) $card->id, 404);
        abort_unless($attachment->attachable_type === $card->getMorphClass(), 404);
        abort_unless($attachment->kind === 'photo', 404);

        $this->attachments->delete($attachment, $actor->id);
    }

    public function deleteLink(JirauCard $card, JirauCardLink $link): void
    {
        abort_unless((int) $link->card_id === (int) $card->id, 404);
        $link->delete();
    }

    public function assertSafePhoto(UploadedFile $file): void
    {
        if ($file->getSize() && $file->getSize() > self::MAX_PHOTO_KB * 1024) {
            throw ValidationException::withMessages([
                'photos' => 'Cada foto pode ter no máximo 4 MB.',
            ]);
        }

        $ext = mb_strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: '');

        $ok = in_array($ext, self::PHOTO_EXTENSIONS, true)
            && in_array($mime, self::PHOTO_MIMES, true);

        if (! $ok) {
            throw ValidationException::withMessages([
                'photos' => 'Só são aceitos prints e fotos em JPG, PNG ou WebP.',
            ]);
        }
    }
}
