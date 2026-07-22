<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AvatarService
{
    public function store(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

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
}
