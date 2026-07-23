<?php

namespace App\Services\Telegram;

use App\Enums\GoogleEventColor;
use App\Models\Contact;
use App\Models\Task;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TaskTelegramFormatter
{
    /**
     * Card rico de agenda para detalhe / criação / actualização / lembrete.
     */
    public function card(Task $task, ?string $headline = null): string
    {
        $task->loadMissing(['project', 'contact', 'activities.contact', 'activities.user']);

        $color = $task->googleColor();
        $isDone = $task->status->isDone();
        $statusMark = $task->status->telegramMark();
        $colorMark = $isDone ? '✅' : ($color?->telegramEmoji() ?? '📅');
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $url = route('admin.tasks.index', array_filter([
            'view' => 'agenda',
            'month' => $due?->format('Y-m'),
            'focus' => $task->id,
        ])).($task->id ? '#task-'.$task->id : '');

        $defaultHeadline = $isDone
            ? '✅ <b>Compromisso concluído</b>'
            : ($colorMark.' <b>Compromisso na agenda</b>');

        $lines = [
            $headline ?: $defaultHeadline,
            '──────────────',
            $statusMark.' <b>#'.$task->id.' · '.$this->escape($task->title).'</b>',
        ];

        if ($isDone) {
            $lines[] = '🟢 <b>Êxito · '.$this->escape($task->status->label()).'</b>';
        }

        $lines[] = '';
        $lines[] = $this->scheduleBlock($due, $color, $isDone);

        $meta = array_values(array_filter([
            $task->project ? '📁 '.$this->escape($task->project->name) : null,
            $statusMark.' '.$this->escape($task->status->label()).' · '.$this->escape($task->priority->label()),
        ]));

        if ($meta !== []) {
            $lines[] = '';
            array_push($lines, ...$meta);
        }

        $contactBlock = $this->contactActionsBlock($task->contact);
        if ($contactBlock !== null) {
            $lines[] = '';
            $lines[] = $contactBlock;
        }

        $meetBlock = $this->meetAndInviteBlock($task, $due);
        if ($meetBlock !== null) {
            $lines[] = '';
            $lines[] = $meetBlock;
        }

        if ($task->isSyncedWithGoogle()) {
            $lines[] = '';
            $lines[] = '☁️ Sincronizado com Google Agenda';
        }

        $lines[] = '';
        $lines[] = $this->activitiesBlock($task);

        $lines[] = '';
        $lines[] = '🔗 <a href="'.$this->escape($url).'">Abrir na agenda do CRM</a>';

        return implode("\n", $lines);
    }

    /** Linha compacta para listagens. */
    public function listLine(Task $task): string
    {
        $task->loadMissing(['activities']);

        $color = $task->googleColor();
        $isDone = $task->status->isDone();
        $mark = $isDone ? '✅' : ($color?->telegramEmoji() ?? $task->status->telegramMark());
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $when = $due
            ? $due->translatedFormat('d/m H:i')
            : 'sem hora';

        $bits = [
            $mark.' <b>#'.$task->id.'</b>',
            $this->escape($task->title),
            '🕐 '.$when,
            $isDone ? '<b>Concluída</b>' : $this->escape($task->status->label()),
        ];

        if ($color && ! $isDone) {
            $bits[] = $color->label();
        }

        $latest = $task->activities->first();
        if ($latest) {
            $preview = trim((string) ($latest->body ?: $latest->subject ?: $latest->type->label()));
            $bits[] = '🗂 '.$this->escape($latest->type->label()).': '.$this->escape(Str::limit($preview, 48));
        }

        return implode(' · ', $bits);
    }

    public function reminder(Task $task, Carbon $now): string
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $minutes = $due ? (int) round($now->copy()->timezone($timezone)->diffInMinutes($due, false)) : 0;
        $color = $task->googleColor();
        $mark = $task->status->isDone()
            ? '✅'
            : ($color?->telegramEmoji() ?? '⏰');

        $whenLabel = match (true) {
            $due === null => 'sem horário',
            $minutes <= 0 => 'agora · '.$due->format('H:i'),
            $minutes === 1 => 'em 1 min · '.$due->format('H:i'),
            default => 'em ~'.$minutes.' min · '.$due->format('H:i'),
        };

        $headline = $mark.' <b>Lembrete de agenda</b>';

        $card = $this->card($task, $headline);

        return preg_replace(
            '/──────────────\n/',
            "──────────────\n⏱ <b>".$this->escape($whenLabel)."</b>\n",
            $card,
            1
        ) ?? $card;
    }

    private function activitiesBlock(Task $task): string
    {
        $activities = $task->activities->take(5);

        if ($activities->isEmpty()) {
            $hint = $task->contact
                ? 'Sem atividades ligadas. Registe na ficha do contato e vincule esta tarefa.'
                : 'Sem atividades. Associe um contato e registe na ficha CRM.';

            return '🗂 <b>Atividades do contato</b>'."\n".'<i>'.$this->escape($hint).'</i>';
        }

        $lines = ['🗂 <b>Atividades do contato</b> ('.$activities->count().')'];

        foreach ($activities as $activity) {
            $when = optional($activity->happened_at ?? $activity->created_at)->format('d/m H:i') ?? '—';
            $title = filled($activity->subject) ? (string) $activity->subject : $activity->type->label();
            $body = trim((string) ($activity->body ?? ''));
            $lines[] = '';
            $lines[] = '• <b>'.$this->escape($activity->type->label()).'</b> · '.$when;
            $lines[] = $this->escape($title);
            if ($body !== '' && $body !== $title) {
                $lines[] = $this->escape(Str::limit($body, 400));
            }
            if ($activity->contact?->name) {
                $lines[] = '👤 '.$this->escape($activity->contact->name);
            }
        }

        if ($task->contact) {
            $lines[] = '';
            $lines[] = '<a href="'.$this->escape(route('admin.contacts.show', $task->contact).'#conducao').'">Abrir condução do contato</a>';
        }

        return implode("\n", $lines);
    }

    private function scheduleBlock(?Carbon $due, ?GoogleEventColor $color, bool $isDone = false): string
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

        $prefix = $isDone ? '✅' : '🕐';

        return $prefix.' <b>'.$this->escape($day).'</b> · <b>'.$time.'</b>'
            ."\n⏳ ".$this->escape($relative)
            .$colorLine;
    }

    private function contactActionsBlock(?Contact $contact): ?string
    {
        if ($contact === null) {
            return null;
        }

        $lines = [
            '👤 <b>'.$this->escape($contact->name).'</b>',
        ];

        $phoneLabel = PhoneNumber::format($contact->phone);
        if ($phoneLabel) {
            $lines[] = '📞 '.$this->escape($phoneLabel);
        }

        $actions = [];
        $tel = $contact->telUrl();
        if ($tel) {
            $actions[] = '<a href="'.$this->escape($tel).'">Ligar</a>';
        }

        $whatsapp = $contact->whatsappUrl();
        if ($whatsapp) {
            $actions[] = '<a href="'.$this->escape($whatsapp).'">WhatsApp</a>';
        }

        if ($actions !== []) {
            $lines[] = '📲 '.implode(' · ', $actions);
        }

        return implode("\n", $lines);
    }

    private function meetAndInviteBlock(Task $task, ?Carbon $due): ?string
    {
        if (! $task->want_meet && blank($task->meet_url)) {
            return null;
        }

        $lines = [];

        if (filled($task->meet_url)) {
            $meetUrl = (string) $task->meet_url;
            $lines[] = '🎥 <a href="'.$this->escape($meetUrl).'">Abrir Google Meet</a>';

            $inviteText = $this->invitePlainText($task, $due, $meetUrl);
            $lines[] = '';
            $lines[] = '📩 <b>Convite para encaminhar</b>';
            $lines[] = '<pre>'.$this->escape($inviteText).'</pre>';

            $shareLinks = [];
            $contactWhatsapp = $task->contact?->whatsappUrl();
            if ($contactWhatsapp) {
                $shareLinks[] = '<a href="'.$this->escape($this->whatsappShareUrl($contactWhatsapp, $inviteText)).'">Enviar convite no WhatsApp</a>';
            }

            $shareLinks[] = '<a href="'.$this->escape($this->whatsappShareUrl('https://wa.me/', $inviteText)).'">Encaminhar noutro WhatsApp</a>';
            $lines[] = implode(' · ', $shareLinks);
        } else {
            $lines[] = '🎥 Meet previsto ao sincronizar com a Agenda';
        }

        return implode("\n", $lines);
    }

    private function invitePlainText(Task $task, ?Carbon $due, string $meetUrl): string
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $when = $due
            ? Str::ucfirst($due->copy()->timezone($timezone)->translatedFormat('l, d/m/Y')).' às '.$due->format('H:i')
            : 'horário a confirmar';

        $lines = [
            'Olá'.($task->contact?->name ? ', '.$task->contact->name : '').'!',
            '',
            'Segue o convite para o nosso compromisso:',
            '',
            '📌 '.$task->title,
            '🕐 '.$when,
            '🎥 Meet: '.$meetUrl,
        ];

        if ($task->project?->name) {
            $lines[] = '📁 '.$task->project->name;
        }

        $description = trim((string) $task->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = Str::limit($description, 180);
        }

        $lines[] = '';
        $lines[] = 'Até lá!';

        return implode("\n", $lines);
    }

    private function whatsappShareUrl(string $baseOrContactUrl, string $text): string
    {
        $query = http_build_query(['text' => $text], '', '&', PHP_QUERY_RFC3986);

        if (str_starts_with($baseOrContactUrl, 'https://wa.me/') && $baseOrContactUrl !== 'https://wa.me/') {
            $separator = str_contains($baseOrContactUrl, '?') ? '&' : '?';

            return $baseOrContactUrl.$separator.$query;
        }

        return 'https://wa.me/?'.$query;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
