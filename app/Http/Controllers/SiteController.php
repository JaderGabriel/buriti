<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\SettingService;
use App\Services\TestimonialService;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private TestimonialService $testimonials,
    ) {}

    public function home(): View
    {
        $projects = Project::query()
            ->public()
            ->ordered()
            ->take(24)
            ->get();

        $homeTestimonials = $this->testimonials->publicApproved();
        $quotesByProject = $homeTestimonials
            ->filter(fn ($quote) => $quote->project_id)
            ->groupBy(fn ($quote) => (int) $quote->project_id);

        $projects->each(function (Project $project) use ($quotesByProject): void {
            $project->setRelation(
                'approvedPublicTestimonials',
                $quotesByProject->get($project->id, collect())->values(),
            );
        });

        $featured = $projects->where('featured_on_home', true)->values();

        return view('site.home', [
            'projects' => $projects,
            'featuredProjects' => $featured,
            'openSourceProjects' => $projects->where('repo_is_private', false)->where('featured_on_home', false)->values(),
            'privateRepoProjects' => $projects->where('repo_is_private', true)->where('featured_on_home', false)->values(),
            'homeTestimonials' => $homeTestimonials,
            'services' => config('buriti.services', []),
            'expertise' => config('buriti.expertise', []),
            'method' => config('buriti.method', []),
            'team' => config('buriti.team', []),
        ]);
    }

    public function privacy(): View
    {
        return view('site.legal.privacy', [
            'updatedAt' => '28/10/2026',
            'privacyEmail' => $this->privacyEmail(),
        ]);
    }

    public function cookies(): View
    {
        return view('site.legal.cookies', [
            'updatedAt' => '28/10/2026',
            'privacyEmail' => $this->privacyEmail(),
        ]);
    }

    private function privacyEmail(): string
    {
        return (string) ($this->settings->get('contact_email')
            ?: config('buriti.contact.email')
            ?: config('mail.from.address')
            ?: 'jadergabriel8@gmail.com');
    }
}
