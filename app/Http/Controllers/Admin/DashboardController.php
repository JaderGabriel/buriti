<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CrmActivityType;
use App\Enums\IdeaNoteColor;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\IdeaNote;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Services\CrmInboxService;
use App\Services\FunnelMetricsService;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private CrmInboxService $inbox,
        private FunnelMetricsService $funnel,
    ) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id') ?: null;

        return view('admin.dashboard', [
            'companyId' => $companyId,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name', 'trade_name']),
            'inbox' => $this->inbox->nextActions($companyId),
            'funnel' => $this->funnel->snapshot($companyId),
            'unreadMessages' => ContactMessage::query()->unread()->count(),
            'totalMessages' => ContactMessage::query()->count(),
            'projectsCount' => Project::query()->count(),
            'openTasksCount' => Task::query()->open()->count(),
            'contactsCount' => Contact::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->count(),
            'openOpportunities' => Opportunity::query()
                ->open()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->count(),
            'opportunityStageCounts' => Opportunity::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->selectRaw('stage, count(*) as total')
                ->groupBy('stage')
                ->pluck('total', 'stage'),
            'recentMessages' => ContactMessage::query()->latest()->take(6)->get(),
            'recentContacts' => Contact::query()
                ->with('clientCompany')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->latest()
                ->take(6)
                ->get(),
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
            'recentActivities' => $this->recentContactActivities($companyId),
            'activityTypes' => CrmActivityType::options(),
            'pickerContacts' => Contact::query()
                ->with('clientCompany:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'company', 'company_id', 'phone', 'email', 'status']),
            'openTasks' => Task::query()
                ->where(function ($query) {
                    $query->open()->orWhere(function ($done) {
                        $done->where('status', TaskStatus::Done)->whereNotNull('contact_id');
                    });
                })
                ->orderBy('title')
                ->limit(120)
                ->get(),
            'leadsCount' => Contact::query()->leads()->count(),
            'tasksDueSoon' => Task::query()
                ->open()
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now()->addDay())
                ->count(),
            'ideaNotes' => IdeaNote::query()
                ->with(['user', 'company', 'contact'])
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->get(),
            'ideaColors' => IdeaNoteColor::options(),
            'ideaCompanies' => Company::query()->orderBy('name')->get(['id', 'name', 'trade_name']),
            'ideaContacts' => Contact::query()
                ->with('clientCompany:id,name,trade_name')
                ->orderBy('name')
                ->get(['id', 'name', 'company_id', 'company']),
            'ideaReorderUrl' => route('admin.idea-notes.reorder'),
            'googleCalendarSrc' => $this->settings->calendarSrc(),
        ]);
    }

    /** @return EloquentCollection<int, CrmActivity> */
    private function recentContactActivities(?int $companyId): EloquentCollection
    {
        return CrmActivity::query()
            ->with(['contact.clientCompany', 'user', 'task', 'opportunity'])
            ->whereNotNull('contact_id')
            ->when($companyId, fn ($q) => $q->whereHas('contact', fn ($c) => $c->where('company_id', $companyId)))
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }
}
