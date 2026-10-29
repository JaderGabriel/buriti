<?php

namespace App\Http\Requests\Admin;

use App\Enums\MailMarker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMail') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pasta' => ['required', 'string', 'max:200'],
            'uids' => ['required', 'array', 'min:1', 'max:50'],
            'uids.*' => ['integer', 'min:1'],
            'acao' => ['required', Rule::in(['star', 'unstar', 'seen', 'unseen', 'destroy', 'move', 'marker', 'label'])],
            'destino' => ['required_if:acao,move', 'nullable', 'string', 'max:200'],
            'marker' => ['nullable', Rule::in(array_merge([''], MailMarker::values()))],
            'label_id' => ['required_if:acao,label', 'nullable', 'integer', 'min:1', 'exists:mail_labels,id'],
        ];
    }

    /** @return list<int> */
    public function uids(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('uids', []))));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uids.required' => 'Selecciona pelo menos uma mensagem.',
            'destino.required_if' => 'Informe a pasta de destino.',
            'label_id.required_if' => 'Escolha uma etiqueta.',
        ];
    }
}
