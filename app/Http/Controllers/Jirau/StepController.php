<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\AttachCardStepRequest;
use App\Http\Requests\Jirau\StoreBoardStepRequest;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauStepService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StepController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauStepService $steps,
    ) {}

    public function store(StoreBoardStepRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertView($request->user(), $jirau);

        $data = $request->validated();
        $data['create_card'] = $request->boolean('create_card', true);

        $step = $this->steps->createStep($jirau, $request->user(), $data);

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Etapa «'.$step->title.'» criada no projeto'.($data['create_card'] ? ' e junta a este Jirau.' : '.'));
    }

    public function import(Request $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertView($request->user(), $jirau);

        $count = $this->steps->importUnlinkedSteps($jirau, $request->user());

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', $count === 0
                ? 'Não havia etapas soltas para juntar a este quadro.'
                : $count.' etapa'.($count === 1 ? '' : 's').' junta'.($count === 1 ? '' : 's').' ao Jirau.');
    }

    public function attach(AttachCardStepRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->access->assertManage($request->user());

        $stepId = (int) ($request->validated('step_id') ?? 0);
        if ($stepId > 0) {
            $project = $jirau->project;
            abort_unless($project !== null, 404);
            $step = $project->steps()->whereKey($stepId)->firstOrFail();
            $this->steps->attach($card, $step);
            $message = 'Etapa junta a este cartão.';
        } else {
            $this->steps->exportCard($card, $request->user());
            $message = 'Cartão enviado às etapas do projeto.';
        }

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', $message);
    }

    public function detach(Request $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->access->assertManage($request->user());

        $this->steps->detach($card);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Etapa desvinculada deste cartão.');
    }

    private function assertCard(Request $request, Jirau $jirau, JirauCard $card): void
    {
        $this->access->assertView($request->user(), $jirau);
        abort_unless((int) $card->jirau_id === (int) $jirau->id, 404);
    }
}
