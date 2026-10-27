<?php

namespace App\Http\Controllers\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Enums\JirauTemplate;
use App\Http\Controllers\Controller;
use App\Models\Jirau;
use App\Models\Project;
use App\Models\User;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauMetricsService $metrics,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->access->assertManage($user);

        $filters = $this->metrics->filtersFrom($request);
        $selectedBoard = $filters['quadro']
            ? Jirau::query()->with('project:id,name')->find($filters['quadro'])
            : null;

        $boardOptions = Jirau::query()
            ->withoutDemo()
            ->with('project:id,name')
            ->when($filters['projeto'], fn ($q) => $q->where('project_id', $filters['projeto']))
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'is_active']);

        return view('jirau.analytics', [
            'jirau' => $selectedBoard && ! $selectedBoard->isDemo() ? $selectedBoard : null,
            'isAdmin' => true,
            'metrics' => $this->metrics->snapshot($user, $filters),
            'filters' => $filters,
            'types' => JirauCardType::options(),
            'priorities' => JirauCardPriority::options(),
            'templates' => JirauTemplate::options(),
            'projects' => Project::query()
                ->whereHas('jiraus', fn ($q) => $q->withoutDemo())
                ->orderBy('name')
                ->get(['id', 'name']),
            'boardOptions' => $boardOptions,
            'people' => User::assignableOnJirau()->get(['id', 'name', 'jirau_role']),
        ]);
    }

    public function show(Request $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());

        return redirect()->route('jirau.analytics', array_filter([
            'quadro' => $jirau->id,
            'projeto' => $request->query('projeto'),
            'usuario' => $request->query('usuario'),
            'modelo' => $request->query('modelo'),
            'estado' => $request->query('estado'),
            'tipo' => $request->query('tipo'),
            'prioridade' => $request->query('prioridade'),
            'risco' => $request->query('risco'),
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
