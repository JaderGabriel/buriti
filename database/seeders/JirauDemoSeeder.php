<?php

namespace Database\Seeders;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Enums\ProjectStatus;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\Project;
use App\Models\User;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JirauDemoSeeder extends Seeder
{
    public const DEMO_USERNAME = 'jirau.demo';

    public const DEMO_EMAIL = 'jirau.demo@buriti.dev.br';

    public const DEMO_PASSWORD = 'jirau-demo-2026';

    public const DEMO_BOARD_NAME = Jirau::DEMO_NAME;

    public function run(): void
    {
        $admin = User::query()->where('is_admin', true)->orderBy('id')->first();
        $boards = app(JirauBoardService::class);

        $project = Project::query()->firstOrCreate(
            ['name' => Jirau::DEMO_PROJECT_NAME],
            [
                'information' => 'Quadro de demonstração do Jirau — plataforma ágil da BURI-TI no lugar de Trello/Notion.',
                'stack' => ['Laravel', 'Tailwind'],
                'category' => 'Desenvolvimento',
                'status' => ProjectStatus::Active,
                'is_public' => false,
                'featured_on_home' => false,
                'repo_is_private' => true,
                'sort_order' => 90,
            ],
        );

        $jirau = $project->jirau ?: $boards->createForProject(
            $project,
            $admin ?? User::factory()->create(['name' => 'Admin Jirau']),
            self::DEMO_BOARD_NAME,
        );

        if ($jirau->name !== self::DEMO_BOARD_NAME) {
            $jirau->forceFill(['name' => self::DEMO_BOARD_NAME])->save();
        }

        $member = User::query()->firstOrNew(['username' => self::DEMO_USERNAME]);
        $member->fill([
            'name' => 'Cliente demonstração',
            'email' => self::DEMO_EMAIL,
            'password' => Hash::make(self::DEMO_PASSWORD),
        ]);
        $member->forceFill([
            'is_admin' => false,
            'is_active' => true,
        ])->save();

        if (! $jirau->members()->whereKey($member->id)->exists()) {
            $jirau->members()->attach($member->id, ['invited_by' => $admin?->id]);
        }

        if ($jirau->cards()->exists()) {
            return;
        }

        $columns = $jirau->columns()->get()->keyBy('slug');
        $labels = $jirau->labels()->get()->keyBy('name');

        $this->card($jirau, $columns['inbox'], $member, [
            'title' => 'Incluir exportação do relatório mensal em PDF',
            'description' => 'Hoje só conseguimos ver o relatório na tela. Precisamos baixar em PDF para enviar à diretoria.',
            'type' => JirauCardType::Demand,
            'priority' => JirauCardPriority::High,
        ]);

        $this->card($jirau, $columns['backlog'], $admin, [
            'title' => 'Autenticação do portal do cliente',
            'description' => 'Como cliente, quero entrar no Jirau só deste projeto para acompanhar o andamento.',
            'type' => JirauCardType::Story,
            'priority' => JirauCardPriority::High,
            'estimate' => 5,
            'label' => 'Backend',
        ], $labels);

        $this->card($jirau, $columns['ready'], $admin, [
            'title' => 'Filtro por etiqueta e prioridade no quadro',
            'description' => 'Critério de pronto: busca, tipo, prioridade e etiqueta aplicados na URL.',
            'type' => JirauCardType::Story,
            'priority' => JirauCardPriority::Medium,
            'estimate' => 3,
            'label' => 'Frontend',
        ], $labels);

        $doing = $this->card($jirau, $columns['doing'], $admin, [
            'title' => 'WIP nas faixas Em curso e Revisão',
            'description' => 'Limite 3. Acima do limite a faixa destaca sobrecarga.',
            'type' => JirauCardType::Task,
            'priority' => JirauCardPriority::Medium,
            'estimate' => 2,
            'assigned_to' => $admin?->id,
        ]);
        $doing->checklistItems()->createMany([
            ['title' => 'Mostrar limite no cabeçalho', 'sort_order' => 10, 'is_done' => true],
            ['title' => 'Estado visual quando estoura', 'sort_order' => 20, 'is_done' => false],
        ]);

        $this->card($jirau, $columns['review'], $admin, [
            'title' => 'Notificar admin ao receber demanda',
            'description' => 'Telegram para admins com link direto do cartão JR.',
            'type' => JirauCardType::Task,
            'priority' => JirauCardPriority::High,
            'estimate' => 2,
            'label' => 'Backend',
        ], $labels);

        $this->card($jirau, $columns['done'], $admin, [
            'title' => 'Colunas padrão do fluxo ágil',
            'description' => 'Demandas → Backlog → Pronto → Em curso → Revisão → Feito.',
            'type' => JirauCardType::Story,
            'priority' => JirauCardPriority::Low,
            'estimate' => 1,
            'triaged_at' => now()->subDays(4),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function card(Jirau $jirau, $column, ?User $actor, array $data, $labels = null): JirauCard
    {
        $labelName = $data['label'] ?? null;
        unset($data['label']);

        $max = (int) $jirau->cards()->where('column_id', $column->id)->max('sort_order');

        $card = $jirau->cards()->create([
            'column_id' => $column->id,
            'created_by' => $actor?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'],
            'estimate' => $data['estimate'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'sort_order' => $max + 10,
            'triaged_at' => $column->is_inbox ? null : now()->subDay(),
        ]);

        if ($labelName && $labels?->has($labelName)) {
            $card->labels()->attach($labels->get($labelName)->id);
        }

        return $card;
    }
}
