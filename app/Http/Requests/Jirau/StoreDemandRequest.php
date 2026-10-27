<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauCardPriority;
use App\Services\Jirau\JirauEvidenceService;
use App\Support\JirauSafeLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDemandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:8000'],
            'priority' => ['nullable', Rule::enum(JirauCardPriority::class)],
            'drive_url' => ['nullable', 'string', 'max:2500'],
            'photos' => ['nullable', 'array', 'max:'.JirauEvidenceService::MAX_PHOTOS],
            'photos.*' => ['file', 'max:'.JirauEvidenceService::MAX_PHOTO_KB, 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $blob = (string) $this->input('drive_url', '');
            if (trim($blob) === '') {
                return;
            }

            $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $blob) ?: [])));
            if (count($lines) > JirauEvidenceService::MAX_LINKS) {
                $validator->errors()->add('drive_url', 'No máximo '.JirauEvidenceService::MAX_LINKS.' links por demanda.');

                return;
            }

            foreach ($lines as $line) {
                if (JirauSafeLink::parse($line) === null) {
                    $validator->errors()->add('drive_url', 'Use só links https do Google Drive ou de álbum do Google Fotos.');

                    return;
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Descreva a demanda em um título.',
            'photos.*.mimes' => 'Só são aceitos prints e fotos em JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto pode ter no máximo 4 MB.',
        ];
    }
}
