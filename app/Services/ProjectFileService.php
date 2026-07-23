<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProjectFileService
{
    public function store(?UploadedFile $file, string $directory, string $disk = 'public'): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($directory, $disk);
    }

    public function replace(?string $currentPath, ?UploadedFile $file, string $directory, string $disk = 'public'): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        if ($currentPath) {
            Storage::disk($disk)->delete($currentPath);
            // Contratos antigos podiam estar no disco público.
            if ($disk === 'local' && str_contains($directory, 'contracts')) {
                Storage::disk('public')->delete($currentPath);
            }
        }

        return $this->store($file, $directory, $disk);
    }

    public function delete(?string $path, string $disk = 'public'): void
    {
        if ($path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
