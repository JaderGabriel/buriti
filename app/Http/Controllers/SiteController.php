<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\SettingService;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function home(): View
    {
        $projects = Project::query()
            ->public()
            ->ordered()
            ->take(18)
            ->get();

        return view('site.home', [
            'projects' => $projects,
            'openSourceProjects' => $projects->where('repo_is_private', false)->values(),
            'privateRepoProjects' => $projects->where('repo_is_private', true)->values(),
            'services' => config('buriti.services', []),
            'expertise' => config('buriti.expertise', []),
            'method' => config('buriti.method', []),
            'team' => config('buriti.team', []),
        ]);
    }

    public function privacy(): View
    {
        return view('site.legal.privacy', [
            'updatedAt' => '22/07/2026',
            'privacyEmail' => $this->privacyEmail(),
        ]);
    }

    public function cookies(): View
    {
        return view('site.legal.cookies', [
            'updatedAt' => '22/07/2026',
            'privacyEmail' => $this->privacyEmail(),
        ]);
    }

    private function privacyEmail(): string
    {
        return (string) ($this->settings->get('contact_email') ?: 'contato@buriti.dev.br');
    }
}
