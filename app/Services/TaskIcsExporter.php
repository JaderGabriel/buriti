<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TaskIcsExporter
{
    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function export(Collection $tasks, string $calendarName = 'BURI-TI Agenda'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BURI-TI//Agenda//PT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText($calendarName),
            'X-WR-TIMEZONE:'.(config('app.timezone') ?: 'UTC'),
        ];

        foreach ($tasks as $task) {
            if (! $task->due_at) {
                continue;
            }

            $lines = array_merge($lines, $this->eventLines($task));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /** @return list<string> */
    private function eventLines(Task $task): array
    {
        $start = $task->due_at->copy();
        $end = $start->copy()->addHour();
        $stamp = now('UTC');
        $uid = 'task-'.$task->id.'@buriti.dev.br';

        $description = trim((string) ($task->description ?? ''));
        if ($task->meet_url) {
            $description = trim($description."\n\nMeet: ".$task->meet_url);
        }

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$this->formatUtc($stamp),
            'DTSTART:'.$this->formatLocal($start),
            'DTEND:'.$this->formatLocal($end),
            'SUMMARY:'.$this->escapeText($task->title),
        ];

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escapeText($description);
        }

        if ($task->meet_url) {
            $lines[] = 'URL:'.$this->escapeText($task->meet_url);
            $lines[] = 'LOCATION:'.$this->escapeText($task->meet_url);
        }

        $status = $task->status === \App\Enums\TaskStatus::Done ? 'COMPLETED' : 'CONFIRMED';
        $lines[] = 'STATUS:'.$status;
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function formatUtc(Carbon $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private function formatLocal(Carbon $date): string
    {
        return $date->format('Ymd\THis');
    }

    private function escapeText(string $value): string
    {
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
        $value = addcslashes($value, ',;\\');

        return Str::limit($value, 700, '...');
    }
}
