<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Etapas e tarefas concluídas do Servlitcys a partir dos commits/releases
 * do repositório serventec-servlitcys — grupos lógicos do processo de venda/entrega.
 */
class ServlitcysProjectStagesSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('name', 'Servlitcys')->first();

        if (! $project) {
            $this->command?->error('Projeto Servlitcys não encontrado.');

            return;
        }

        $userId = User::query()->orderBy('id')->value('id');

        $stages = [
            [
                'title' => 'Kickoff e fundação da plataforma',
                'notes' => 'Contratação/kickoff técnico: Release 1.0 — Laravel, integração i-Educar e base PT-BR (commit 8507c9a2).',
                'completed_at' => '2026-04-13 18:00:00',
                'sort_order' => 10,
            ],
            [
                'title' => 'Painéis operacionais e consultoria municipal',
                'notes' => 'Mapa de escolas, semáforo RX, matrículas/VAAF, inclusão NEE e operação consultiva (releases 2.3.x).',
                'completed_at' => '2026-05-23 18:00:00',
                'sort_order' => 20,
            ],
            [
                'title' => 'Importação de bases educacionais (SAEB/FUNDEB)',
                'notes' => 'Release 2.4.0 Ceres — pipeline de importação SAEB (INEP) e FUNDEB para alimentar a análise.',
                'completed_at' => '2026-05-24 18:00:00',
                'sort_order' => 30,
            ],
            [
                'title' => 'Conformidade LGPD e módulo consultoria',
                'notes' => 'Release 3.0.0 Apollo — consentimento LGPD, privacidade, inclusão NEE e refinamentos de consultoria.',
                'completed_at' => '2026-05-25 18:00:00',
                'sort_order' => 40,
            ],
            [
                'title' => 'Horizonte — mapa de oportunidade comercial',
                'notes' => 'Releases 5.0.0–5.1.0: mapa de oportunidade municipal, prospecção e feed comercial quinzenal.',
                'completed_at' => '2026-06-19 18:00:00',
                'sort_order' => 50,
            ],
            [
                'title' => 'GIS nacional e identidade do produto Horizonte',
                'notes' => 'Release 6.0.0 Odin — marca Horizonte, UX GIS, dados públicos agrupados e identidade visual.',
                'completed_at' => '2026-06-24 18:00:00',
                'sort_order' => 60,
            ],
            [
                'title' => 'Inteligência financeira municipal (FUNDEB/SICONFI)',
                'notes' => 'Releases 7.0.0–7.0.1 Ploutos/Moneta — SICONFI, bases públicas e enriquecimento financeiro no Horizonte.',
                'completed_at' => '2026-07-05 18:00:00',
                'sort_order' => 70,
            ],
            [
                'title' => 'Consolidação territorial e documentação operacional',
                'notes' => 'Releases 6.5.0 Jord → 7.0.3 Calliope — Horizonte territorial, UI pt-BR e leitor de docs modular.',
                'completed_at' => '2026-07-09 18:00:00',
                'sort_order' => 80,
            ],
            [
                'title' => 'Hub Clio de relatórios e entrega consultiva',
                'notes' => 'Release 8.0.0 Aletheia + refinamentos 21–22/07 — hub de relatórios, PDF operacional, transporte escolar e home limpa. Entrega em 100%.',
                'completed_at' => '2026-07-22 18:00:00',
                'sort_order' => 90,
            ],
        ];

        foreach ($stages as $stage) {
            $completedAt = Carbon::parse($stage['completed_at'], config('app.timezone'));

            ProjectStep::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title' => $stage['title'],
                ],
                [
                    'notes' => $stage['notes'],
                    'is_completed' => true,
                    'completed_at' => $completedAt,
                    'sort_order' => $stage['sort_order'],
                ]
            );

            $task = Task::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title' => $stage['title'],
                ],
                [
                    'description' => $stage['notes'],
                    'status' => TaskStatus::Done,
                    'priority' => TaskPriority::Medium,
                    'due_at' => $completedAt,
                    'contact_id' => null,
                    'want_meet' => false,
                ]
            );

            if ($userId && $task->user_id !== $userId) {
                $task->forceFill(['user_id' => $userId])->save();
            }
        }

        $project->loadCount([
            'steps',
            'steps as done_steps_count' => fn ($q) => $q->where('is_completed', true),
            'tasks',
            'tasks as done_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Done),
        ]);

        $stats = $project->progressStats();

        $this->command?->info(sprintf(
            'Servlitcys: %d etapas e %d tarefas sincronizadas. Progresso %s%% (%s).',
            $project->steps_count,
            $project->tasks_count,
            $stats['percent'] ?? 0,
            $stats['source']
        ));
    }
}
