<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('login')) {
            $this->merge([
                'login' => trim((string) $this->input('login')),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:180'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'login.required' => 'Informe o e-mail ou o username.',
            'password.required' => 'Informe a senha.',
        ];
    }

    public function loginField(): string
    {
        $login = (string) $this->input('login');

        return filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    /** @return array{email?: string, username?: string, password: string} */
    public function credentials(): array
    {
        return [
            $this->loginField() => $this->input('login'),
            'password' => $this->input('password'),
        ];
    }
}
