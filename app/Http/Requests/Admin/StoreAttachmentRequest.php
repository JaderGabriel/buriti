<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            $mime = (string) ($file->getClientMimeType() ?: $file->getMimeType());
            $ext = mb_strtolower($file->getClientOriginalExtension());

            $ok = match ($kind) {
                'photo' => str_starts_with($mime, 'image/')
                    || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                'media' => str_starts_with($mime, 'image/')
                    || str_starts_with($mime, 'video/')
                    || str_starts_with($mime, 'audio/')
                    || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'mp3', 'wav'], true),
                default => str_starts_with($mime, 'image/')
                    || $mime === 'application/pdf'
                    || in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'jpg', 'jpeg', 'png', 'webp'], true),
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
