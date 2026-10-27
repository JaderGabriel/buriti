<?php

namespace App\Console\Commands;

use App\Services\OpportunityFollowUpService;
use Illuminate\Console\Command;

class SendOpportunityFollowUpsCommand extends Command
{
    protected $signature = 'opportunities:follow-up
                            {--days=7 : Janela do resumo em dias}
                            {--force : Enviar mesmo se já foi nesta semana}';

    protected $description = 'Envia um resumo comercial semanal no Telegram';

    public function handle(OpportunityFollowUpService $followUps): int
    {
        $result = $followUps->notifyWeeklySummary(
            max(1, (int) $this->option('days')),
            (bool) $this->option('force'),
        );

        if ($result['skipped']) {
            $this->info('Resumo comercial já enviado nesta semana.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Resumo comercial: %s, %d chat(s).',
            $result['sent'] ? 'enviado' : 'não enviado',
            $result['chats'],
        ));

        return self::SUCCESS;
    }
}
