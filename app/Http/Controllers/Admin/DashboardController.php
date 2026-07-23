<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\IdeaNote;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Enums\IdeaNoteColor;
use App\Services\SettingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'unreadMessages' => ContactMessage::query()->unread()->count(),
            'totalMessages' => ContactMessage::query()->count(),
            'projectsCount' => Project::query()->count(),
            'openTasks' => Task::query()->open()->count(),
            'contactsCount' => Contact::query()->count(),
            'openOpportunities' => Opportunity::query()->open()->count(),
            'opportunityStageCounts' => Opportunity::query()
                ->selectRaw('stage, count(*) as total')
                ->groupBy('stage')
                ->pluck('total', 'stage'),
            'recentMessages' => ContactMessage::query()->latest()->take(6)->get(),
            'recentContacts' => Contact::query()->with('clientCompany')->latest()->take(6)->get(),
            'upcomingTasks' => Task::query()
                ->with('project')
                ->open()
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->take(6)
                ->get(),
            'leadsCount' => Contact::query()->leads()->count(),
            'tasksDueSoon' => Task::query()
                ->open()
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now()->addDay())
                ->count(),
            'ideaNotes' => IdeaNote::query()
                ->with('user')
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->get(),
            'ideaColors' => IdeaNoteColor::options(),
            'googleCalendarSrc' => $this->settings->calendarSrc(),
        ]);
    }
}
