<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'telegram_handle' => ['nullable', 'string', 'max:60'],
            'google_calendar_embed' => ['nullable', 'string', 'max:2000'],
            'google_calendar_url' => ['nullable', 'url', 'max:255'],
            'google_calendar_id' => ['nullable', 'string', 'max:180'],
            'google_auto_sync' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
