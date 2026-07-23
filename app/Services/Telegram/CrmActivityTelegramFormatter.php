<?php

namespace App\Services\Telegram;

use App\Models\CrmActivity;
use Illuminate\Support\Str;

class CrmActivityTelegramFormatter
{
    public function listLine(CrmActivity $activity): string
    {
        $activity->loadMissing(['contact']);

        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $when = $activity->happened_at?->timezone($timezone)?->translatedFormat('d/m H:i') ?? 'sem data';
        $contact = $activity->contact?->name ?? 'Sem contato';
        $subject = trim((string) ($activity->subject ?: $activity->type->label()));
        $mark = $activity->type->telegramMark();

        return implode(' · ', [
            $mark.' <b>#'.$activity->id.'</b>',
            $this->escape($activity->type->label()),
            $this->escape(Str::limit($subject, 48)),
            '👤 '.$this->escape(Str::limit($contact, 28)),
            '🕐 '.$when,
        ]);
    }

    public function card(CrmActivity $activity, ?string $headline = null): string
    {
        $activity->loadMissing(['contact', 'task', 'opportunity', 'user']);

        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $when = $activity->happened_at?->timezone($timezone);
        $mark = $activity->type->telegramMark();
        $contact = $activity->contact;
        $url = $contact
            ? route('admin.contacts.activities.edit', [$contact, $activity])
            : route('admin.contacts.index');

        $lines = [
            $headline ?: $mark.' <b>Atividade CRM</b>',
            '──────────────',
            $mark.' <b>#'.$activity->id.' · '.$this->escape($activity->type->label()).'</b>',
        ];

        if (filled($activity->subject)) {
            $lines[] = '<b>Assunto:</b> '.$this->escape($activity->subject);
        }

        if (filled($activity->body)) {
            $lines[] = '';
            $lines[] = $this->escape(Str::limit(strip_tags((string) $activity->body), 1200));
        }

        $lines[] = '';
        $lines[] = '🕐 '.($when ? $when->translatedFormat('d/m/Y H:i') : 'sem data');

        if ($contact) {
            $lines[] = '👤 <b>#'.$contact->id.'</b> '.$this->escape($contact->name);
            if ($contact->email) {
                $lines[] = '✉️ '.$this->escape($contact->email);
            }
        }

        if ($activity->task) {
            $lines[] = '📅 Tarefa <b>#'.$activity->task->id.'</b> '.$this->escape($activity->task->title);
        }

        if ($activity->opportunity) {
            $lines[] = '💼 Opp <b>#'.$activity->opportunity->id.'</b> '.$this->escape($activity->opportunity->title);
        }

        if ($activity->user) {
            $lines[] = '✍️ '.$this->escape($activity->user->name);
        }

        $lines[] = '';
        $lines[] = '🔗 <a href="'.$this->escape($url).'">Abrir no CRM</a>';

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
