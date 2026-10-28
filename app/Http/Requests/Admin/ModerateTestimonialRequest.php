<?php

namespace App\Http\Requests\Admin;

use App\Enums\TestimonialStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in([
                TestimonialStatus::Approved->value,
                TestimonialStatus::Rejected->value,
                TestimonialStatus::Archived->value,
            ])],
            'use_original' => ['sometimes', 'boolean'],
            'published_stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'published_body' => ['nullable', 'string', 'min:20', 'max:4000'],
            'published_display_name' => ['nullable', 'string', 'max:120'],
            'moderation_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
