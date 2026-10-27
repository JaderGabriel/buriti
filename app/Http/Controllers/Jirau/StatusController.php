<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\ToggleJirauActiveRequest;
use App\Models\Jirau;
use App\Services\AuditLogger;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauTimeService;
use Illuminate\Http\RedirectResponse;

class StatusController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private AuditLogger $audit,
        private JirauTimeService $time,
    ) {}

    public function update(ToggleJirauActiveRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertView($request->user(), $jirau);

        $active = $request->boolean('is_active');
        if (! $active) {
            $this->time->pauseOnBoard($jirau);
        }
        $jirau->forceFill(['is_active' => $active])->save();

        $this->audit->record('jirau.'.($active ? 'activated' : 'deactivated'), $jirau, [
            'summary' => $jirau->name,
        ]);

        return redirect()
            ->back(fallback: route('jirau.show', $jirau))
            ->with('success', $active
                ? 'Jirau «'.$jirau->name.'» está ativo e entra no quadro geral.'
                : 'Jirau «'.$jirau->name.'» ficou inativo — some do quadro geral (filtro Ativos).');
    }
}
