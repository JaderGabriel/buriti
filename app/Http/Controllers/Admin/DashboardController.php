<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CrmActivityType;
use App\Enums\IdeaNoteColor;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\IdeaNote;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
                ->with([
                    'project',
                    'activities' => fn ($q) => $q->limit(1),
                ])
                ->open()
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->take(6)
                ->get(),
            'recentActivities' => $this->recentContactActivities(),
            'activityTypes' => CrmActivityType::options(),
            'pickerContacts' => Contact::query()
                ->with('clientCompany:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'company', 'company_id', 'phone', 'email', 'status']),
            'openTasks' => Task::query()->open()->orderBy('title')->limit(80)->get(),
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

    /** @return EloquentCollection<int, CrmActivity> */
    private function recentContactActivities(): EloquentCollection
    {
        return CrmActivity::query()
            ->with(['contact.clientCompany', 'user', 'task', 'opportunity'])
            ->whereNotNull('contact_id')
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }
}
