<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Http\Client\RequestException;
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

    /** @return array{embed: bool, deep_link: bool, api: bool, level: int, label: string, next_step: string} */
    public function integrationStatus(): array
    {
        $embed = filled($this->settings->calendarSrc());
        $deepLink = filled($this->settings->get('google_calendar_url'));
        $api = $this->apiConfigured();
        $oauthApp = $this->oauthAppConfigured();

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
            $api => 'Integração completa: eventos e Meet são criados no CRM pela API.',
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
     * Exchange authorization code for tokens and persist the refresh token.
     *
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
     * Sync task to Google Calendar via API. Never forces a browser redirect —
     * callers stay in the CRM and receive mode api|error|skipped.
     *
     * @return array{mode: string, url?: string, event_id?: string, meet_url?: string, message: string}
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
            $calendarId = $this->settings->get('google_calendar_id') ?: 'primary';
            $payload = $this->eventPayload($task);
            $headers = [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ];

            $endpoint = 'https://www.googleapis.com/calendar/v3/calendars/'
                .rawurlencode($calendarId)
                .'/events';

            if ($task->google_event_id) {
                $response = Http::withHeaders($headers)
                    ->put(
                        $endpoint.'/'.$task->google_event_id.'?conferenceDataVersion=1',
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

            $task->forceFill([
                'google_event_id' => $response['id'] ?? $task->google_event_id,
                'meet_url' => $meetUrl,
            ])->save();

            $parts = ['Evento sincronizado no Google Agenda'];
            if ($meetUrl) {
                $parts[] = 'Meet gerado e guardado no CRM';
            }

            return [
                'mode' => 'api',
                'event_id' => $task->google_event_id,
                'meet_url' => $task->meet_url,
                'message' => implode('. ', $parts).'.',
            ];
        } catch (RequestException|RuntimeException $e) {
            Log::warning('Google Calendar sync failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);

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
            $calendarId = $this->settings->get('google_calendar_id') ?: 'primary';
            Http::withHeaders(['Authorization' => 'Bearer '.$this->accessToken()])
                ->delete('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.$task->google_event_id)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar delete failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    public function clientId(): ?string
    {
        return $this->settings->get('google_client_id')
            ?: (filled(config('services.google.client_id')) ? (string) config('services.google.client_id') : null);
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
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $this->refreshToken(),
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException('Não foi possível obter access token Google. Religue a conta em Configurações.');
        }

        return (string) $response->json('access_token');
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

        if (str_contains($raw, 'access_denied') || str_contains($raw, '403')) {
            return 'Acesso negado — confirme scopes Calendar e utilizadores de teste.';
        }

        return Str::limit($raw, 160);
    }
}
