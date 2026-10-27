<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item' => ['nullable', 'string', 'max:180'],
            'title' => ['nullable', 'string', 'max:180'],
        ];
    }

    public function itemTitle(): string
    {
        $item = trim((string) $this->input('item', ''));
        if ($item !== '') {
            return $item;
        }

        return trim((string) $this->input('title', ''));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->itemTitle() === '') {
                $validator->errors()->add('item', 'Descreva o item da checklist.');
            }
        });
    }
}
