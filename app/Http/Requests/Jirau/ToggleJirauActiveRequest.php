<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;

class ToggleJirauActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jirau = $this->route('jirau');

        return $jirau instanceof Jirau
            && $this->user()?->can('update', $jirau) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
