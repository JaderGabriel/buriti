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
use App\Models\LoginActivity;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyResolver;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TelegramBotService
{
    private const LIST_DEFAULT = 10;

    private const LIST_MAX = 20;

    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_LOCK_SECONDS = 900;

    public function __construct(
        private TelegramApiClient $api,
        private SettingService $settings,
        private AuditLogger $audit,
        private TelegramWebAuthService $webAuth,
        private TelegramShareCardService $shareCard,
        private CompanyResolver $companies,
    ) {}

    public function configured(): bool
    {
        return $this->api->configured();
    }

    public function readyForCommands(): bool
    {
        return $this->configured();
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
        $messageId = (int) data_get($message, 'message_id', 0);

        if ($chatId === '' || $text === '') {
            return;
        }

        if (! $this->chatPassesAllowlist($chatId)) {
            $this->api->sendMessage(
                $chatId,
                '🚫 Este chat não está na lista de IDs autorizados do bot.'
            );

            return;
        }

        $admin = $this->adminSessionForChat($chatId);

        if (! $admin && ! $this->isPublicCommand($text)) {
            $this->api->sendMessage(
                $chatId,
                "🔐 Acesso restrito a <b>administradores</b>.\n\n".
                "Entre com:\n<code>/login email_ou_usuario | senha</code>\n\n".
                "A mensagem com a senha é apagada automaticamente após o login."
            );

            return;
        }

        $reply = $this->dispatch($text, $chatId, $admin);

        if ($this->shouldScrubLoginMessage($text) && $messageId > 0) {
            $this->api->deleteMessage($chatId, $messageId);
        }

        if ($reply !== null) {
            $this->api->sendMessage($chatId, $reply);
        }
    }

    public function notifyContactMessage(ContactMessage $message): void
    {
        if (! $this->api->configured()) {
            return;
        }

        $chatIds = $this->notificationChatIds();
        if ($chatIds === []) {
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

        foreach ($chatIds as $chatId) {
            $this->api->sendMessage($chatId, $text);
        }
    }

    /** @return list<string> */
    public function notificationChatIds(): array
    {
        $ids = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values();

        $notify = trim((string) ($this->settings->get('telegram_notify_chat_id') ?? ''));
        if ($notify !== '') {
            $ids->push($notify);
        }

        return $ids->unique()->values()->all();
    }

    public function adminSessionForChat(string $chatId): ?User
    {
        $user = User::findByTelegramChatId($chatId);

        if (! $user || ! $user->isTelegramAdminSession()) {
            return null;
        }

        return $user;
    }

    /** @return list<string> */
    public function allowedChatIds(): array
    {
        return User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function isAllowedChat(string $chatId): bool
    {
        return $this->chatPassesAllowlist($chatId)
            && $this->adminSessionForChat($chatId) !== null;
    }

    /** @return list<string> */
    private function configuredAllowedChatIds(): array
    {
        $raw = trim((string) ($this->settings->get('telegram_allowed_chat_ids') ?? ''));
        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values()
            ->all();
    }

    private function chatPassesAllowlist(string $chatId): bool
    {
        $allowed = $this->configuredAllowedChatIds();

        // Sem allowlist configurada: mantém comportamento atual (login por senha disponível).
        if ($allowed === []) {
            return true;
        }

        return in_array($chatId, $allowed, true);
    }

    private function isPublicCommand(string $text): bool
    {
        $cmd = Str::lower(Str::before($text, ' '));
        $cmd = Str::before($cmd, '@');

        return in_array($cmd, ['/start', '/ajuda', '/help', '/id', '/login'], true);
    }

    private function dispatch(string $text, string $chatId, ?User $admin): ?string
    {
        $parts = preg_split('/\s+/', $text, 2) ?: [];
        $command = Str::lower((string) ($parts[0] ?? ''));
        $argument = trim((string) ($parts[1] ?? ''));
        $command = Str::before($command, '@');

        return match ($command) {
            '/start' => $this->handleStart($chatId, $argument, $admin),
            '/ajuda', '/help' => $this->helpText($admin),
            '/id' => "🆔 <b>Chat ID:</b> <code>{$chatId}</code>",
            '/login' => $this->login($chatId, $argument),
            '/logout' => $this->logout($chatId, $admin),
            '/eu', '/quem' => $this->whoAmI($admin),
            '/card' => $this->shareClientCard($chatId, $argument),
            '/status' => $this->statusSummary(),
            '/contatos' => $this->listContacts($argument),
            '/contato' => $this->handleContact($argument),
            '/oportunidades' => $this->listOpportunities($argument),
            '/oportunidade' => $this->handleOpportunity($argument),
            '/projetos' => $this->listProjects($argument),
            '/projeto' => $this->handleProject($argument),
            '/tarefas' => $this->listTasks($argument),
            '/tarefa' => $this->handleTask($argument),
            '/mensagens' => $this->listMessages($argument),
            '/mensagem' => $this->handleMessage($argument),
            default => "Comando não reconhecido. Envie /ajuda para ver a lista.",
        };
    }

    private function handleStart(string $chatId, string $argument, ?User $admin): string
    {
        if (Str::startsWith($argument, 'weblogin_')) {
            return $this->confirmWebLogin($chatId, Str::after($argument, 'weblogin_'), $admin);
        }

        return $this->helpText($admin);
    }

    private function confirmWebLogin(string $chatId, string $token, ?User $admin): string
    {
        $token = trim($token);

        if ($token === '' || strlen($token) < 20) {
            return '❌ Pedido de login web inválido. Volte ao site e tente de novo.';
        }

        $status = $this->webAuth->status($token);
        if (($status['status'] ?? '') === 'expired') {
            return '⏳ Este pedido de login expirou. Volte ao site e clique em <b>Entrar com Telegram</b> outra vez.';
        }

        if (! $admin) {
            $this->webAuth->deny($token, 'unlinked');

            return "🔐 Para liberar o painel web, faça primeiro o login neste chat:\n".
                "<code>/login email_ou_usuario | senha</code>\n\n".
                'Depois volte ao site e clique novamente em <b>Entrar com Telegram</b>.';
        }

        if (! $this->webAuth->approve($token, $admin)) {
            return '❌ Não foi possível confirmar o login web (pedido já usado ou expirado).';
        }

        $completeUrl = route('login.telegram.complete', ['token' => $token]);

        $this->audit->record('auth.telegram.web.approved', $admin, [
            'summary' => $admin->email,
            'chat_id' => $chatId,
        ], null, $admin->id);

        return "✅ Login web confirmado, <b>{$this->escape($admin->name)}</b>.\n\n".
            "Volte ao navegador — o painel deve abrir sozinho.\n".
            'Se não abrir: <a href="'.$this->escape($completeUrl).'">concluir acesso</a>';
    }

    private function helpText(?User $admin): string
    {
        if ($admin) {
            $authBlock = "✅ <b>Sessão ativa</b> — {$this->escape($admin->name)} (admin)\n".
                "<code>/eu</code> ver sessão · <code>/logout</code> sair · <code>/status</code> resumo";
        } else {
            $authBlock = "🔐 <b>Sem sessão</b> — só administradores\n".
                "<code>/login email_ou_usuario | senha</code>\n".
                "<i>A mensagem do login (com senha) é apagada automaticamente.</i>\n".
                '<i>Depois pode entrar no painel web com “Entrar com Telegram”.</i>';
        }

        return <<<HTML
🤖 <b>BURI-TI CRM</b>

{$authBlock}

<b>Como usar</b>
• Listar: <code>/contatos</code> <code>/oportunidades</code> <code>/projetos</code> <code>/tarefas</code> <code>/mensagens</code>
• Ver: <code>/contato 12</code> (idem para as outras entidades)
• Criar: <code>/contato add Nome|email|tel?|empresa?|status?</code>
• Editar: <code>/contato set 12|Nome|email|tel|empresa|status</code>
• Apagar: <code>/contato del 12 ok</code>

Em <code>set</code>, use <code>.</code> para manter o valor.
Em <code>del</code>, confirme com <code>ok</code>.

<b>Atalhos</b>
<code>/card</code> · <code>/card Nome do cliente</code> — card para encaminhar
<code>/oportunidade</code> · <code>/projeto</code> · <code>/tarefa</code> · <code>/mensagem</code>
<code>/mensagem lida ID</code> · <code>/id</code>

Campos separados por <code>|</code>.
HTML;
    }

    private function shareClientCard(string $chatId, string $argument): string
    {
        try {
            $card = $this->shareCard->build($argument !== '' ? $argument : null);
        } catch (\Throwable $e) {
            report($e);

            return '❌ Não foi possível gerar o card. Tente de novo em instantes.';
        }

        $sent = $this->api->sendPhoto(
            $chatId,
            $card['path'],
            $card['caption'],
            $card['reply_markup'],
        );

        if (($card['delete_after_send'] ?? false) && is_file($card['path'])) {
            @unlink($card['path']);
        }

        if (! $sent) {
            return '❌ Falha ao enviar o card no Telegram. Verifique o token do bot e tente novamente.';
        }

        return '📤 Card pronto. <b>Encaminhe</b> a imagem acima ao cliente (toque e segure → Encaminhar).';
    }

    private function shouldScrubLoginMessage(string $text): bool
    {
        $parts = preg_split('/\s+/', trim($text), 2) ?: [];
        $command = Str::before(Str::lower((string) ($parts[0] ?? '')), '@');

        if ($command !== '/login') {
            return false;
        }

        $argument = trim((string) ($parts[1] ?? ''));

        return count($this->splitArgs($argument, 2)) >= 2;
    }

    private function login(string $chatId, string $argument): string
    {
        if ($this->isLoginLocked($chatId)) {
            return '⏳ Demasiadas tentativas. Aguarde alguns minutos e tente de novo.';
        }

        $fields = $this->splitArgs($argument, 2);
        if (count($fields) < 2) {
            return "Uso: <code>/login email_ou_usuario | senha</code>\n\nApenas contas <b>admin</b> ativas.\nA mensagem com senha será apagada automaticamente quando enviar as credenciais.";
        }

        [$login, $password] = $fields;
        $login = trim((string) $login);
        $password = (string) $password;

        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('email', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->hitLoginFailure($chatId);
            $this->recordTelegramLoginAttempt($user, $login, false);

            return '❌ Credenciais inválidas. A mensagem com a senha foi removida do chat.';
        }

        if (! $user->is_active) {
            $this->hitLoginFailure($chatId);
            $this->recordTelegramLoginAttempt($user, $login, false);

            return '⛔ Conta inativa. A mensagem com a senha foi removida do chat.';
        }

        if (! $user->is_admin) {
            $this->hitLoginFailure($chatId);
            $this->recordTelegramLoginAttempt($user, $login, false);

            return '⛔ Apenas administradores podem usar o bot. A mensagem com a senha foi removida do chat.';
        }

        $user->linkTelegramChat($chatId);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => 'telegram:'.$chatId,
        ])->save();

        $this->clearLoginFailures($chatId);
        $this->recordTelegramLoginAttempt($user, $login, true);

        return "✅ Login OK, <b>{$this->escape($user->name)}</b>.\n\n".
            "Credenciais removidas do chat automaticamente.\n".
            "Envie /ajuda para ver os comandos.";
    }

    private function logout(string $chatId, ?User $admin): string
    {
        if (! $admin) {
            return 'Não há sessão ativa neste chat.';
        }

        $admin->unlinkTelegramChat();

        $this->audit->record('auth.telegram.logout', $admin, [
            'summary' => $admin->email,
            'chat_id' => $chatId,
        ], null, $admin->id);

        return '👋 Sessão encerrada. Para voltar: <code>/login email_ou_usuario | senha</code>';
    }

    private function whoAmI(?User $admin): string
    {
        if (! $admin) {
            return 'Sem sessão. Use <code>/login email_ou_usuario | senha</code>.';
        }

        return implode("\n", array_filter([
            '👤 <b>Sessão Telegram</b>',
            '<b>Nome:</b> '.$this->escape($admin->name),
            '<b>E-mail:</b> '.$this->escape($admin->email),
            $admin->username ? '<b>Usuário:</b> '.$this->escape($admin->username) : null,
            '<b>Admin:</b> sim',
            '<b>Chat:</b> <code>'.$this->escape((string) $admin->telegram_chat_id).'</code>',
        ]));
    }

    private function isLoginLocked(string $chatId): bool
    {
        return (int) Cache::get($this->loginFailKey($chatId), 0) >= self::LOGIN_MAX_ATTEMPTS;
    }

    private function hitLoginFailure(string $chatId): void
    {
        $key = $this->loginFailKey($chatId);
        $hits = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $hits, self::LOGIN_LOCK_SECONDS);
    }

    private function clearLoginFailures(string $chatId): void
    {
        Cache::forget($this->loginFailKey($chatId));
    }

    private function loginFailKey(string $chatId): string
    {
        return 'telegram:login-fail:'.$chatId;
    }

    private function recordTelegramLoginAttempt(?User $user, string $login, bool $successful): void
    {
        LoginActivity::query()->create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $login,
            'successful' => $successful,
            'ip_address' => null,
            'user_agent' => 'telegram-bot',
            'created_at' => now(),
        ]);

        $this->audit->record(
            $successful ? 'auth.telegram.login.success' : 'auth.telegram.login.failed',
            $user,
            [
                'summary' => $user?->email ?? $login,
                'email' => $user?->email ?? $login,
            ],
            null,
            $user?->id,
        );
    }

    private function handleContact(string $argument): string
    {
        if ($argument === '') {
            return "Uso:\n<code>/contatos</code>\n<code>/contato ID</code>\n<code>/contato add Nome|email|tel?|empresa?|status?</code>\n<code>/contato set ID|Nome|email|tel|empresa|status</code>\n<code>/contato del ID ok</code>";
        }

        [$action, $rest] = $this->parseAction($argument);

        return match ($action) {
            'list', 'lista' => $this->listContacts($rest),
            'add', 'novo', 'create', 'criar' => $this->createContact($rest),
            'set', 'edit', 'update', 'editar' => $this->updateContact($rest),
            'del', 'delete', 'rm', 'apagar', 'remover' => $this->deleteContact($rest),
            'get', 'ver', 'show' => $this->showContact($rest),
            default => ctype_digit($action) && $rest === ''
                ? $this->showContact($action)
                : (str_contains($argument, '|')
                    ? $this->createContact($argument)
                    : $this->showContact($argument)),
        };
    }

    private function listContacts(string $argument): string
    {
        $limit = $this->listLimit($argument);
        $items = Contact::query()->latest('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            return 'Nenhum contato encontrado.';
        }

        $lines = ["👥 <b>Contatos</b> (últimos {$items->count()})", ''];
        foreach ($items as $contact) {
            $status = $contact->status?->value ?? '?';
            $lines[] = "#{$contact->id} · {$this->escape($contact->name)} · {$this->escape($contact->email)} · <i>{$status}</i>";
        }
        $lines[] = '';
        $lines[] = 'Detalhe: <code>/contato ID</code>';

        return implode("\n", $lines);
    }

    private function showContact(string $ref): string
    {
        $contact = $this->resolveContact($ref);
        if (! $contact) {
            return 'Contato não encontrado. Use ID ou e-mail.';
        }

        $url = route('admin.contacts.show', $contact);

        return implode("\n", array_filter([
            "👤 <b>Contato #{$contact->id}</b>",
            '<b>Nome:</b> '.$this->escape($contact->name),
            '<b>E-mail:</b> '.$this->escape($contact->email),
            $contact->phone ? '<b>Telefone:</b> '.$this->escape($contact->phone) : null,
            $contact->companyLabel() ? '<b>Empresa:</b> '.$this->escape($contact->companyLabel()) : null,
            '<b>Status:</b> '.($contact->status?->value ?? '—'),
            '<b>Origem:</b> '.($contact->source?->value ?? '—'),
            "🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>",
        ]));
    }

    private function createContact(string $argument): string
    {
        $fields = $this->splitArgs($argument, 5);
        if (count($fields) < 2) {
            return 'Uso: <code>/contato add Nome|email|tel?|empresa?|status?</code>';
        }

        [$name, $email, $phone, $company, $statusRaw] = array_pad($fields, 5, null);
        $email = strtolower(trim((string) $email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'E-mail inválido.';
        }

        $status = ContactStatus::tryFrom(Str::lower(trim((string) ($statusRaw ?: 'lead'))))
            ?? ContactStatus::Lead;

        $resolvedCompany = $this->companies->findOrCreateByName(
            filled($company) ? trim((string) $company) : null
        );

        $contact = Contact::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) $name),
                'phone' => filled($phone) ? trim((string) $phone) : null,
                'company' => $resolvedCompany?->name,
                'company_id' => $resolvedCompany?->id,
                'status' => $status,
                'source' => ContactSource::Telegram,
            ]
        );

        $url = route('admin.contacts.show', $contact);

        return "✅ Contato <b>#{$contact->id}</b> {$this->escape($contact->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function updateContact(string $argument): string
    {
        $fields = $this->splitArgs($argument, 6);
        if (count($fields) < 2) {
            return 'Uso: <code>/contato set ID|Nome|email|tel|empresa|status</code> (use <code>.</code> para manter)';
        }

        [$id, $name, $email, $phone, $company, $statusRaw] = array_pad($fields, 6, null);
        $contact = $this->resolveContact((string) $id);
        if (! $contact) {
            return 'Contato não encontrado.';
        }

        $data = [];
        if (! $this->keep($name) && filled($name)) {
            $data['name'] = trim((string) $name);
        }
        if (! $this->keep($email) && filled($email)) {
            $normalized = strtolower(trim((string) $email));
            if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                return 'E-mail inválido.';
            }
            $data['email'] = $normalized;
        }
        if (! $this->keep($phone)) {
            $data['phone'] = filled($phone) ? trim((string) $phone) : null;
        }
        if (! $this->keep($company)) {
            $resolvedCompany = $this->companies->findOrCreateByName(
                filled($company) ? trim((string) $company) : null
            );
            $data['company'] = $resolvedCompany?->name;
            $data['company_id'] = $resolvedCompany?->id;
        }
        if (! $this->keep($statusRaw) && filled($statusRaw)) {
            $status = ContactStatus::tryFrom(Str::lower(trim((string) $statusRaw)));
            if (! $status) {
                return 'Status inválido (lead, active, inactive).';
            }
            $data['status'] = $status;
        }

        if ($data === []) {
            return 'Nada para atualizar. Use <code>.</code> só nos campos que quer manter.';
        }

        $contact->update($data);

        return '✏️ Contato atualizado.'."\n".$this->showContact((string) $contact->id);
    }

    private function deleteContact(string $argument): string
    {
        [$id, $confirm] = $this->parseDelete($argument);
        if ($id === null) {
            return 'Uso: <code>/contato del ID ok</code>';
        }
        if (! $confirm) {
            return "Para apagar o contato <b>#{$id}</b>, confirme:\n<code>/contato del {$id} ok</code>";
        }

        $contact = Contact::query()->find($id);
        if (! $contact) {
            return 'Contato não encontrado.';
        }

        $label = $contact->name;
        $contact->delete();

        return "🗑️ Contato <b>#{$id}</b> {$this->escape($label)} removido.";
    }

    // -------------------------------------------------------------------------
    // Oportunidades
    // -------------------------------------------------------------------------

    private function handleOpportunity(string $argument): string
    {
        if ($argument === '') {
            return "Uso:\n<code>/oportunidades</code>\n<code>/oportunidade ID</code>\n<code>/oportunidade add contato|Título|estágio?|valor?</code>\n<code>/oportunidade set ID|contato|Título|estágio|valor</code>\n<code>/oportunidade del ID ok</code>";
        }

        [$action, $rest] = $this->parseAction($argument);

        return match ($action) {
            'list', 'lista' => $this->listOpportunities($rest),
            'add', 'novo', 'create', 'criar' => $this->createOpportunity($rest),
            'set', 'edit', 'update', 'editar' => $this->updateOpportunity($rest),
            'del', 'delete', 'rm', 'apagar', 'remover' => $this->deleteOpportunity($rest),
            'get', 'ver', 'show' => $this->showOpportunity($rest),
            default => ctype_digit($action) && $rest === ''
                ? $this->showOpportunity($action)
                : (str_contains($argument, '|')
                    ? $this->createOpportunity($argument)
                    : $this->showOpportunity($argument)),
        };
    }

    private function listOpportunities(string $argument): string
    {
        $limit = $this->listLimit($argument);
        $items = Opportunity::query()->with('contact')->latest('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            return 'Nenhuma oportunidade encontrada.';
        }

        $lines = ["💼 <b>Oportunidades</b> (últimas {$items->count()})", ''];
        foreach ($items as $item) {
            $contact = $item->contact?->name ?? '—';
            $value = $item->value !== null ? ' · R$ '.number_format((float) $item->value, 2, ',', '.') : '';
            $lines[] = "#{$item->id} · {$this->escape($item->title)} · {$item->stage->value} · {$this->escape($contact)}{$value}";
        }
        $lines[] = '';
        $lines[] = 'Detalhe: <code>/oportunidade ID</code>';

        return implode("\n", $lines);
    }

    private function showOpportunity(string $ref): string
    {
        if (! ctype_digit(trim($ref))) {
            return 'Informe o ID numérico da oportunidade.';
        }

        $item = Opportunity::query()->with('contact')->find((int) $ref);
        if (! $item) {
            return 'Oportunidade não encontrada.';
        }

        $url = route('admin.opportunities.edit', $item);
        $value = $item->value !== null ? 'R$ '.number_format((float) $item->value, 2, ',', '.') : '—';

        return implode("\n", array_filter([
            "💼 <b>Oportunidade #{$item->id}</b>",
            '<b>Título:</b> '.$this->escape($item->title),
            '<b>Estágio:</b> '.$item->stage->value,
            '<b>Valor:</b> '.$value,
            $item->contact ? '<b>Contato:</b> #'.$item->contact->id.' '.$this->escape($item->contact->name) : null,
            "🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>",
        ]));
    }

    private function createOpportunity(string $argument): string
    {
        $fields = $this->splitArgs($argument, 4);
        if (count($fields) < 2) {
            return 'Uso: <code>/oportunidade add contato|Título|estágio?|valor?</code>';
        }

        [$contactRef, $title, $stageRaw, $valueRaw] = array_pad($fields, 4, null);
        $contact = $this->resolveContact((string) $contactRef);

        if (! $contact) {
            return 'Contato não encontrado. Use o ID ou o e-mail.';
        }

        $stage = OpportunityStage::tryFrom(Str::lower(trim((string) ($stageRaw ?: 'lead'))))
            ?? OpportunityStage::Lead;

        $opportunity = Opportunity::query()->create([
            'contact_id' => $contact->id,
            'title' => trim((string) $title),
            'stage' => $stage,
            'value' => $this->parseMoney($valueRaw),
        ]);

        $url = route('admin.opportunities.edit', $opportunity);

        return "✅ Oportunidade <b>#{$opportunity->id}</b> {$this->escape($opportunity->title)}\n👤 {$this->escape($contact->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function updateOpportunity(string $argument): string
    {
        $fields = $this->splitArgs($argument, 5);
        if (count($fields) < 2) {
            return 'Uso: <code>/oportunidade set ID|contato|Título|estágio|valor</code> (use <code>.</code> para manter)';
        }

        [$id, $contactRef, $title, $stageRaw, $valueRaw] = array_pad($fields, 5, null);
        if (! ctype_digit(trim((string) $id))) {
            return 'ID inválido.';
        }

        $item = Opportunity::query()->find((int) $id);
        if (! $item) {
            return 'Oportunidade não encontrada.';
        }

        $data = [];
        if (! $this->keep($contactRef) && filled($contactRef)) {
            $contact = $this->resolveContact((string) $contactRef);
            if (! $contact) {
                return 'Contato não encontrado.';
            }
            $data['contact_id'] = $contact->id;
        }
        if (! $this->keep($title) && filled($title)) {
            $data['title'] = trim((string) $title);
        }
        if (! $this->keep($stageRaw) && filled($stageRaw)) {
            $stage = OpportunityStage::tryFrom(Str::lower(trim((string) $stageRaw)));
            if (! $stage) {
                return 'Estágio inválido.';
            }
            $data['stage'] = $stage;
        }
        if (! $this->keep($valueRaw)) {
            $data['value'] = filled($valueRaw) ? $this->parseMoney($valueRaw) : null;
        }

        if ($data === []) {
            return 'Nada para atualizar.';
        }

        $item->update($data);

        return '✏️ Oportunidade atualizada.'."\n".$this->showOpportunity((string) $item->id);
    }

    private function deleteOpportunity(string $argument): string
    {
        [$id, $confirm] = $this->parseDelete($argument);
        if ($id === null) {
            return 'Uso: <code>/oportunidade del ID ok</code>';
        }
        if (! $confirm) {
            return "Para apagar a oportunidade <b>#{$id}</b>, confirme:\n<code>/oportunidade del {$id} ok</code>";
        }

        $item = Opportunity::query()->find($id);
        if (! $item) {
            return 'Oportunidade não encontrada.';
        }

        $label = $item->title;
        $item->delete();

        return "🗑️ Oportunidade <b>#{$id}</b> {$this->escape($label)} removida.";
    }

    // -------------------------------------------------------------------------
    // Projetos
    // -------------------------------------------------------------------------

    private function handleProject(string $argument): string
    {
        if ($argument === '') {
            return "Uso:\n<code>/projetos</code>\n<code>/projeto ID</code>\n<code>/projeto add Nome|info?|categoria?|status?</code>\n<code>/projeto set ID|Nome|info|categoria|status</code>\n<code>/projeto del ID ok</code>";
        }

        [$action, $rest] = $this->parseAction($argument);

        return match ($action) {
            'list', 'lista' => $this->listProjects($rest),
            'add', 'novo', 'create', 'criar' => $this->createProject($rest),
            'set', 'edit', 'update', 'editar' => $this->updateProject($rest),
            'del', 'delete', 'rm', 'apagar', 'remover' => $this->deleteProject($rest),
            'get', 'ver', 'show' => $this->showProject($rest),
            default => ctype_digit($action) && $rest === ''
                ? $this->showProject($action)
                : (str_contains($argument, '|')
                    ? $this->createProject($argument)
                    : $this->showProject($argument)),
        };
    }

    private function listProjects(string $argument): string
    {
        $limit = $this->listLimit($argument);
        $items = Project::query()->latest('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            return 'Nenhum projeto encontrado.';
        }

        $lines = ["📁 <b>Projetos</b> (últimos {$items->count()})", ''];
        foreach ($items as $item) {
            $cat = $item->category ? ' · '.$this->escape($item->category) : '';
            $lines[] = "#{$item->id} · {$this->escape($item->name)} · {$item->status->value}{$cat}";
        }
        $lines[] = '';
        $lines[] = 'Detalhe: <code>/projeto ID</code>';

        return implode("\n", $lines);
    }

    private function showProject(string $ref): string
    {
        if (! ctype_digit(trim($ref))) {
            return 'Informe o ID numérico do projeto.';
        }

        $item = Project::query()->find((int) $ref);
        if (! $item) {
            return 'Projeto não encontrado.';
        }

        $url = route('admin.projects.edit', $item);

        return implode("\n", array_filter([
            "📁 <b>Projeto #{$item->id}</b>",
            '<b>Nome:</b> '.$this->escape($item->name),
            $item->information ? '<b>Info:</b> '.$this->escape(Str::limit($item->information, 280)) : null,
            $item->category ? '<b>Categoria:</b> '.$this->escape($item->category) : null,
            '<b>Status:</b> '.$item->status->value,
            '<b>Público:</b> '.($item->is_public ? 'sim' : 'não'),
            "🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>",
        ]));
    }

    private function createProject(string $argument): string
    {
        $fields = $this->splitArgs($argument, 4);
        if ($fields === [] || ! filled($fields[0] ?? null)) {
            return 'Uso: <code>/projeto add Nome|info?|categoria?|status?</code>';
        }

        [$name, $information, $category, $statusRaw] = array_pad($fields, 4, null);
        $status = ProjectStatus::tryFrom(Str::lower(trim((string) ($statusRaw ?: 'active'))))
            ?? ProjectStatus::Active;

        $project = Project::query()->create([
            'name' => trim((string) $name),
            'information' => filled($information) ? trim((string) $information) : null,
            'category' => filled($category) ? trim((string) $category) : null,
            'status' => $status,
            'is_public' => false,
            'repo_is_private' => true,
            'sort_order' => 0,
        ]);

        $url = route('admin.projects.edit', $project);

        return "✅ Projeto <b>#{$project->id}</b> {$this->escape($project->name)}\n🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>";
    }

    private function updateProject(string $argument): string
    {
        $fields = $this->splitArgs($argument, 5);
        if (count($fields) < 2) {
            return 'Uso: <code>/projeto set ID|Nome|info|categoria|status</code> (use <code>.</code> para manter)';
        }

        [$id, $name, $information, $category, $statusRaw] = array_pad($fields, 5, null);
        if (! ctype_digit(trim((string) $id))) {
            return 'ID inválido.';
        }

        $item = Project::query()->find((int) $id);
        if (! $item) {
            return 'Projeto não encontrado.';
        }

        $data = [];
        if (! $this->keep($name) && filled($name)) {
            $data['name'] = trim((string) $name);
        }
        if (! $this->keep($information)) {
            $data['information'] = filled($information) ? trim((string) $information) : null;
        }
        if (! $this->keep($category)) {
            $data['category'] = filled($category) ? trim((string) $category) : null;
        }
        if (! $this->keep($statusRaw) && filled($statusRaw)) {
            $status = ProjectStatus::tryFrom(Str::lower(trim((string) $statusRaw)));
            if (! $status) {
                return 'Status inválido (active, paused, done).';
            }
            $data['status'] = $status;
        }

        if ($data === []) {
            return 'Nada para atualizar.';
        }

        $item->update($data);

        return '✏️ Projeto atualizado.'."\n".$this->showProject((string) $item->id);
    }

    private function deleteProject(string $argument): string
    {
        [$id, $confirm] = $this->parseDelete($argument);
        if ($id === null) {
            return 'Uso: <code>/projeto del ID ok</code>';
        }
        if (! $confirm) {
            return "Para apagar o projeto <b>#{$id}</b>, confirme:\n<code>/projeto del {$id} ok</code>";
        }

        $item = Project::query()->find($id);
        if (! $item) {
            return 'Projeto não encontrado.';
        }

        $label = $item->name;
        $item->delete();

        return "🗑️ Projeto <b>#{$id}</b> {$this->escape($label)} removido.";
    }

    // -------------------------------------------------------------------------
    // Tarefas
    // -------------------------------------------------------------------------

    private function handleTask(string $argument): string
    {
        if ($argument === '') {
            return "Uso:\n<code>/tarefas</code>\n<code>/tarefa ID</code>\n<code>/tarefa add Título|projeto?|contato?|prio?|status?</code>\n<code>/tarefa set ID|Título|projeto|contato|prio|status</code>\n<code>/tarefa del ID ok</code>";
        }

        [$action, $rest] = $this->parseAction($argument);

        return match ($action) {
            'list', 'lista' => $this->listTasks($rest),
            'add', 'novo', 'create', 'criar' => $this->createTask($rest),
            'set', 'edit', 'update', 'editar' => $this->updateTask($rest),
            'del', 'delete', 'rm', 'apagar', 'remover' => $this->deleteTask($rest),
            'get', 'ver', 'show' => $this->showTask($rest),
            default => ctype_digit($action) && $rest === ''
                ? $this->showTask($action)
                : (str_contains($argument, '|')
                    ? $this->createTask($argument)
                    : $this->showTask($argument)),
        };
    }

    private function listTasks(string $argument): string
    {
        $limit = $this->listLimit($argument);
        $items = Task::query()->with(['project', 'contact'])->latest('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            return 'Nenhuma tarefa encontrada.';
        }

        $lines = ["✅ <b>Tarefas</b> (últimas {$items->count()})", ''];
        foreach ($items as $item) {
            $project = $item->project ? ' · P#'.$item->project->id : '';
            $lines[] = "#{$item->id} · {$this->escape($item->title)} · {$item->status->value}/{$item->priority->value}{$project}";
        }
        $lines[] = '';
        $lines[] = 'Detalhe: <code>/tarefa ID</code>';

        return implode("\n", $lines);
    }

    private function showTask(string $ref): string
    {
        if (! ctype_digit(trim($ref))) {
            return 'Informe o ID numérico da tarefa.';
        }

        $item = Task::query()->with(['project', 'contact'])->find((int) $ref);
        if (! $item) {
            return 'Tarefa não encontrada.';
        }

        $url = route('admin.tasks.index');

        return implode("\n", array_filter([
            "✅ <b>Tarefa #{$item->id}</b>",
            '<b>Título:</b> '.$this->escape($item->title),
            '<b>Status:</b> '.$item->status->value,
            '<b>Prioridade:</b> '.$item->priority->value,
            $item->project ? '<b>Projeto:</b> #'.$item->project->id.' '.$this->escape($item->project->name) : null,
            $item->contact ? '<b>Contato:</b> #'.$item->contact->id.' '.$this->escape($item->contact->name) : null,
            "🔗 <a href=\"{$this->escape($url)}\">Abrir tarefas</a>",
        ]));
    }

    private function createTask(string $argument): string
    {
        $fields = $this->splitArgs($argument, 5);
        if ($fields === [] || ! filled($fields[0] ?? null)) {
            return 'Uso: <code>/tarefa add Título|projeto_id?|contato?|prioridade?|status?</code>';
        }

        [$title, $projectRef, $contactRef, $priorityRaw, $statusRaw] = array_pad($fields, 5, null);

        $projectId = $this->resolveProjectId($projectRef, allowEmpty: true);
        if ($projectId === false) {
            return 'Projeto não encontrado.';
        }

        $contactId = null;
        if (filled($contactRef) && ! $this->keep($contactRef)) {
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

    private function updateTask(string $argument): string
    {
        $fields = $this->splitArgs($argument, 6);
        if (count($fields) < 2) {
            return 'Uso: <code>/tarefa set ID|Título|projeto|contato|prio|status</code> (use <code>.</code> para manter)';
        }

        [$id, $title, $projectRef, $contactRef, $priorityRaw, $statusRaw] = array_pad($fields, 6, null);
        if (! ctype_digit(trim((string) $id))) {
            return 'ID inválido.';
        }

        $item = Task::query()->find((int) $id);
        if (! $item) {
            return 'Tarefa não encontrada.';
        }

        $data = [];
        if (! $this->keep($title) && filled($title)) {
            $data['title'] = trim((string) $title);
        }
        if (! $this->keep($projectRef)) {
            $projectId = $this->resolveProjectId($projectRef, allowEmpty: true);
            if ($projectId === false) {
                return 'Projeto não encontrado.';
            }
            $data['project_id'] = $projectId;
        }
        if (! $this->keep($contactRef)) {
            if (! filled($contactRef)) {
                $data['contact_id'] = null;
            } else {
                $contact = $this->resolveContact((string) $contactRef);
                if (! $contact) {
                    return 'Contato não encontrado.';
                }
                $data['contact_id'] = $contact->id;
            }
        }
        if (! $this->keep($priorityRaw) && filled($priorityRaw)) {
            $priority = TaskPriority::tryFrom(Str::lower(trim((string) $priorityRaw)));
            if (! $priority) {
                return 'Prioridade inválida (low, medium, high).';
            }
            $data['priority'] = $priority;
        }
        if (! $this->keep($statusRaw) && filled($statusRaw)) {
            $status = TaskStatus::tryFrom(Str::lower(trim((string) $statusRaw)));
            if (! $status) {
                return 'Status inválido (todo, doing, done).';
            }
            $data['status'] = $status;
        }

        if ($data === []) {
            return 'Nada para atualizar.';
        }

        $item->update($data);

        return '✏️ Tarefa atualizada.'."\n".$this->showTask((string) $item->id);
    }

    private function deleteTask(string $argument): string
    {
        [$id, $confirm] = $this->parseDelete($argument);
        if ($id === null) {
            return 'Uso: <code>/tarefa del ID ok</code>';
        }
        if (! $confirm) {
            return "Para apagar a tarefa <b>#{$id}</b>, confirme:\n<code>/tarefa del {$id} ok</code>";
        }

        $item = Task::query()->find($id);
        if (! $item) {
            return 'Tarefa não encontrada.';
        }

        $label = $item->title;
        $item->delete();

        return "🗑️ Tarefa <b>#{$id}</b> {$this->escape($label)} removida.";
    }

    // -------------------------------------------------------------------------
    // Mensagens
    // -------------------------------------------------------------------------

    private function handleMessage(string $argument): string
    {
        if ($argument === '') {
            return "Uso:\n<code>/mensagens</code>\n<code>/mensagem ID</code>\n<code>/mensagem lida ID</code>\n<code>/mensagem del ID ok</code>";
        }

        [$action, $rest] = $this->parseAction($argument);

        return match ($action) {
            'list', 'lista' => $this->listMessages($rest),
            'lida', 'read', 'ler' => $this->markMessageRead($rest),
            'del', 'delete', 'rm', 'apagar', 'remover' => $this->deleteMessage($rest),
            'get', 'ver', 'show' => $this->showMessage($rest),
            default => ctype_digit($action) && $rest === ''
                ? $this->showMessage($action)
                : $this->showMessage($argument),
        };
    }

    private function listMessages(string $argument): string
    {
        $limit = $this->listLimit($argument);
        $items = ContactMessage::query()->latest('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            return 'Nenhuma mensagem encontrada.';
        }

        $unread = ContactMessage::query()->unread()->count();
        $lines = ["✉️ <b>Mensagens</b> (últimas {$items->count()} · {$unread} não lidas)", ''];
        foreach ($items as $item) {
            $flag = $item->isUnread() ? '🔴' : '⚪';
            $lines[] = "{$flag} #{$item->id} · {$this->escape($item->name)} · {$this->escape(Str::limit($item->subject, 40))}";
        }
        $lines[] = '';
        $lines[] = 'Detalhe: <code>/mensagem ID</code> · Marcar lida: <code>/mensagem lida ID</code>';

        return implode("\n", $lines);
    }

    private function showMessage(string $ref): string
    {
        if (! ctype_digit(trim($ref))) {
            return 'Informe o ID numérico da mensagem.';
        }

        $item = ContactMessage::query()->find((int) $ref);
        if (! $item) {
            return 'Mensagem não encontrada.';
        }

        $url = route('admin.messages.show', $item);
        $body = $this->escape(Str::limit($item->message, 1200));

        return implode("\n", array_filter([
            "✉️ <b>Mensagem #{$item->id}</b> ".($item->isUnread() ? '(não lida)' : '(lida)'),
            '<b>De:</b> '.$this->escape($item->name),
            '<b>E-mail:</b> '.$this->escape($item->email),
            $item->phone ? '<b>Telefone:</b> '.$this->escape($item->phone) : null,
            '<b>Assunto:</b> '.$this->escape($item->subject),
            '',
            $body,
            '',
            "🔗 <a href=\"{$this->escape($url)}\">Abrir no admin</a>",
        ]));
    }

    private function markMessageRead(string $ref): string
    {
        if (! ctype_digit(trim($ref))) {
            return 'Uso: <code>/mensagem lida ID</code>';
        }

        $item = ContactMessage::query()->find((int) $ref);
        if (! $item) {
            return 'Mensagem não encontrada.';
        }

        $item->markAsRead();

        return "✔️ Mensagem <b>#{$item->id}</b> marcada como lida.";
    }

    private function deleteMessage(string $argument): string
    {
        [$id, $confirm] = $this->parseDelete($argument);
        if ($id === null) {
            return 'Uso: <code>/mensagem del ID ok</code>';
        }
        if (! $confirm) {
            return "Para apagar a mensagem <b>#{$id}</b>, confirme:\n<code>/mensagem del {$id} ok</code>";
        }

        $item = ContactMessage::query()->find($id);
        if (! $item) {
            return 'Mensagem não encontrada.';
        }

        $item->delete();

        return "🗑️ Mensagem <b>#{$id}</b> removida.";
    }

    // -------------------------------------------------------------------------
    // Status + helpers
    // -------------------------------------------------------------------------

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
            '',
            'Listas: <code>/contatos</code> · <code>/oportunidades</code> · <code>/projetos</code> · <code>/tarefas</code> · <code>/mensagens</code>',
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

    /** @return int|null|false null = sem projeto; false = inválido */
    private function resolveProjectId(mixed $projectRef, bool $allowEmpty = false): int|null|false
    {
        if ($this->keep($projectRef) || ! filled($projectRef)) {
            return $allowEmpty ? null : false;
        }

        $ref = trim((string) $projectRef);
        if (! ctype_digit($ref)) {
            return false;
        }

        $id = Project::query()->whereKey((int) $ref)->value('id');

        return $id ? (int) $id : false;
    }

    /** @return array{0: string, 1: string} */
    private function parseAction(string $argument): array
    {
        $parts = preg_split('/\s+/', trim($argument), 2) ?: [];
        $action = Str::lower((string) ($parts[0] ?? ''));
        $rest = trim((string) ($parts[1] ?? ''));

        return [$action, $rest];
    }

    /** @return array{0: int|null, 1: bool} */
    private function parseDelete(string $argument): array
    {
        $parts = preg_split('/\s+/', trim($argument)) ?: [];
        $idPart = $parts[0] ?? '';
        $confirmPart = Str::lower((string) ($parts[1] ?? ''));

        if (! ctype_digit($idPart)) {
            return [null, false];
        }

        return [(int) $idPart, in_array($confirmPart, ['ok', 'confirm', 'sim', 'yes'], true)];
    }

    private function listLimit(string $argument): int
    {
        $n = (int) trim($argument);
        if ($n <= 0) {
            return self::LIST_DEFAULT;
        }

        return min($n, self::LIST_MAX);
    }

    private function keep(mixed $value): bool
    {
        return trim((string) $value) === '.';
    }

    private function parseMoney(mixed $valueRaw): ?float
    {
        if (! filled($valueRaw) || $this->keep($valueRaw)) {
            return null;
        }

        $normalized = str_replace(['R$', ' '], '', (string) $valueRaw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    /** @return list<string> */
    private function splitArgs(string $argument, int $max): array
    {
        if (trim($argument) === '') {
            return [];
        }

        $parts = array_map('trim', explode('|', $argument, $max));

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
