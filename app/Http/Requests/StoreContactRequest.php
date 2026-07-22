<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $iso = strtoupper((string) $this->input('phone_country', 'BR'));
        $countries = collect(config('countries', []))->keyBy('iso');
        $country = $countries->get($iso) ?? $countries->get('BR');

        $local = preg_replace('/\D+/', '', (string) $this->input('phone_number', ''));
        // Remove leading zeros and accidental dial code duplication
        $local = ltrim((string) $local, '0');
        $dial = (string) ($country['dial'] ?? '55');
        if (str_starts_with($local, $dial) && strlen($local) > strlen($dial) + 7) {
            $local = substr($local, strlen($dial));
        }

        $phone = $local !== ''
            ? '+'.$dial.' '.$local
            : null;

        $this->merge([
            'phone_country' => $country['iso'] ?? 'BR',
            'phone_number' => $local !== '' ? $local : null,
            'phone' => $phone,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isos = collect(config('countries', []))->pluck('iso')->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone_country' => ['required', 'string', Rule::in($isos)],
            'phone_number' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9]+$/'],
            'phone' => ['required', 'string', 'min:10', 'max:40'],
            'preferred_channel' => ['required', Rule::in(['email', 'phone', 'whatsapp'])],
            'company' => ['nullable', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy_consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o seu nome.',
            'email.required' => 'Informe o seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'phone_country.required' => 'Selecione o país do telefone.',
            'phone_country.in' => 'País inválido.',
            'phone_number.required' => 'Informe o telefone para contato.',
            'phone_number.min' => 'Informe um telefone válido.',
            'phone_number.regex' => 'Use apenas números no telefone (sem DDI).',
            'phone.required' => 'Informe o telefone para contato.',
            'preferred_channel.required' => 'Escolha o canal preferido de contato.',
            'preferred_channel.in' => 'Canal de contato inválido.',
            'subject.required' => 'Informe o assunto.',
            'message.required' => 'Escreva a sua mensagem.',
            'privacy_consent.accepted' => 'É necessário aceitar a Política de Privacidade para enviar o contato.',
            'website.max' => 'Envio inválido.',
        ];
    }
}
