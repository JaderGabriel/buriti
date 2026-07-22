<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCalendarService
{
    public function __construct(private SettingService $settings) {}

    /** @return array{embed: bool, deep_link: bool, api: bool, level: int, label: string, next_step: string} */
    public function integrationStatus(): array
    {
        $embed = filled($this->settings->calendarSrc());
        $deepLink = filled($this->settings->get('google_calendar_url'));
        $api = $this->apiConfigured();

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

        $next = match ($level) {
            0 => 'Cole o embed da Agenda e a URL em Configurações.',
            1 => 'Publique a agenda (embed) e use Meet em cada tarefa.',
            2 => 'Configure GOOGLE_CLIENT_ID, SECRET e REFRESH_TOKEN no .env para sync automático.',
            default => 'Integração completa: eventos e Meet são criados pela API.',
        };

        return [
            'embed' => $embed,
            'deep_link' => $deepLink,
            'api' => $api,
            'level' => $level,
            'label' => $labels[$level],
            'next_step' => $next,
        ];
    }

    public function apiConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.refresh_token'));
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
     * Sync task to Google Calendar. When API is configured, creates/updates an event with Meet.
     * Otherwise returns a deep-link payload for the browser.
     *
     * @return array{mode: string, url?: string, event_id?: string, meet_url?: string, message: string}
     */
    public function syncTask(Task $task): array
    {
        if (! $this->apiConfigured()) {
            return [
                'mode' => 'redirect',
                'url' => $this->createEventUrl($task, (bool) $task->want_meet),
                'message' => 'Abra o Google Agenda para concluir o evento'.($task->want_meet ? ' com Meet' : '').'.',
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

            if ($task->google_event_id) {
                $response = Http::withHeaders($headers)
                    ->put(
                        'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.$task->google_event_id.'?conferenceDataVersion=1',
                        $payload
                    )
                    ->throw()
                    ->json();
            } else {
                $response = Http::withHeaders($headers)
                    ->post(
                        'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events?conferenceDataVersion=1',
                        $payload
                    )
                    ->throw()
                    ->json();
            }

            $meetUrl = $response['hangoutLink']
                ?? data_get($response, 'conferenceData.entryPoints.0.uri')
                ?? $task->meet_url;

            $task->forceFill([
                'google_event_id' => $response['id'] ?? $task->google_event_id,
                'meet_url' => $meetUrl,
            ])->save();

            return [
                'mode' => 'api',
                'event_id' => $task->google_event_id,
                'meet_url' => $task->meet_url,
                'message' => 'Evento sincronizado no Google Agenda'.($meetUrl ? ' com Meet' : '').'.',
            ];
        } catch (RequestException|RuntimeException $e) {
            Log::warning('Google Calendar sync failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);

            return [
                'mode' => 'redirect',
                'url' => $this->createEventUrl($task, (bool) $task->want_meet),
                'message' => 'Falha na API Google — use o atalho da Agenda. '.$e->getMessage(),
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

    /** @return array<string, mixed> */
    private function eventPayload(Task $task): array
    {
        $start = ($task->due_at ?? now()->addDay())->copy()->startOfHour();
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

        if ($task->want_meet && blank($task->meet_url) && blank($task->google_event_id)) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'buriti-task-'.$task->id.'-'.uniqid(),
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

    private function accessToken(): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => config('services.google.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException('Não foi possível obter access token Google.');
        }

        return (string) $response->json('access_token');
    }
}
