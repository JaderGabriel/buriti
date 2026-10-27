<?php

namespace App\Http\Requests\Jirau;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $mode = $this->input('mode', 'create');

        if ($mode === 'attach') {
            return [
                'mode' => ['required', 'in:attach,create'],
                'login' => ['required', 'string', 'max:180'],
            ];
        }

        return [
            'mode' => ['required', 'in:attach,create'],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->filled('username')
            ? strtolower(trim((string) $this->input('username')))
            : null;

        $this->merge([
            'mode' => $this->input('mode', 'create'),
            'username' => $username,
            'login' => $this->filled('login') ? trim((string) $this->input('login')) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Informe o usuário ou e-mail já cadastrado.',
        ];
    }
}
