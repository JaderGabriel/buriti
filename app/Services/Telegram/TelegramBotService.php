<?php

namespace App\Services\Telegram;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\OpportunityStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Services\SettingService;
use Illuminate\Support\Str;

class TelegramBotService
{
    public function __construct(
        private TelegramApiClient $api,
        private SettingService $settings,
    ) {}

    public function configured(): bool
    {
        return $this->api->configured();
    }

    public function readyForCommands(): bool
    {
        return $this->configured() && $this->allowedChatIds() !== [];
    }

    /** @param  array<string, mixed>  $update */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chatId = (string) data_get($message, 'chat.id', '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        if (! $this->isAllowedChat($chatId) && ! $this->isSetupCommand($text)) {
            $this->api->sendMessage($chatId, '⛔ Chat não autorizado. Envie /id e adicione o número em Integrações → Telegram.');

            return;
        }

        $reply = $this->dispatch($text, $chatId);
        if ($reply !== null) {
            $this->api->sendMessage($chatId, $reply);
        }
    }

    public function notifyContactMessage(ContactMessage $message): void
    {
        if (! $this->api->configured()) {
            return;
        }

        $chatId = $this->settings->get('telegram_notify_chat_id')
            ?: $this->firstAllowedChatId();

        if (! filled($chatId)) {
            return;
        }

        $adminUrl = route('admin.messages.show', $message);
        $body = htmlspecialchars(Str::limit($message->message, 1200), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = implode("\n", array_filter([
            '📬 <b>Nova mensagem do site</b>',
            '',
            '<b>De:</b> '.$this->escape($message->name),
            '<b>E-mail:</b> '.$this->escape($message->email),
            $message->phone ? '<b>Telefone:</b> '.$this->escape($message->phone) : null,
            $message->company ? '<b>Empresa:</b> '.$this->escape($message->company) : null,
            '<b>Assunto:</b> '.$this->escape($message->subject),
            '',
            $body,
            '',
            '🔗 <a href="'.$this->escape($adminUrl).'">Abrir no admin</a>',
        ]));

        $this->api->sendMessage($chatId, $text);
    }

    /** @return list<string> */
    public function allowedChatIds(): array
    {
        $raw = (string) ($this->settings->get('telegram_allowed_chat_ids') ?? '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->values()
            ->all();
    }

    public function isAllowedChat(string $chatId): bool
    {
        $allowed = $this->allowedChatIds();
        $notify = trim((string) ($this->settings->get('telegram_notify_chat_id') ?? ''));

        if ($notify !== '' && ! in_array($notify, $allowed, true)) {
            $allowed[] = $notify;
        }

        if ($allowed === []) {
            return false;
        }

        return in_array($chatId, $allowed, true);
    }

    private function firstAllowedChatId(): ?string
    {
        return $this->allowedChatIds()[0] ?? null;
    }

    private function isSetupCommand(string $text): bool
    {
        $cmd = Str::lower(Str::before($text, ' '));

        return in_array($cmd, ['/start', '/ajuda', '/help', '/id'], true);
    }

    private function dispatch(string $text, string $chatId): ?string
    {
        $parts = preg_split('/\s+/', $text, 2) ?: [];
        $command = Str::lower((string) ($parts[0] ?? ''));
        $argument = trim((string) ($parts[1] ?? ''));

        // Telegram pode enviar /cmd@BotName
        $command = Str::before($command, '@');

        return match ($command) {
            '/start', '/ajuda', '/help' => $this->helpText(),
            '/id' => "🆔 <b>Chat ID:</b> <code>{$chatId}</code>\n\nCole este valor em Integrações → Telegram (chats autorizados / notificação).",
            '/contato' => $this->createContact($argument),
            '/oportunidade' => $this->createOpportunity($argument),
            '/projeto' => $this->createProject($argument),
            '/tarefa' => $this->createTask($argument),
            '/status' => $this->statusSummary(),
            default => "Comando não reconhecido. Envie /ajuda para ver a lista.",
        };
    }

    private function helpText(): string
    {
        return <<<'HTML'
🤖 <b>Bot BURI-TI</b>

Comandos (campos separados por <code>|</code>):

<b>/contato</b> Nome | email | telefone? | empresa?
<b>/oportunidade</b> email_ou_id_contato | Título | estágio? | valor?
<b>/projeto</b> Nome | informação? | categoria?
<b>/tarefa</b> Título | projeto_id? | email_contato? | prioridade? | status?
<b>/status</b> — resumo rápido do CRM
<b>/id</b> — mostra o chat ID deste chat

Estágios oportunidade: lead, qualified, proposal, won, lost
Prioridades tarefa: low, medium, high
Status tarefa: todo, doing, done
HTML;
    }

    private function createContact(string $argument): string
    {
        $fields = $this->splitArgs($argument, 4);
        if (count($fields) < 2) {
            return "Uso: <code>/contato Nome | email | telefone? | empresa?</code>";
        }

        [$name, $email, $phone, $company] = array_pad($fields, 4, null);
        $email = strtolower(trim((string) $email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'E-mail inválido.';
        }

        $contact = Contact::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) $name),
                'phone' => filled($phone) ? trim((string) $phone) : null,
                'company' => filled($company) ? trim((string) $company) : null,
                'status' => ContactStatus::Lead,
                'source' => ContactSource::Telegram,
            ]
        );

        $url = route('admin.contacts.show', $contact);

        return "✅ Contato <b>#{$contact->id}</b> {$this->escape($contact->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function createOpportunity(string $argument): string
    {
        $fields = $this->splitArgs($argument, 4);
        if (count($fields) < 2) {
            return "Uso: <code>/oportunidade email_ou_id | Título | estágio? | valor?</code>";
        }

        [$contactRef, $title, $stageRaw, $valueRaw] = array_pad($fields, 4, null);
        $contact = $this->resolveContact((string) $contactRef);

        if (! $contact) {
            return 'Contato não encontrado. Use o ID ou o e-mail.';
        }

        $stage = OpportunityStage::tryFrom(Str::lower(trim((string) ($stageRaw ?: 'lead'))))
            ?? OpportunityStage::Lead;

        $value = null;
        if (filled($valueRaw)) {
            $normalized = str_replace(['R$', ' '], '', (string) $valueRaw);
            if (str_contains($normalized, ',')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            }
            $value = is_numeric($normalized) ? round((float) $normalized, 2) : null;
        }

        $opportunity = Opportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => trim((string) $title),
            'stage' => $stage,
            'value' => $value,
        ]);

        $url = route('admin.opportunities.edit', $opportunity);

        return "✅ Oportunidade <b>#{$opportunity->id}</b> {$this->escape($opportunity->title)}\n👤 {$this->escape($contact->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function createProject(string $argument): string
    {
        $fields = $this->splitArgs($argument, 3);
        if ($fields === [] || ! filled($fields[0] ?? null)) {
            return "Uso: <code>/projeto Nome | informação? | categoria?</code>";
        }

        [$name, $information, $category] = array_pad($fields, 3, null);

        $project = Project::query()->create([
            'name' => trim((string) $name),
            'information' => filled($information) ? trim((string) $information) : null,
            'category' => filled($category) ? trim((string) $category) : null,
            'status' => ProjectStatus::Active,
            'is_public' => false,
            'repo_is_private' => true,
            'sort_order' => 0,
        ]);

        $url = route('admin.projects.edit', $project);

        return "✅ Projeto <b>#{$project->id}</b> {$this->escape($project->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function createTask(string $argument): string
    {
        $fields = $this->splitArgs($argument, 5);
        if ($fields === [] || ! filled($fields[0] ?? null)) {
            return "Uso: <code>/tarefa Título | projeto_id? | email_contato? | prioridade? | status?</code>";
        }

        [$title, $projectRef, $contactRef, $priorityRaw, $statusRaw] = array_pad($fields, 5, null);

        $projectId = null;
        if (filled($projectRef) && ctype_digit(trim((string) $projectRef))) {
            $projectId = Project::query()->whereKey((int) $projectRef)->value('id');
            if (! $projectId) {
                return 'Projeto não encontrado.';
            }
        }

        $contactId = null;
        if (filled($contactRef)) {
            $contact = $this->resolveContact((string) $contactRef);
            if (! $contact) {
                return 'Contato não encontrado.';
            }
            $contactId = $contact->id;
        }

        $priority = TaskPriority::tryFrom(Str::lower(trim((string) ($priorityRaw ?: 'medium'))))
            ?? TaskPriority::Medium;
        $status = TaskStatus::tryFrom(Str::lower(trim((string) ($statusRaw ?: 'todo'))))
            ?? TaskStatus::Todo;

        $task = Task::query()->create([
            'title' => trim((string) $title),
            'project_id' => $projectId,
            'contact_id' => $contactId,
            'priority' => $priority,
            'status' => $status,
            'want_meet' => true,
        ]);

        $url = route('admin.tasks.index');

        return "✅ Tarefa <b>#{$task->id}</b> {$this->escape($task->title)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir tarefas</a>";
    }

    private function statusSummary(): string
    {
        $contacts = Contact::query()->count();
        $leads = Contact::query()->leads()->count();
        $opportunities = Opportunity::query()->open()->count();
        $projects = Project::query()->where('status', ProjectStatus::Active)->count();
        $tasks = Task::query()->open()->count();
        $unread = ContactMessage::query()->unread()->count();

        return implode("\n", [
            '📊 <b>Resumo BURI-TI</b>',
            '',
            "Contatos: <b>{$contacts}</b> ({$leads} leads)",
            "Oportunidades abertas: <b>{$opportunities}</b>",
            "Projetos ativos: <b>{$projects}</b>",
            "Tarefas abertas: <b>{$tasks}</b>",
            "Mensagens não lidas: <b>{$unread}</b>",
        ]);
    }

    private function resolveContact(string $ref): ?Contact
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            return Contact::query()->find((int) $ref);
        }

        return Contact::query()->where('email', strtolower($ref))->first();
    }

    /** @return list<string> */
    private function splitArgs(string $argument, int $max): array
    {
        if (trim($argument) === '') {
            return [];
        }

        $parts = array_map('trim', explode('|', $argument, $max));

        // Remove apenas vazios no final, mantém slots do meio.
        while ($parts !== [] && end($parts) === '') {
            array_pop($parts);
        }

        return array_values($parts);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
