<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AvatarService
{
    public const RULES = ['nullable', 'file', 'max:2048', 'mimes:jpeg,jpg,png,webp'];

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function store(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $this->assertSafeRaster($file);

        return $file->store('avatars', 'public');
    }

    public function replace(?string $currentPath, ?UploadedFile $file): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        $this->delete($currentPath);

        return $this->store($file);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function assertSafeRaster(UploadedFile $file): void
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $head = (string) file_get_contents($file->getRealPath() ?: $file->getPathname(), false, null, 0, 256);

        if (str_contains(strtolower($head), '<svg') || str_contains($head, '<?xml')) {
            throw ValidationException::withMessages([
                'avatar' => 'Use uma foto JPEG, PNG ou WebP. SVG não é aceite.',
            ]);
        }

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'avatar' => 'Use uma foto JPEG, PNG ou WebP.',
            ]);
        }
    }
}
