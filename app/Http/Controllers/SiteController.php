<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $projects = Project::query()
            ->public()
            ->ordered()
            ->take(9)
            ->get();

        return view('site.home', [
            'projects' => $projects,
            'services' => config('buriti.services', []),
            'expertise' => config('buriti.expertise', []),
        ]);
    }
}
