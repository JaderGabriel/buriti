<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use App\Support\JirauSafeLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCardLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jirau = $this->route('jirau');

        return $jirau instanceof Jirau
            && $this->user()?->can('write', $jirau) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'drive_url' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (JirauSafeLink::parse((string) $this->input('drive_url')) === null) {
                $validator->errors()->add('drive_url', 'Use só um link https do Google Drive ou de um álbum do Google Fotos.');
            }
        });
    }
}
