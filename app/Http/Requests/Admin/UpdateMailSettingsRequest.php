<?php

namespace App\Http\Requests\Admin;

use App\Services\MailSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    protected function prepareForValidation(): void
    {
        $username = strtolower(trim((string) $this->input('mail_username')));
        $merge = [];

        if ($username !== '') {
            $merge['mail_username'] = $username;
        }

        if ($this->input('mail_mailer') === 'smtp' && MailSettingsService::mailboxAddress($username) !== null) {
            $merge['mail_from_address'] = $username;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $smtpWithUser = $this->input('mail_mailer') === 'smtp' && filled($this->input('mail_username'));

        return [
            'mail_mailer' => ['required', 'string', Rule::in(['smtp', 'log', 'sendmail'])],
            'mail_host' => ['nullable', 'string', 'max:180'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'mail_username' => ['nullable', 'string', 'max:180', Rule::when($smtpWithUser, ['email'])],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:180'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'mail_imap_host' => ['nullable', 'string', 'max:180'],
            'mail_imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_imap_encryption' => ['nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'mail_imap_sent_folder' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $issue = MailSettingsService::identityIssue(
                $this->input('mail_from_address'),
                $this->input('mail_mailer'),
                $this->input('mail_host'),
                $this->input('mail_username'),
            );

            if ($issue !== null) {
                $validator->errors()->add(
                    filled($this->input('mail_username')) ? 'mail_username' : 'mail_from_address',
                    $issue,
                );
            }
        });
    }
}
