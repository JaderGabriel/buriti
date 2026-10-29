<?php

namespace App\Http\Requests\Jirau;

use App\Enums\JirauStaffRole;
use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    protected $redirectRoute = 'jirau.staff';

    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'jirau_role' => ['required', Rule::enum(JirauStaffRole::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use letras, números, ponto, hífen ou sublinhado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => $this->filled('username')
                ? strtolower(trim((string) $this->input('username')))
                : null,
        ]);
    }
}
