<?php

namespace App\Data;

use App\Enums\GoogleEventColor;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GoogleCalendarEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?Carbon $start,
        public readonly ?Carbon $end,
        public readonly bool $allDay,
        public readonly ?string $htmlLink,
        public readonly ?string $colorId,
        public readonly ?string $meetUrl,
        public readonly ?string $description,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromGooglePayload(array $payload): ?self
    {
        $id = $payload['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        $startRaw = $payload['start']['dateTime'] ?? $payload['start']['date'] ?? null;
        $endRaw = $payload['end']['dateTime'] ?? $payload['end']['date'] ?? null;
        $allDay = isset($payload['start']['date']) && ! isset($payload['start']['dateTime']);

        $start = is_string($startRaw) ? Carbon::parse($startRaw) : null;
        $end = is_string($endRaw) ? Carbon::parse($endRaw) : null;

        if ($start === null) {
            return null;
        }

        $meetUrl = $payload['hangoutLink'] ?? null;
        if (! is_string($meetUrl) || $meetUrl === '') {
            $meetUrl = null;
            foreach ((array) data_get($payload, 'conferenceData.entryPoints', []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $uri = $entry['uri'] ?? null;
                if (($entry['entryPointType'] ?? null) === 'video' && is_string($uri) && $uri !== '') {
                    $meetUrl = $uri;
                    break;
                }
            }
        }

        return new self(
            id: $id,
            title: (string) ($payload['summary'] ?? '(Sem título)'),
            start: $start,
            end: $end,
            allDay: $allDay,
            htmlLink: is_string($payload['htmlLink'] ?? null) ? $payload['htmlLink'] : null,
            colorId: isset($payload['colorId']) ? (string) $payload['colorId'] : null,
            meetUrl: $meetUrl,
            description: isset($payload['description']) ? Str::limit(strip_tags((string) $payload['description']), 240) : null,
        );
    }

    public function dateKey(): string
    {
        return $this->start?->format('Y-m-d') ?? '';
    }

    public function timeLabel(): ?string
    {
        if ($this->allDay || $this->start === null) {
            return null;
        }

        return $this->start->format('H:i');
    }

    public function googleColor(): ?GoogleEventColor
    {
        return GoogleEventColor::tryFromMixed($this->colorId);
    }
}
