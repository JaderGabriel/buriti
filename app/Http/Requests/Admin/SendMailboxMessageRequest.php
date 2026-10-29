<?php

namespace App\Http\Requests\Admin;

use App\Services\MailAnnotationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class SendMailboxMessageRequest extends FormRequest
{
    private const BLOCKED_EXTENSIONS = ['svg', 'svgz', 'html', 'htm', 'js', 'php', 'phtml', 'exe', 'sh'];

    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'odt', 'ods',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:500'],
            'cc' => ['nullable', 'string', 'max:500'],
            'bcc' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'signature' => ['sometimes', 'boolean'],
            'in_reply_to' => ['nullable', 'string', 'max:250'],
            'reply_folder' => ['nullable', 'string', 'max:200'],
            'reply_uid' => ['nullable', 'integer', 'min:1'],
            'contact_message_id' => ['nullable', 'integer', 'min:1'],
            'draft' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $files = $this->file('attachments', []);
            if (! is_array($files)) {
                $files = $files ? [$files] : [];
            }

            $total = 0;
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $total += (int) $file->getSize();
                $ext = mb_strtolower((string) $file->getClientOriginalExtension());
                $mime = (string) ($file->getMimeType() ?: '');
                if (in_array($ext, self::BLOCKED_EXTENSIONS, true) || $mime === 'text/html' || $mime === 'image/svg+xml') {
                    $validator->errors()->add('attachments.'.$index, 'Tipo de anexo não permitido.');

                    continue;
                }
                if ($ext !== '' && ! in_array($ext, self::ALLOWED_EXTENSIONS, true) && ! str_starts_with($mime, 'image/')) {
                    $validator->errors()->add('attachments.'.$index, 'Tipo de anexo não permitido.');
                }
            }

            if ($total > MailAnnotationService::MAX_ATTACHMENT_BYTES) {
                $validator->errors()->add('attachments', 'Os anexos não podem ultrapassar 10 MB no total.');
            }
        });
    }

    /** @return list<UploadedFile> */
    public function uploadedAttachments(): array
    {
        $files = $this->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required' => 'Indica o destinatário.',
            'subject.required' => 'Indica o assunto.',
            'body.required' => 'A mensagem está vazia.',
            'attachments.max' => 'No máximo 5 anexos.',
            'attachments.*.max' => 'Cada anexo pode ter até 10 MB.',
        ];
    }
}
