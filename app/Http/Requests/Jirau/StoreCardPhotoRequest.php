<?php

namespace App\Http\Requests\Jirau;

use App\Services\Jirau\JirauEvidenceService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'max:'.JirauEvidenceService::MAX_PHOTOS],
            'photos.*' => ['file', 'max:'.JirauEvidenceService::MAX_PHOTO_KB, 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Escolha ao menos uma foto ou print.',
            'photos.*.mimes' => 'Só são aceitos prints e fotos em JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto pode ter no máximo 4 MB.',
        ];
    }
}
