<?php

namespace App\Services\Telegram;

use App\Enums\GoogleEventColor;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TaskTelegramFormatter
{
    /**
     * Card rico de agenda para detalhe / criação / actualização.
     */
    public function card(Task $task, ?string $headline = null): string
    {
        $task->loadMissing(['project', 'contact']);

        $color = $task->googleColor();
        $colorMark = $color?->telegramEmoji() ?? '📅';
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $url = route('admin.tasks.index', array_filter([
            'view' => 'agenda',
            'month' => $due?->format('Y-m'),
            'focus' => $task->id,
        ])).($task->id ? '#task-'.$task->id : '');

        $lines = [
            $headline ?: ($colorMark.' <b>Compromisso na agenda</b>'),
            '──────────────',
            $colorMark.' <b>#'.$task->id.' · '.$this->escape($task->title).'</b>',
        ];

        $lines[] = '';
        $lines[] = $this->scheduleBlock($due, $color);

        $meta = array_values(array_filter([
            $task->project ? '📁 '.$this->escape($task->project->name) : null,
            $task->contact ? '👤 '.$this->escape($task->contact->name) : null,
            '📌 '.$this->escape($task->status->label()).' · '.$this->escape($task->priority->label()),
        ]));

        if ($meta !== []) {
            $lines[] = '';
            array_push($lines, ...$meta);
        }

        if ($task->want_meet || filled($task->meet_url)) {
            $lines[] = '';
            if (filled($task->meet_url)) {
                $lines[] = '🎥 <a href="'.$this->escape($task->meet_url).'">Abrir Google Meet</a>';
            } else {
                $lines[] = '🎥 Meet previsto ao sincronizar';
            }
        }

        if ($task->isSyncedWithGoogle()) {
            $lines[] = '☁️ Sincronizado com Google Agenda';
        }

        $description = trim((string) $task->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = '📝 '.$this->escape(Str::limit($description, 320));
        }

        $lines[] = '';
        $lines[] = '🔗 <a href="'.$this->escape($url).'">Abrir na agenda do CRM</a>';

        return implode("\n", $lines);
    }

    /** Linha compacta para listagens. */
    public function listLine(Task $task): string
    {
        $color = $task->googleColor();
        $mark = $color?->telegramEmoji() ?? '•';
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $when = $due
            ? $due->translatedFormat('d/m H:i')
            : 'sem hora';

        $bits = [
            $mark.' <b>#'.$task->id.'</b>',
            $this->escape($task->title),
            '🕐 '.$when,
            $this->escape($task->status->label()),
        ];

        if ($color) {
            $bits[] = $color->label();
        }

        return implode(' · ', $bits);
    }

    public function reminder(Task $task, Carbon $now): string
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $minutes = $due ? (int) round($now->copy()->timezone($timezone)->diffInMinutes($due, false)) : 0;
        $color = $task->googleColor();
        $mark = $color?->telegramEmoji() ?? '⏰';

        $whenLabel = match (true) {
            $due === null => 'sem horário',
            $minutes <= 0 => 'agora · '.$due->format('H:i'),
            $minutes === 1 => 'em 1 min · '.$due->format('H:i'),
            default => 'em ~'.$minutes.' min · '.$due->format('H:i'),
        };

        $headline = $mark.' <b>Lembrete de agenda</b>';

        $card = $this->card($task, $headline);

        // Inject timing emphasis near the top after headline separator.
        return preg_replace(
            '/──────────────\n/',
            "──────────────\n⏱ <b>".$this->escape($whenLabel)."</b>\n",
            $card,
            1
        ) ?? $card;
    }

    private function scheduleBlock(?Carbon $due, ?GoogleEventColor $color): string
    {
        if ($due === null) {
            return '🕐 <i>Sem data/hora na agenda</i>';
        }

        $day = Str::ucfirst($due->translatedFormat('D, d M Y'));
        $time = $due->format('H:i');
        $relative = $due->diffForHumans();
        $colorLine = $color
            ? "\n🎨 ".$color->telegramEmoji().' '.$this->escape($color->label()).' · <code>'.$color->background().'</code>'
            : '';

        return '🕐 <b>'.$this->escape($day).'</b> · <b>'.$time.'</b>'
            ."\n⏳ ".$this->escape($relative)
            .$colorLine;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
