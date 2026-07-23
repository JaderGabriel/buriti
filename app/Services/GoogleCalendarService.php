<?php

namespace App\Services;

use App\Data\GoogleCalendarEvent;
use App\Enums\GoogleEventColor;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCalendarService
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function __construct(private SettingService $settings) {}

    /**
     * @return array{
     *     embed: bool,
     *     deep_link: bool,
     *     api: bool,
     *     oauth_app: bool,
     *     level: int,
     *     label: string,
     *     next_step: string,
     *     calendar_id: string,
     *     calendar_matches_embed: bool|null
     * }
     */
    public function integrationStatus(): array
    {
        $embed = filled($this->settings->calendarSrc());
        $deepLink = filled($this->settings->get('google_calendar_url'));
        $api = $this->apiConfigured();
        $oauthApp = $this->oauthAppConfigured();
        $calendarId = $this->resolvedCalendarId();
        $embedCalendarId = $this->calendarIdFromEmbed($this->settings->calendarSrc());

        $level = match (true) {
            $api => 3,
            $embed && $deepLink => 2,
            $embed || $deepLink => 1,
            default => 0,
        };

        $labels = [
            0 => 'Não configurado',
            1 => 'Básico — atalhos Google',
            2 => 'Operacional — Agenda embutida + Meet',
            3 => 'Total — API Calendar + Meet automático',
        ];

        $next = match (true) {
            $api => 'Eventos vão para a agenda «'.$calendarId.'» com cores Google e Meet no CRM.',
            $oauthApp => 'Clique em «Ligar conta Google» para autorizar a Agenda e o Meet.',
            $level >= 2 => 'Preencha Client ID e Secret (abaixo ou .env) e ligue a conta Google.',
            $level === 1 => 'Publique a agenda (embed) e configure a API OAuth para sync no CRM.',
            default => 'Cole o embed da Agenda e a URL em Configurações.',
        };

        return [
            'embed' => $embed,
            'deep_link' => $deepLink,
            'api' => $api,
            'oauth_app' => $oauthApp,
            'level' => $level,
            'label' => $labels[$level],
            'next_step' => $next,
            'calendar_id' => $calendarId,
            'calendar_matches_embed' => $embedCalendarId === null
                ? null
                : $this->calendarIdsMatch($calendarId, $embedCalendarId),
        ];
    }

    public function oauthAppConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function apiConfigured(): bool
    {
        return $this->oauthAppConfigured() && filled($this->refreshToken());
    }

    /**
     * Honest connection state for the settings UI.
     *
     * @return array{state: string, label: string, message: string, has_client_id: bool, has_secret: bool, has_refresh: bool}
     */
    public function connectionStatus(): array
    {
        $hasClientId = filled($this->clientId());
        $hasSecret = filled($this->clientSecret());
        $hasRefresh = filled($this->refreshToken());

        return match (true) {
            $hasClientId && $hasSecret && $hasRefresh => [
                'state' => 'linked',
                'label' => 'Conta Google ligada',
                'message' => 'Refresh token presente. Use «Testar ligação» para validar o access token.',
                'has_client_id' => true,
                'has_secret' => true,
                'has_refresh' => true,
            ],
            $hasClientId && $hasSecret && ! $hasRefresh => [
                'state' => 'ready_to_link',
                'label' => 'Pronto para ligar',
                'message' => 'Client ID e Secret ok — falta clicar em «Ligar conta Google» para gravar o refresh token.',
                'has_client_id' => true,
                'has_secret' => true,
                'has_refresh' => false,
            ],
            $hasClientId && ! $hasSecret => [
                'state' => 'missing_secret',
                'label' => 'Falta o Client Secret',
                'message' => 'O Client ID está guardado, mas o Secret não. Cole o Secret, salve e depois ligue a conta.',
                'has_client_id' => true,
                'has_secret' => false,
                'has_refresh' => $hasRefresh,
            ],
            default => [
                'state' => 'missing_credentials',
                'label' => 'Credenciais em falta',
                'message' => 'Preencha Client ID e Client Secret do Cloud Console e salve.',
                'has_client_id' => $hasClientId,
                'has_secret' => $hasSecret,
                'has_refresh' => $hasRefresh,
            ],
        };
    }

    /**
     * @return array{ok: bool, message: string, error?: string}
     */
    public function testConnection(): array
    {
        $status = $this->connectionStatus();
        if ($status['state'] !== 'linked') {
            return [
                'ok' => false,
                'message' => $status['message'],
                'error' => $status['state'],
            ];
        }

        try {
            $this->accessToken();

            return [
                'ok' => true,
                'message' => 'Ligação OK — access token obtido. Eventos serão criados na agenda «'.$this->shortCalendarLabel($this->resolvedCalendarId()).'».',
            ];
        } catch (RuntimeException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'error' => 'token_failed',
            ];
        }
    }

    public function sanitizeClientId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES));
        // Avoid leftover dots/spaces from copy-paste or placeholder confusion.
        $value = preg_replace('/^[\s.]+/', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"'");

        return $value !== '' ? $value : null;
    }

    /** Calendar ID used by the API (settings, else embed src, else primary). */
    public function resolvedCalendarId(): string
    {
        $fromSettings = $this->settings->normalizeCalendarId($this->settings->get('google_calendar_id'));
        if (filled($fromSettings)) {
            return $fromSettings;
        }

        $fromEmbed = $this->calendarIdFromEmbed($this->settings->calendarSrc());
        if (filled($fromEmbed)) {
            return $fromEmbed;
        }

        return 'primary';
    }

    public function calendarIdForTask(Task $task): string
    {
        $stored = $this->settings->normalizeCalendarId($task->google_calendar_id);
        if (filled($stored)) {
            return $stored;
        }

        return $this->resolvedCalendarId();
    }

    /**
     * Writable calendars from the linked Google account.
     *
     * @return list<array{id: string, summary: string, primary: bool, selected: bool}>
     */
    public function listWritableCalendars(): array
    {
        if (! $this->apiConfigured()) {
            return [];
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
                    'minAccessRole' => 'writer',
                ])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('Google calendarList failed', ['error' => $e->getMessage()]);

            return [];
        }

        $items = $response['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $current = $this->resolvedCalendarId();
        $calendars = [];

        foreach ($items as $item) {
            if (! is_array($item) || blank($item['id'] ?? null)) {
                continue;
            }

            $id = (string) $item['id'];
            $calendars[] = [
                'id' => $id,
                'summary' => (string) ($item['summary'] ?? $id),
                'primary' => (bool) ($item['primary'] ?? false),
                'selected' => $this->calendarIdsMatch($id, $current),
            ];
        }

        usort($calendars, function (array $a, array $b): int {
            if ($a['selected'] !== $b['selected']) {
                return $a['selected'] ? -1 : 1;
            }
            if ($a['primary'] !== $b['primary']) {
                return $a['primary'] ? -1 : 1;
            }

            return strcasecmp($a['summary'], $b['summary']);
        });

        return $calendars;
    }

    public function redirectUri(): string
    {
        $configured = config('services.google.redirect_uri');

        if (filled($configured)) {
            return (string) $configured;
        }

        return rtrim((string) config('app.url'), '/').'/admin/google/callback';
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return self::SCOPES;
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->oauthAppConfigured()) {
            throw new RuntimeException('Configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET antes de ligar a conta.');
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * @return array{refresh_token?: string, access_token?: string}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao trocar o código OAuth: '.$response->body());
        }

        $payload = $response->json() ?? [];
        $refresh = $payload['refresh_token'] ?? null;

        if (filled($refresh)) {
            $this->settings->putSecret('google_refresh_token', (string) $refresh);
        } elseif (! filled($this->refreshToken())) {
            throw new RuntimeException(
                'Google não devolveu refresh token. Revogue o acesso da app em myaccount.google.com/permissions e tente novamente.'
            );
        }

        return $payload;
    }

    public function disconnect(): void
    {
        $this->settings->forgetSecret('google_refresh_token');
    }

    public function instantMeetUrl(): string
    {
        return 'https://meet.google.com/new';
    }

    public function calendarHomeUrl(): string
    {
        return $this->settings->get('google_calendar_url')
            ?: (string) config('buriti.google_calendar_url');
    }

    public function createEventUrl(Task $task, bool $withMeet = true): string
    {
        $start = ($task->due_at ?? now()->addDay())->copy()->startOfHour();
        $end = $start->copy()->addHour();

        $details = trim((string) ($task->description ?? ''));
        if ($withMeet) {
            $details = trim($details."\n\nGoogle Meet: adicione videoconferência neste evento (ou cole o link Meet na tarefa).");
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => $task->title,
            'details' => $details,
            'dates' => $start->utc()->format('Ymd\THis\Z').'/'.$end->utc()->format('Ymd\THis\Z'),
        ];

        if ($withMeet) {
            $params['location'] = 'Google Meet';
        }

        if (filled($task->meet_url)) {
            $params['details'] = trim(($task->description ?? '')."\n\nMeet: ".$task->meet_url);
            $params['location'] = $task->meet_url;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    /**
     * @return array{mode: string, url?: string, event_id?: string, meet_url?: string, calendar_id?: string, message: string}
     */
    public function syncTask(Task $task): array
    {
        if (! $this->apiConfigured()) {
            return [
                'mode' => 'error',
                'url' => $this->createEventUrl($task, (bool) $task->want_meet),
                'message' => $this->oauthAppConfigured()
                    ? 'Conta Google ainda não ligada. Vá a Configurações → Integração Google e clique em «Ligar conta Google».'
                    : 'API Google incompleta. Preencha Client ID/Secret e ligue a conta em Configurações.',
            ];
        }

        try {
            $accessToken = $this->accessToken();
            $calendarId = $this->resolvedCalendarId();
            $payload = $this->eventPayload($task);
            $headers = [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ];

            $endpoint = 'https://www.googleapis.com/calendar/v3/calendars/'
                .rawurlencode($calendarId)
                .'/events';

            $eventId = $task->google_event_id;
            if ($eventId && filled($task->google_calendar_id)
                && ! $this->calendarIdsMatch((string) $task->google_calendar_id, $calendarId)) {
                $eventId = null;
            }

            if ($eventId) {
                $response = Http::withHeaders($headers)
                    ->put(
                        $endpoint.'/'.$eventId.'?conferenceDataVersion=1',
                        $payload
                    )
                    ->throw()
                    ->json();
            } else {
                $response = Http::withHeaders($headers)
                    ->post($endpoint.'?conferenceDataVersion=1', $payload)
                    ->throw()
                    ->json();
            }

            $meetUrl = $this->extractMeetUrl($response) ?? $task->meet_url;
            $colorId = isset($response['colorId'])
                ? (string) $response['colorId']
                : $task->googleColor()?->value;

            $task->forceFill([
                'google_event_id' => $response['id'] ?? $task->google_event_id,
                'google_calendar_id' => $calendarId,
                'meet_url' => $meetUrl,
                'google_color_id' => $colorId,
            ])->save();

            $parts = ['Evento sincronizado na agenda «'.$this->shortCalendarLabel($calendarId).'»'];
            if ($meetUrl) {
                $parts[] = 'Meet gerado e guardado no CRM';
            }
            if (filled($colorId) && ($color = GoogleEventColor::tryFromMixed($colorId))) {
                $parts[] = 'cor '.$color->label();
            }

            return [
                'mode' => 'api',
                'event_id' => $task->google_event_id,
                'meet_url' => $task->meet_url,
                'calendar_id' => $calendarId,
                'message' => implode('. ', $parts).'.',
            ];
        } catch (RequestException|RuntimeException $e) {
            Log::warning('Google Calendar sync failed', [
                'task_id' => $task->id,
                'calendar_id' => $this->resolvedCalendarId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'mode' => 'error',
                'url' => $this->createEventUrl($task, (bool) $task->want_meet),
                'message' => 'Não foi possível sincronizar pela API. A tarefa ficou no CRM. '.$this->friendlyError($e->getMessage()),
            ];
        }
    }

    public function deleteRemoteEvent(Task $task): void
    {
        if (! $this->apiConfigured() || blank($task->google_event_id)) {
            return;
        }

        try {
            $calendarId = $this->calendarIdForTask($task);
            Http::withHeaders(['Authorization' => 'Bearer '.$this->accessToken()])
                ->delete('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.$task->google_event_id)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar delete failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    public function clientId(): ?string
    {
        $fromSettings = $this->sanitizeClientId($this->settings->get('google_client_id'));
        if (filled($fromSettings)) {
            return $fromSettings;
        }

        return $this->sanitizeClientId(
            filled(config('services.google.client_id')) ? (string) config('services.google.client_id') : null
        );
    }

    public function clientSecret(): ?string
    {
        $fromSettings = $this->settings->getSecret('google_client_secret');
        if (filled($fromSettings)) {
            return $fromSettings;
        }

        return filled(config('services.google.client_secret'))
            ? (string) config('services.google.client_secret')
            : null;
    }

    public function refreshToken(): ?string
    {
        $fromSettings = $this->settings->getSecret('google_refresh_token');
        if (filled($fromSettings)) {
            return $fromSettings;
        }

        return filled(config('services.google.refresh_token'))
            ? (string) config('services.google.refresh_token')
            : null;
    }

    public function calendarIdFromEmbed(?string $embedSrc): ?string
    {
        return $this->settings->normalizeCalendarId($embedSrc);
    }

    public function calendarIdsMatch(string $a, string $b): bool
    {
        return strcasecmp(rawurldecode($a), rawurldecode($b)) === 0;
    }

    public function shortCalendarLabel(string $calendarId): string
    {
        if ($calendarId === 'primary') {
            return 'primary';
        }

        if (str_contains($calendarId, '@group.calendar.google.com')) {
            return Str::before($calendarId, '@').'@group…';
        }

        return Str::limit($calendarId, 42, '…');
    }

    /**
     * Pull events from the configured Google Calendar for the CRM agenda overlay.
     *
     * @param  list<string>  $excludeEventIds  CRM-synced google_event_id values to skip (avoid duplicates)
     * @return array{events: \Illuminate\Support\Collection<int, \App\Data\GoogleCalendarEvent>, error: string|null}
     */
    public function listEvents(Carbon $from, Carbon $to, array $excludeEventIds = []): array
    {
        if (! $this->apiConfigured()) {
            return [
                'events' => collect(),
                'error' => null,
            ];
        }

        $calendarId = $this->resolvedCalendarId();
        $exclude = array_fill_keys(array_filter($excludeEventIds), true);
        $cacheKey = 'buriti.gcal.events.'.sha1($calendarId.'|'.$from->toIso8601String().'|'.$to->toIso8601String());

        try {
            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($calendarId, $from, $to): array {
                $token = $this->accessToken();
                $items = [];
                $pageToken = null;

                do {
                    $query = [
                        'timeMin' => $from->copy()->utc()->toIso8601String(),
                        'timeMax' => $to->copy()->utc()->toIso8601String(),
                        'singleEvents' => 'true',
                        'orderBy' => 'startTime',
                        'maxResults' => 250,
                    ];
                    if (is_string($pageToken) && $pageToken !== '') {
                        $query['pageToken'] = $pageToken;
                    }

                    $response = Http::withToken($token)
                        ->get(
                            'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events',
                            $query
                        )
                        ->throw()
                        ->json();

                    foreach ((array) ($response['items'] ?? []) as $item) {
                        if (is_array($item)) {
                            $items[] = $item;
                        }
                    }

                    $pageToken = $response['nextPageToken'] ?? null;
                } while (is_string($pageToken) && $pageToken !== '' && count($items) < 500);

                return $items;
            });
        } catch (RequestException|RuntimeException $e) {
            Log::warning('Google Calendar listEvents failed', [
                'calendar_id' => $calendarId,
                'error' => $e->getMessage(),
            ]);

            return [
                'events' => collect(),
                'error' => 'Não foi possível carregar eventos do Google Agenda. '.$this->friendlyError($e->getMessage()),
            ];
        }

        $events = collect($rawItems)
            ->map(fn (array $item) => GoogleCalendarEvent::fromGooglePayload($item))
            ->filter()
            ->reject(fn (GoogleCalendarEvent $event) => isset($exclude[$event->id]))
            ->values();

        return [
            'events' => $events,
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function eventPayload(Task $task): array
    {
        $start = $task->due_at?->copy() ?? now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        $payload = [
            'summary' => $task->title,
            'description' => $task->description,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
        ];

        $color = $task->googleColor();
        if ($color) {
            $payload['colorId'] = $color->value;
        }

        $needsMeet = $task->want_meet && blank($task->meet_url);
        if ($needsMeet) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'buriti-task-'.$task->id.'-'.Str::lower(Str::random(10)),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        if (filled($task->meet_url)) {
            $payload['location'] = $task->meet_url;
            $payload['description'] = trim(($task->description ?? '')."\n\nMeet: ".$task->meet_url);
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $response */
    private function extractMeetUrl(array $response): ?string
    {
        if (filled($response['hangoutLink'] ?? null)) {
            return (string) $response['hangoutLink'];
        }

        $entryPoints = data_get($response, 'conferenceData.entryPoints', []);
        if (! is_array($entryPoints)) {
            return null;
        }

        foreach ($entryPoints as $entry) {
            if (($entry['entryPointType'] ?? null) === 'video' && filled($entry['uri'] ?? null)) {
                return (string) $entry['uri'];
            }
        }

        foreach ($entryPoints as $entry) {
            if (filled($entry['uri'] ?? null) && str_contains((string) $entry['uri'], 'meet.google.com')) {
                return (string) $entry['uri'];
            }
        }

        return null;
    }

    private function accessToken(): string
    {
        if (! filled($this->refreshToken())) {
            throw new RuntimeException('Conta Google sem refresh token. Clique em «Ligar conta Google» em Configurações.');
        }

        if (! filled($this->clientId()) || ! filled($this->clientSecret())) {
            throw new RuntimeException('Client ID ou Secret em falta. Guarde as credenciais em Configurações.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $this->refreshToken(),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful() && filled($response->json('access_token'))) {
            return (string) $response->json('access_token');
        }

        $error = (string) ($response->json('error') ?? '');
        $description = (string) ($response->json('error_description') ?? '');

        Log::warning('Google access token failed', [
            'status' => $response->status(),
            'error' => $error,
            'error_description' => $description,
        ]);

        $hint = match ($error) {
            'invalid_client' => 'Client ID/Secret inválidos (confirme se o ID não tem ponto a mais no início).',
            'invalid_grant' => 'Refresh token inválido ou revogado — desligue e volte a «Ligar conta Google».',
            'unauthorized_client' => 'Este cliente OAuth não permite refresh token — use tipo «Aplicativo da Web».',
            default => 'Religue a conta em Configurações.',
        };

        throw new RuntimeException(trim('Não foi possível obter access token Google. '.$hint.($description !== '' ? ' ('.$description.')' : '')));
    }

    private function friendlyError(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, 'invalid_grant')) {
            return 'Token inválido — religue a conta Google.';
        }

        if (str_contains($raw, 'Not Found') || str_contains($raw, '404')) {
            return 'Agenda não encontrada — confira o Calendar ID (tem de coincidir com a agenda embutida).';
        }

        if (str_contains($raw, 'access_denied') || str_contains($raw, '403')) {
            return 'Acesso negado — confirme scopes Calendar e permissão de escrita na agenda.';
        }

        return Str::limit($raw, 160);
    }
}
