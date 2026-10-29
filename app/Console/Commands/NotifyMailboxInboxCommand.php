<?php

namespace App\Console\Commands;

use App\Services\MailInboxPollService;
use Illuminate\Console\Command;

class NotifyMailboxInboxCommand extends Command
{
    protected $signature = 'mail:notify-inbox';

    protected $description = 'Avisa no Telegram e no sino do admin os e-mails novos da caixa de entrada';

    public function handle(MailInboxPollService $poll): int
    {
        $result = $poll->poll();

        $this->info(sprintf(
            'INBOX: %d avisos, %d ignorados%s.',
            $result['notified'],
            $result['skipped'],
            $result['seeded'] !== null ? ', marca inicial '.$result['seeded'] : '',
        ));

        return self::SUCCESS;
    }
}
