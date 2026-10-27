<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;

class AttachCardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'step_id' => ['nullable', 'integer'],
        ];
    }
}
