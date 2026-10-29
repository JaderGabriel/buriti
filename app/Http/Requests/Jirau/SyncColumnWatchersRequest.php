<?php

namespace App\Http\Requests\Jirau;

use App\Models\Jirau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncColumnWatchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Jirau::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)
                    ->where(function ($inner) {
                        $inner->where('is_admin', true)->orWhereNotNull('jirau_role');
                    });
            })],
        ];
    }
}
