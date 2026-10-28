<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunQaCommandRequest;
use App\Models\QaRun;
use App\Services\QaLabService;
use App\Support\QaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class QaLabController extends Controller
{
    public function __construct(private QaLabService $lab) {}

    public function index(): View
    {
        return view('admin.qa.index', [
            'groups' => QaCatalog::groups(),
            'runs' => QaRun::query()
                ->with('user:id,name')
                ->latest('id')
                ->limit(40)
                ->get(),
            'suiteRunning' => $this->lab->suiteIsRunning(),
        ]);
    }

    public function show(QaRun $run): View
    {
        $run->load('user:id,name');

        return view('admin.qa.show', [
            'run' => $run,
            'command' => QaCatalog::find($run->command),
        ]);
    }

    public function store(RunQaCommandRequest $request): RedirectResponse
    {
        $command = (string) $request->validated('command');

        try {
            $run = $this->lab->start($request->user(), $command);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage() !== '' ? $e->getMessage() : 'Não foi possível iniciar o teste.');
        }

        if (QaCatalog::isSuite($command)) {
            ignore_user_abort(true);
            $runId = $run->id;
            dispatch(function () use ($runId) {
                ignore_user_abort(true);
                set_time_limit(180);
                $queued = QaRun::query()->find($runId);
                if ($queued) {
                    app(QaLabService::class)->execute($queued);
                }
            })->afterResponse();

            return redirect()
                ->route('admin.qa.show', $run)
                ->with('success', 'Suíte a correr. Esta página actualiza sozinha.');
        }

        $run = $this->lab->execute($run);

        return redirect()
            ->route('admin.qa.show', $run)
            ->with($run->isPassed() ? 'success' : 'error', $run->summary ?: 'Teste concluído.');
    }
}
