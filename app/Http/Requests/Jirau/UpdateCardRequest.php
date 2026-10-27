<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\User;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::rulesFor($this->user());
    }

    /** @return array<string, mixed> */
    public static function rulesFor(?User $user): array
    {
        $admin = $user?->is_admin === true;

        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:8000'],
            'priority' => ['nullable', Rule::enum(JirauCardPriority::class)],
        ];

        if ($admin) {
            $rules['type'] = ['nullable', Rule::enum(JirauCardType::class)];
            $rules['estimate'] = ['nullable', 'integer', Rule::in(JirauBoardService::estimateOptions())];
            $team = Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)
                    ->where(function ($inner) {
                        $inner->where('is_admin', true)->orWhereNotNull('jirau_role');
                    });
            });
            $rules['assigned_to'] = ['nullable', 'integer', $team];
            $rules['responsible_ids'] = ['nullable', 'array'];
            $rules['responsible_ids.*'] = ['integer', $team];
            $rules['actor_ids'] = ['nullable', 'array'];
            $rules['actor_ids.*'] = ['integer', $team];
            $rules['due_at'] = ['nullable', 'date'];
            $rules['label_ids'] = ['nullable', 'array'];
            $rules['label_ids.*'] = ['integer'];
        }

        return $rules;
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
