<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\Jirau;
use App\Services\Jirau\JirauBoardService;
use App\Services\Jirau\JirauEvidenceService;
use App\Support\JirauSafeLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $jirau = $this->route('jirau');

        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:8000'],
            'type' => ['required', Rule::enum(JirauCardType::class)],
            'priority' => ['required', Rule::enum(JirauCardPriority::class)],
            'column_id' => ['required', 'integer', Rule::exists('jirau_columns', 'id')->where('jirau_id', $jirau?->id)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)
                    ->where(function ($inner) {
                        $inner->where('is_admin', true)->orWhereNotNull('jirau_role');
                    });
            })],
            'responsible_ids' => ['nullable', 'array'],
            'responsible_ids.*' => ['integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)
                    ->where(function ($inner) {
                        $inner->where('is_admin', true)->orWhereNotNull('jirau_role');
                    });
            })],
            'actor_ids' => ['nullable', 'array'],
            'actor_ids.*' => ['integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)
                    ->where(function ($inner) {
                        $inner->where('is_admin', true)->orWhereNotNull('jirau_role');
                    });
            })],
            'estimate' => ['nullable', 'integer', Rule::in(JirauBoardService::estimateOptions())],
            'due_at' => ['nullable', 'date'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
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
                $validator->errors()->add('drive_url', 'No máximo '.JirauEvidenceService::MAX_LINKS.' links por cartão.');

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
            'photos.*.mimes' => 'Só são aceitos prints e fotos em JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto pode ter no máximo 4 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('assigned_to') === '') {
            $this->merge(['assigned_to' => null]);
        }
        if ($this->has('responsible_ids_sync')) {
            $this->merge(['responsible_ids' => array_values(array_filter((array) $this->input('responsible_ids', [])))]);
        }
        if ($this->has('actor_ids_sync')) {
            $this->merge(['actor_ids' => array_values(array_filter((array) $this->input('actor_ids', [])))]);
        }
    }
}
