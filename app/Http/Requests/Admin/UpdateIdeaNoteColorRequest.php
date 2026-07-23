<?php

namespace App\Http\Requests\Admin;

use App\Enums\IdeaNoteColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIdeaNoteColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'color' => ['required', Rule::enum(IdeaNoteColor::class)],
        ];
    }
}
