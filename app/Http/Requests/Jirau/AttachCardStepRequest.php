<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;

class AttachCardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'step_id' => ['nullable', 'integer'],
        ];
    }
}
