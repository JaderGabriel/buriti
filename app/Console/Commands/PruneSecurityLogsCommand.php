<?php

namespace App\Console\Commands;

use App\Services\AuthSecurityService;
use Illuminate\Console\Command;

class PruneSecurityLogsCommand extends Command
{
    protected $signature = 'security:prune-logs {--days=90 : Conservar este número de dias}';

    protected $description = 'Remove tentativas de login e audit logs mais antigos que o prazo indicado.';

    public function handle(AuthSecurityService $security): int
    {
        $days = max(1, (int) $this->option('days'));
        $pruned = $security->pruneOlderThanDays($days);

        $this->info("Removidas {$pruned['login_activities']} tentativas de login e {$pruned['audit_logs']} auditorias com mais de {$days} dias.");

        return self::SUCCESS;
    }
}
