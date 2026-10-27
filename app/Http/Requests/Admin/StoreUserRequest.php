<?php

namespace App\Http\Requests\Admin;

use App\Enums\JirauStaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
            'jirau_role' => ['nullable', Rule::enum(JirauStaffRole::class)],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_admin' => $this->boolean('is_admin'),
            'username' => $this->filled('username')
                ? strtolower(trim((string) $this->input('username')))
                : null,
            'jirau_role' => $this->filled('jirau_role') ? $this->input('jirau_role') : null,
        ]);
    }
}
