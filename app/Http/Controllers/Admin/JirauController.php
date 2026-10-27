<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJirauRequest;
use App\Models\Project;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Http\RedirectResponse;

class JirauController extends Controller
{
    public function __construct(private JirauBoardService $boards) {}

    public function store(StoreJirauRequest $request, Project $project): RedirectResponse
    {
        $jirau = $this->boards->createForProject(
            $project,
            $request->user(),
            $request->boardName(),
            $request->template(),
        );

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Jirau «'.$jirau->name.'» criado com o modelo '.$jirau->template->label().'.');
    }
}
