<?php

namespace App\Http\Controllers\Jirau;

use App\Enums\JirauOverviewLane;
use App\Http\Controllers\Controller;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauOverviewService $overview,
    ) {}

    public function __invoke(Request $request): View
    {
        $this->access->assertManage($request->user());

        $estado = (string) $request->query('estado', 'ativos');
        $snapshot = $this->overview->snapshot($estado);

        return view('jirau.overview', [
            'jirau' => null,
            'isAdmin' => true,
            'estado' => $snapshot['estado'],
            'boardCount' => $snapshot['boards'],
            'lanes' => $snapshot['lanes'],
            'laneDefs' => JirauOverviewLane::cases(),
        ]);
    }
}
