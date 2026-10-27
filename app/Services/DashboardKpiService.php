<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\Opportunity;
use App\Models\OpportunityStageEvent;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardKpiService
{
    private const DAYS = 14;

    private const LOOKBACK = 7;

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string,
     *     tone: string,
     *     icon: string,
     *     polarity: string,
     *     value: int,
     *     series: list<int>,
     *     path: string,
     *     area: string,
     *     direction: string,
     *     delta: int,
     *     delta_label: string,
     *     aria: string
     * }>
     */
    public function cards(?int $companyId = null): array
    {
        $start = CarbonImmutable::now()->subDays(self::DAYS - 1)->startOfDay();
        $days = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $days[] = $start->addDays($i)->toDateString();
        }

        $contactsQuery = Contact::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        return [
            $this->card(
                'unread',
                'Mensagens novas',
                route('admin.messages.index'),
                'messages',
                'message',
                'up-bad',
                $this->unreadSeries($start, $days),
                ContactMessage::query()->unread()->count(),
            ),
            $this->card(
                'messages',
                'Total mensagens',
                route('admin.messages.index'),
                'inbox',
                'mail',
                'up-good',
                $this->cumulativeSeries(ContactMessage::query(), $start, $days),
                ContactMessage::query()->count(),
            ),
            $this->card(
                'contacts',
                'Contatos CRM',
                route('admin.contacts.index'),
                'contacts',
                'contact',
                'up-good',
                $this->cumulativeSeries($contactsQuery, $start, $days),
                (clone $contactsQuery)->count(),
            ),
            $this->card(
                'opportunities',
                'Oportunidades abertas',
                route('admin.opportunities.index'),
                'opps',
                'opportunity',
                'up-good',
                $this->openOpportunitySeries($companyId, $start, $days),
                Opportunity::query()->open()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            ),
            $this->card(
                'projects',
                'Projetos',
                route('admin.projects.index'),
                'projects',
                'project',
                'up-good',
                $this->cumulativeSeries(Project::query(), $start, $days),
                Project::query()->count(),
            ),
            $this->card(
                'tasks',
                'Tarefas abertas',
                route('admin.tasks.index'),
                'tasks',
                'task',
                'up-bad',
                $this->openTaskSeries($start, $days),
                Task::query()->open()->count(),
            ),
        ];
    }

    /**
     * @param  list<string>  $days
     * @param  list<int>  $series
     * @return array{
     *     key: string,
     *     label: string,
     *     href: string,
     *     tone: string,
     *     icon: string,
     *     polarity: string,
     *     value: int,
     *     series: list<int>,
     *     path: string,
     *     area: string,
     *     direction: string,
     *     delta: int,
     *     delta_label: string,
     *     aria: string
     * }
     */
    private function card(
        string $key,
        string $label,
        string $href,
        string $tone,
        string $icon,
        string $polarity,
        array $series,
        int $value,
    ): array {
        if ($series === []) {
            $series = array_fill(0, self::DAYS, $value);
        }

        $series[count($series) - 1] = $value;

        $previous = $series[max(0, count($series) - 1 - self::LOOKBACK)];
        $delta = $value - $previous;
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        $spark = $this->spark($series);

        $deltaLabel = match ($direction) {
            'up' => '+'.$delta.' em 7 dias',
            'down' => '−'.abs($delta).' em 7 dias',
            default => 'estável em 7 dias',
        };

        $trendPhrase = match ($direction) {
            'up' => 'subiu '.$delta.' em 7 dias',
            'down' => 'caiu '.abs($delta).' em 7 dias',
            default => 'estável em 7 dias',
        };

        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'tone' => $tone,
            'icon' => $icon,
            'polarity' => $polarity,
            'value' => $value,
            'series' => $series,
            'path' => $spark['path'],
            'area' => $spark['area'],
            'direction' => $direction,
            'delta' => $delta,
            'delta_label' => $deltaLabel,
            'aria' => $label.': '.$value.'. '.$trendPhrase,
        ];
    }

    /**
     * @param  list<string>  $days
     * @return list<int>
     */
    private function unreadSeries(CarbonImmutable $start, array $days): array
    {
        $rows = ContactMessage::query()
            ->where(function ($query) use ($start) {
                $query->whereNull('read_at')->orWhere('read_at', '>=', $start);
            })
            ->get(['created_at', 'read_at']);

        $startStock = 0;
        $delta = array_fill_keys($days, 0);

        foreach ($rows as $row) {
            if ($row->created_at?->lt($start)) {
                $startStock++;
            } elseif ($row->created_at) {
                $this->bump($delta, $row->created_at->toDateString(), 1);
            }

            if ($row->read_at?->gte($start)) {
                $this->bump($delta, $row->read_at->toDateString(), -1);
            }
        }

        return $this->running($startStock, $delta, $days);
    }

    /**
     * @param  list<string>  $days
     * @return list<int>
     */
    private function cumulativeSeries(Builder $query, CarbonImmutable $start, array $days): array
    {
        $startStock = (clone $query)->where('created_at', '<', $start)->count();
        $delta = array_fill_keys($days, 0);

        foreach ((clone $query)->where('created_at', '>=', $start)->get(['created_at']) as $row) {
            if ($row->created_at) {
                $this->bump($delta, $row->created_at->toDateString(), 1);
            }
        }

        return $this->running($startStock, $delta, $days);
    }

    /**
     * @param  list<string>  $days
     * @return list<int>
     */
    private function openOpportunitySeries(?int $companyId, CarbonImmutable $start, array $days): array
    {
        $opportunities = Opportunity::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'created_at', 'stage', 'updated_at']);

        $closedAt = [];
        OpportunityStageEvent::query()
            ->whereIn('to_stage', [OpportunityStage::Won, OpportunityStage::Lost])
            ->when($companyId, fn ($q) => $q->whereHas('opportunity', fn ($o) => $o->where('company_id', $companyId)))
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get(['opportunity_id', 'changed_at'])
            ->each(function (OpportunityStageEvent $event) use (&$closedAt): void {
                if (! isset($closedAt[$event->opportunity_id])) {
                    $closedAt[$event->opportunity_id] = $event->changed_at;
                }
            });

        foreach ($opportunities as $opportunity) {
            $stage = $opportunity->stage;
            if ($stage instanceof OpportunityStage && ! $stage->isOpen() && ! isset($closedAt[$opportunity->id])) {
                $closedAt[$opportunity->id] = $opportunity->updated_at ?? $opportunity->created_at;
            }
        }

        $startStock = 0;
        $delta = array_fill_keys($days, 0);

        foreach ($opportunities as $opportunity) {
            if (! $opportunity->created_at) {
                continue;
            }

            $closed = $closedAt[$opportunity->id] ?? null;
            if ($opportunity->created_at->lt($start)) {
                if ($closed === null || $closed->gte($start)) {
                    $startStock++;
                }
            } else {
                $this->bump($delta, $opportunity->created_at->toDateString(), 1);
            }

            if ($closed?->gte($start)) {
                $this->bump($delta, $closed->toDateString(), -1);
            }
        }

        return $this->running($startStock, $delta, $days);
    }

    /**
     * @param  list<string>  $days
     * @return list<int>
     */
    private function openTaskSeries(CarbonImmutable $start, array $days): array
    {
        $tasks = Task::query()->get(['created_at', 'status', 'updated_at']);
        $startStock = 0;
        $delta = array_fill_keys($days, 0);

        foreach ($tasks as $task) {
            if (! $task->created_at) {
                continue;
            }

            $closed = $task->status instanceof TaskStatus && $task->status->isDone()
                ? ($task->updated_at ?? $task->created_at)
                : null;

            if ($task->created_at->lt($start)) {
                if ($closed === null || $closed->gte($start)) {
                    $startStock++;
                }
            } else {
                $this->bump($delta, $task->created_at->toDateString(), 1);
            }

            if ($closed?->gte($start)) {
                $this->bump($delta, $closed->toDateString(), -1);
            }
        }

        return $this->running($startStock, $delta, $days);
    }

    /**
     * @param  array<string, int>  $delta
     */
    private function bump(array &$delta, string $day, int $amount): void
    {
        if (array_key_exists($day, $delta)) {
            $delta[$day] += $amount;
        }
    }

    /**
     * @param  array<string, int>  $delta
     * @param  list<string>  $days
     * @return list<int>
     */
    private function running(int $startStock, array $delta, array $days): array
    {
        $value = $startStock;
        $series = [];

        foreach ($days as $day) {
            $value = max(0, $value + ($delta[$day] ?? 0));
            $series[] = $value;
        }

        return $series;
    }

    /**
     * @param  list<int>  $series
     * @return array{path: string, area: string}
     */
    private function spark(array $series, int $width = 72, int $height = 28): array
    {
        $count = count($series);
        if ($count === 0) {
            return ['path' => '', 'area' => ''];
        }

        $min = min($series);
        $max = max($series);
        $span = $max - $min;
        $padX = 1.6;
        $padY = 3.2;
        $innerW = $width - ($padX * 2);
        $innerH = $height - ($padY * 2);
        $points = [];

        foreach ($series as $index => $value) {
            $x = $padX + ($count === 1 ? $innerW / 2 : $index * $innerW / ($count - 1));
            $ratio = $span > 0 ? ($value - $min) / $span : 0.5;
            $y = $padY + ($innerH * (1 - $ratio));
            $points[] = [round($x, 2), round($y, 2)];
        }

        $path = '';
        foreach ($points as $index => [$x, $y]) {
            $path .= ($index === 0 ? 'M' : ' L')." {$x} {$y}";
        }

        $firstX = $points[0][0];
        $lastX = $points[$count - 1][0];
        $area = $path." L {$lastX} {$height} L {$firstX} {$height} Z";

        return ['path' => $path, 'area' => $area];
    }
}
