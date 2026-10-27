<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
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
            'estimate' => ['nullable', 'integer', Rule::in(JirauBoardService::estimateOptions())],
            'due_at' => ['nullable', 'date'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('assigned_to') === '') {
            $this->merge(['assigned_to' => null]);
        }
    }
}
