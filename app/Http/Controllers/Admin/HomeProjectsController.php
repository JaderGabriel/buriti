<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderHomeProjectsRequest;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeProjectsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function edit(): View
    {
        $projects = Project::query()->orderBy('name')->get();

        $featured = $projects
            ->where('is_public', true)
            ->where('featured_on_home', true)
            ->sortBy('featured_sort')
            ->values();

        $portfolio = $projects
            ->where('is_public', true)
            ->where('featured_on_home', false)
            ->sortBy('sort_order')
            ->values();

        $hidden = $projects
            ->where('is_public', false)
            ->sortBy('name')
            ->values();

        return view('admin.home-projects.edit', [
            'featured' => $featured,
            'portfolio' => $portfolio,
            'hidden' => $hidden,
            'saveUrl' => route('admin.home-projects.update'),
        ]);
    }

    public function update(ReorderHomeProjectsRequest $request): JsonResponse
    {
        $featuredIds = $this->uniqueIds($request->validated('featured_ids'));
        $portfolioIds = $this->uniqueIds($request->validated('portfolio_ids'));
        $hiddenIds = $this->uniqueIds($request->validated('hidden_ids'));

        $seen = [];
        foreach ([$featuredIds, $portfolioIds, $hiddenIds] as $group) {
            foreach ($group as $id) {
                if (isset($seen[$id])) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'O mesmo projeto não pode estar em duas colunas.',
                    ], 422);
                }
                $seen[$id] = true;
            }
        }

        DB::transaction(function () use ($featuredIds, $portfolioIds, $hiddenIds) {
            foreach ($featuredIds as $index => $id) {
                Project::query()->whereKey($id)->update([
                    'is_public' => true,
                    'featured_on_home' => true,
                    'featured_sort' => ($index + 1) * 10,
                ]);
            }

            foreach ($portfolioIds as $index => $id) {
                Project::query()->whereKey($id)->update([
                    'is_public' => true,
                    'featured_on_home' => false,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            foreach ($hiddenIds as $id) {
                Project::query()->whereKey($id)->update([
                    'is_public' => false,
                    'featured_on_home' => false,
                ]);
            }
        });

        $this->audit->record('projects.home_reordered', null, [
            'summary' => 'Ordem e destaques da home',
            'featured_ids' => $featuredIds,
            'portfolio_ids' => $portfolioIds,
        ]);

        return response()->json([
            'ok' => true,
            'featured_ids' => $featuredIds,
            'portfolio_ids' => $portfolioIds,
            'hidden_ids' => $hiddenIds,
        ]);
    }

    /** @param  list<mixed>  $ids
     *  @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
