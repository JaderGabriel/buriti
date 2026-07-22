<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'information' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'stack' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'is_public' => ['sometimes', 'boolean'],
            'repo_is_private' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'contract' => ['nullable', 'file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:10240'],
        ];
    }

    /** @return array<string, mixed> */
    public function projectData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'information' => $this->input('information'),
            'category' => $this->input('category'),
            'stack' => $this->stackList(),
            'website_url' => $this->input('website_url'),
            'github_url' => $this->input('github_url'),
            'status' => $this->string('status')->toString(),
            'is_public' => $this->boolean('is_public'),
            'repo_is_private' => $this->boolean('repo_is_private'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ];
    }

    /** @return list<string>|null */
    private function stackList(): ?array
    {
        $raw = trim((string) $this->input('stack', ''));

        if ($raw === '') {
            return null;
        }

        return collect(preg_split('/\s*,\s*/', $raw) ?: [])
            ->filter()
            ->values()
            ->all();
    }
}
