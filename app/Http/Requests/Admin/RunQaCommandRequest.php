<?php

namespace App\Http\Requests\Admin;

use App\Support\QaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunQaCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'command' => ['required', 'string', Rule::in(QaCatalog::ids())],
        ];
    }
}
