<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\SyncColumnWatchersRequest;
use App\Models\Jirau;
use App\Models\JirauColumn;
use App\Services\Jirau\JirauAccess;
use Illuminate\Http\RedirectResponse;

class ColumnWatcherController extends Controller
{
    public function __construct(private JirauAccess $access) {}

    public function update(SyncColumnWatchersRequest $request, Jirau $jirau, JirauColumn $column): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertView($request->user(), $jirau);
        abort_unless((int) $column->jirau_id === (int) $jirau->id, 404);

        $ids = array_values(array_unique(array_map('intval', $request->validated('user_ids') ?? [])));
        $column->watchers()->sync($ids);

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Avisos da faixa «'.$column->name.'» atualizados.');
    }
}
