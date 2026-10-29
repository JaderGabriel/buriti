<?php

namespace App\Services;

use App\Models\QaRun;
use App\Support\QaCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QaDashboardService
{
    /**
     * @return array{
     *     health: array{label: string, tone: string, hint: string},
     *     kpis: list<array<string, mixed>>,
     *     modules: list<array<string, mixed>>,
     *     last_by_command: array<string, QaRun>,
     *     trend: list<array{day: string, label: string, passed: int, failed: int, total: int}>,
     *     last_suite: ?QaRun
     * }
     */
    public function build(): array
    {
        $now = now();
        $from14 = $now->copy()->subDays(13)->startOfDay();
        $from7 = $now->copy()->subDays(6)->startOfDay();
        $prev7Start = $now->copy()->subDays(13)->startOfDay();
        $prev7End = $from7->copy()->subSecond();

        $recent = QaRun::query()
            ->with('user:id,name')
            ->where('created_at', '>=', $from14)
            ->orderByDesc('id')
            ->get();

        $lastByCommand = [];
        foreach (QaRun::query()->with('user:id,name')->latest('id')->limit(120)->get() as $run) {
            if (! isset($lastByCommand[$run->command])) {
                $lastByCommand[$run->command] = $run;
            }
        }

        $week = $recent->filter(fn (QaRun $run) => $run->created_at >= $from7);
        $prev = $recent->filter(fn (QaRun $run) => $run->created_at >= $prev7Start && $run->created_at <= $prev7End);

        $weekFinished = $week->filter(fn (QaRun $run) => ! $run->isRunning());
        $prevFinished = $prev->filter(fn (QaRun $run) => ! $run->isRunning());
        $weekPassed = $weekFinished->filter(fn (QaRun $run) => $run->isPassed());
        $prevPassed = $prevFinished->filter(fn (QaRun $run) => $run->isPassed());
        $weekFailed = $weekFinished->filter(fn (QaRun $run) => $run->isFailed());

        $passRate = $weekFinished->count() > 0
            ? (int) round(($weekPassed->count() / $weekFinished->count()) * 100)
            : null;
        $prevRate = $prevFinished->count() > 0
            ? (int) round(($prevPassed->count() / $prevFinished->count()) * 100)
            : null;

        $durations = $weekFinished
            ->map(fn (QaRun $run) => $run->durationSeconds())
            ->filter(fn ($value) => $value !== null)
            ->values();
        $avgDuration = $durations->count() > 0 ? (int) round($durations->avg()) : null;

        $running = QaRun::query()->where('status', QaRun::STATUS_RUNNING)->count();
        $lastSuite = $lastByCommand['suite.phpunit'] ?? null;

        $dailyTotals = $this->dailySeries($recent, $from14, 14);
        $dailyFailed = $this->dailySeries($recent->filter(fn (QaRun $run) => $run->isFailed()), $from14, 14);
        $dailyPassed = $this->dailySeries($recent->filter(fn (QaRun $run) => $run->isPassed()), $from14, 14);

        $kpis = [
            $this->kpi(
                'Taxa de sucesso',
                $passRate === null ? '—' : $passRate.'%',
                $this->ppDelta($passRate, $prevRate),
                $dailyPassed,
                'opps',
                'qualified',
                'up-good',
                'Percentagem de execuções OK nos últimos 7 dias.',
            ),
            $this->kpi(
                'Execuções (7d)',
                (string) $week->count(),
                $this->countDelta($week->count(), $prev->count()),
                $dailyTotals,
                'messages',
                'flow',
                'up-good',
                'Corridas nos últimos 7 dias.',
            ),
            $this->kpi(
                'Falhas (7d)',
                (string) $weekFailed->count(),
                $this->countDelta($weekFailed->count(), $prev->filter(fn (QaRun $run) => $run->isFailed())->count()),
                $dailyFailed,
                'tasks',
                'lost',
                'up-bad',
                'Falhas e erros nos últimos 7 dias.',
            ),
            $this->kpi(
                'Duração média',
                $avgDuration === null ? '—' : $avgDuration.'s',
                'média 7d',
                $dailyTotals,
                'projects',
                'calendar',
                'up-good',
                'Tempo médio das execuções concluídas.',
                'flat',
            ),
            $this->kpi(
                'Última suíte',
                $lastSuite?->statusLabel() ?? '—',
                $lastSuite?->created_at?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'sem corrida',
                $dailyPassed,
                'contacts',
                'diagnose',
                $lastSuite?->isFailed() ? 'up-bad' : 'up-good',
                'Resultado da última suíte PHPUnit completa.',
                $lastSuite === null ? 'flat' : ($lastSuite->isPassed() ? 'up' : ($lastSuite->isFailed() ? 'down' : 'flat')),
            ),
            $this->kpi(
                'Em execução',
                (string) $running,
                $running > 0 ? 'ativo' : 'fila livre',
                $dailyTotals,
                'inbox',
                'operate',
                'up-bad',
                'Suítes ou probes neste momento.',
                $running > 0 ? 'up' : 'flat',
            ),
        ];

        return [
            'health' => $this->health($running, $passRate, $week->count(), $weekFailed->count()),
            'kpis' => $kpis,
            'modules' => $this->modules($lastByCommand),
            'last_by_command' => $lastByCommand,
            'trend' => $this->trend($recent, $from14),
            'last_suite' => $lastSuite,
        ];
    }

    /**
     * @param  Collection<int, QaRun>  $runs
     * @return list<int>
     */
    private function dailySeries(Collection $runs, Carbon $from, int $days): array
    {
        $index = [];
        for ($i = 0; $i < $days; $i++) {
            $index[$from->copy()->addDays($i)->toDateString()] = $i;
        }

        $bucket = array_fill(0, $days, 0);

        foreach ($runs as $run) {
            $key = $run->created_at->toDateString();
            if (isset($index[$key])) {
                $bucket[$index[$key]]++;
            }
        }

        return $bucket;
    }

    /**
     * @param  Collection<int, QaRun>  $runs
     * @return list<array{day: string, label: string, passed: int, failed: int, total: int}>
     */
    private function trend(Collection $runs, Carbon $from): array
    {
        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $days[$key] = [
                'day' => $key,
                'label' => $day->format('j'),
                'passed' => 0,
                'failed' => 0,
                'total' => 0,
            ];
        }

        foreach ($runs as $run) {
            $key = $run->created_at->toDateString();
            if (! isset($days[$key])) {
                continue;
            }
            $days[$key]['total']++;
            if ($run->isPassed()) {
                $days[$key]['passed']++;
            } elseif ($run->isFailed()) {
                $days[$key]['failed']++;
            }
        }

        return array_values($days);
    }

    /**
     * @param  array<string, QaRun>  $lastByCommand
     * @return list<array<string, mixed>>
     */
    private function modules(array $lastByCommand): array
    {
        $items = [];

        foreach (QaCatalog::groups() as $group) {
            $ids = collect($group['commands'])->pluck('id')->all();
            $last = null;
            foreach ($ids as $id) {
                $candidate = $lastByCommand[$id] ?? null;
                if ($candidate === null) {
                    continue;
                }
                if ($last === null || $candidate->id > $last->id) {
                    $last = $candidate;
                }
            }

            $items[] = [
                'id' => $group['id'],
                'label' => $group['label'],
                'icon' => $group['icon'] ?? 'diagnose',
                'tone' => $group['id'],
                'run' => $last,
                'status' => $last?->status ?? 'idle',
                'status_label' => $last?->statusLabel() ?? 'Sem dados',
            ];
        }

        return $items;
    }

    /**
     * @param  list<int>  $series
     * @return array<string, mixed>
     */
    private function kpi(
        string $label,
        string $value,
        string $deltaLabel,
        array $series,
        string $tone,
        string $icon,
        string $polarity,
        string $aria,
        ?string $direction = null,
    ): array {
        $spark = $this->spark($series);
        $direction ??= $this->directionFromDelta($deltaLabel);

        return [
            'label' => $label,
            'value' => $value,
            'delta_label' => $deltaLabel,
            'tone' => $tone,
            'icon' => $icon,
            'polarity' => $polarity,
            'direction' => $direction,
            'path' => $spark['path'],
            'area' => $spark['area'],
            'aria' => $aria,
        ];
    }

    /**
     * @return array{label: string, tone: string, hint: string}
     */
    private function health(int $running, ?int $passRate, int $weekRuns, int $weekFailed): array
    {
        if ($running > 0) {
            return [
                'label' => 'Em execução',
                'tone' => 'run',
                'hint' => 'Há uma suíte ou probe em execução.',
            ];
        }

        if ($weekRuns === 0) {
            return [
                'label' => 'Sem dados',
                'tone' => 'idle',
                'hint' => 'Ainda não há execuções nos últimos 7 dias.',
            ];
        }

        if ($weekFailed > 0 && ($passRate === null || $passRate < 85)) {
            return [
                'label' => 'Atenção',
                'tone' => 'warn',
                'hint' => $weekFailed.' falha(s) nos últimos 7 dias.',
            ];
        }

        return [
            'label' => 'Operacional',
            'tone' => 'ok',
            'hint' => 'Qualidade estável nos últimos 7 dias.',
        ];
    }

    private function countDelta(int $current, int $previous): string
    {
        $diff = $current - $previous;
        if ($diff === 0) {
            return '0';
        }

        return ($diff > 0 ? '+' : '').$diff;
    }

    private function ppDelta(?int $current, ?int $previous): string
    {
        if ($current === null) {
            return '—';
        }
        if ($previous === null) {
            return 'novo';
        }

        $diff = $current - $previous;
        if ($diff === 0) {
            return '0 pp';
        }

        return ($diff > 0 ? '+' : '').$diff.' pp';
    }

    private function directionFromDelta(string $deltaLabel): string
    {
        if (str_starts_with($deltaLabel, '+')) {
            return 'up';
        }
        if (str_starts_with($deltaLabel, '-') || str_starts_with($deltaLabel, '−')) {
            return 'down';
        }

        return 'flat';
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
