<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\PhoneNumber;
use App\Support\WhatsAppLink;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $phoneParts = PhoneNumber::normalizeInput([
            'phone_country' => $this->input('contact_phone_country'),
            'phone_number' => $this->input('contact_phone_number'),
            'phone' => $this->input('contact_phone'),
        ]);

        $telegramHandle = trim((string) $this->input('telegram_handle', ''));
        if ($telegramHandle !== '' && ! str_starts_with($telegramHandle, '@')) {
            $telegramHandle = '@'.$telegramHandle;
        }

        $this->merge([
            'contact_phone' => $phoneParts['phone'],
            'contact_whatsapp' => WhatsAppLink::normalize($this->input('contact_whatsapp')),
            'telegram_handle' => $telegramHandle !== '' ? $telegramHandle : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isos = PhoneNumber::countries()->pluck('iso')->all();

        return [
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone_country' => ['nullable', 'string', Rule::in($isos)],
            'contact_phone_number' => ['nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9]+$/'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:60'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'telegram_handle' => ['nullable', 'string', 'max:60'],
            'google_calendar_embed' => ['nullable', 'string', 'max:2000'],
            'google_calendar_url' => ['nullable', 'url', 'max:255'],
            'google_calendar_id' => ['nullable', 'string', 'max:180'],
            'google_auto_sync' => ['nullable', Rule::in(['0', '1'])],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_drive_templates_folder_id' => ['nullable', 'string', 'max:128'],
            'google_drive_contracts_folder_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
