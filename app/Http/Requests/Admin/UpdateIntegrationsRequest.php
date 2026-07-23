<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIntegrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'trello_board_id' => ['nullable', 'string', 'max:80'],
            'trello_board_url' => ['nullable', 'url', 'max:255'],
            'trello_list_todo_id' => ['nullable', 'string', 'max:80'],
            'notion_database_id' => ['nullable', 'string', 'max:80'],
            'notion_workspace_url' => ['nullable', 'url', 'max:255'],
            'notion_default_page_url' => ['nullable', 'url', 'max:255'],
            'telegram_allowed_chat_ids' => ['nullable', 'string', 'max:255'],
            'telegram_notify_chat_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
