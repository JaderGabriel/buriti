<?php

namespace App\Console\Commands;

use App\Services\OpportunityFollowUpService;
use Illuminate\Console\Command;

class SendOpportunityFollowUpsCommand extends Command
{
    protected $signature = 'opportunities:follow-up {--days=7 : Dias sem actividade}';

    protected $description = 'Avisa no Telegram oportunidades paradas em qualified/proposta/negociação';

    public function handle(OpportunityFollowUpService $followUps): int
    {
        $days = max(1, (int) $this->option('days'));
        $result = $followUps->notifyStale($days);

        $this->info(sprintf(
            'Follow-ups: %d oportunidades paradas, %d avisos enviados.',
            $result['checked'],
            $result['notified']
        ));

        return self::SUCCESS;
    }
}
