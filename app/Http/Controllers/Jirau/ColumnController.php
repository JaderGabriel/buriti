<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\ReorderColumnsRequest;
use App\Http\Requests\Jirau\StoreColumnRequest;
use App\Http\Requests\Jirau\UpdateColumnRequest;
use App\Models\Jirau;
use App\Models\JirauColumn;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ColumnController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauBoardService $boards,
    ) {}

    public function store(StoreColumnRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);

        $column = $this->boards->addColumn($jirau, $request->validated());

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Etapa «'.$column->name.'» adicionada ao quadro.');
    }

    public function update(UpdateColumnRequest $request, Jirau $jirau, JirauColumn $column): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);
        abort_unless((int) $column->jirau_id === (int) $jirau->id, 404);

        $data = $request->validated();
        $payload = [];
        foreach (['name', 'color', 'default_due_hours', 'overview_lane'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        if (array_key_exists('overview_lane', $payload) && $column->overviewLaneIsLocked()) {
            unset($payload['overview_lane']);
        }
        if ($payload !== []) {
            $column->update($payload);
        }

        if ($request->boolean('sync_watchers') || $request->exists('user_ids')) {
            $ids = array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])));
            $column->watchers()->sync($ids);
        }

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Etapa «'.$column->fresh()->name.'» atualizada.');
    }

    public function reorder(ReorderColumnsRequest $request, Jirau $jirau): JsonResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);

        foreach ($request->validated('ordered_ids') as $index => $id) {
            JirauColumn::query()
                ->whereKey($id)
                ->where('jirau_id', $jirau->id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }

        return response()->json(['ok' => true]);
    }
}
