<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:20', 'max:4000'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'stars.required' => 'Escolha de 1 a 5 estrelas.',
            'body.required' => 'Escreva o depoimento com as suas palavras.',
            'body.min' => 'O depoimento precisa de pelo menos 20 caracteres.',
        ];
    }
}
