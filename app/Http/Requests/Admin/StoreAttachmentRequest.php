<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'mp3', 'wav'];

    private const DOCUMENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip',
        'jpg', 'jpeg', 'png', 'webp',
    ];

    private const BLOCKED_EXTENSIONS = ['svg', 'svgz', 'html', 'htm', 'js', 'php', 'phtml', 'exe', 'sh'];

    private const BLOCKED_MIMES = [
        'image/svg+xml',
        'text/html',
        'application/javascript',
        'text/javascript',
    ];

    public function authorize(): bool
    {
        return $this->user()?->is_admin === true && $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'kind' => ['nullable', Rule::in(['document', 'media', 'photo'])],
            'title' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('file');
            if (! $file) {
                return;
            }

            $kind = $this->input('kind', 'document');
            // Prefere MIME detectado no servidor (finfo), não o declarado pelo cliente.
            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: '');
            $ext = mb_strtolower((string) $file->getClientOriginalExtension());

            if (in_array($ext, self::BLOCKED_EXTENSIONS, true) || in_array($mime, self::BLOCKED_MIMES, true)) {
                $validator->errors()->add('file', 'Tipo de ficheiro não permitido por segurança.');

                return;
            }

            $ok = match ($kind) {
                'photo' => (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml')
                    || in_array($ext, self::PHOTO_EXTENSIONS, true),
                'media' => (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml')
                    || str_starts_with($mime, 'video/')
                    || str_starts_with($mime, 'audio/')
                    || in_array($ext, self::MEDIA_EXTENSIONS, true),
                default => (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml')
                    || $mime === 'application/pdf'
                    || in_array($ext, self::DOCUMENT_EXTENSIONS, true),
            };

            if (! $ok) {
                $validator->errors()->add('file', 'Tipo de ficheiro inválido para esta pasta.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kind' => $this->input('kind', 'document'),
        ]);
    }
}
