<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Task;

class CrmInboxService
{
    /**
     * @return array{
     *     unlinked_messages: \Illuminate\Database\Eloquent\Collection<int, ContactMessage>,
     *     overdue_tasks: \Illuminate\Database\Eloquent\Collection<int, Task>,
     *     stale_opportunities: \Illuminate\Database\Eloquent\Collection<int, Opportunity>,
     *     meet_missing: \Illuminate\Database\Eloquent\Collection<int, Task>,
     *     google_unsynced: \Illuminate\Database\Eloquent\Collection<int, Task>
     * }
     */
    public function nextActions(?int $companyId = null, int $staleDays = 7): array
    {
        $messages = ContactMessage::query()
            ->with('contact')
            ->where(function ($q) {
                $q->whereNull('contact_id')->orWhereNull('read_at');
            })
            ->when($companyId, function ($q) use ($companyId) {
                $q->where(function ($inner) use ($companyId) {
                    $inner->whereHas('contact', fn ($c) => $c->where('company_id', $companyId))
                        ->orWhereNull('contact_id');
                });
            })
            ->latest()
            ->limit(8)
            ->get();

        $overdue = Task::query()
            ->with(['contact', 'project'])
            ->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->when($companyId, fn ($q) => $q->whereHas('contact', fn ($c) => $c->where('company_id', $companyId)))
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $cutoff = now()->subDays($staleDays);
        $stale = Opportunity::query()
            ->with(['contact.clientCompany', 'clientCompany'])
            ->whereIn('stage', [
                OpportunityStage::Qualified->value,
                OpportunityStage::Proposal->value,
                OpportunityStage::Negotiation->value,
            ])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDoesntHave('activities', fn ($q) => $q->where('happened_at', '>=', $cutoff))
            ->orderBy('updated_at')
            ->limit(8)
            ->get();

        $meetMissing = Task::query()
            ->with(['contact'])
            ->open()
            ->where('want_meet', true)
            ->where(function ($q) {
                $q->whereNull('meet_url')->orWhere('meet_url', '');
            })
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        $unsynced = Task::query()
            ->open()
            ->whereNotNull('due_at')
            ->where(function ($q) {
                $q->whereNull('google_event_id')->orWhere('google_event_id', '');
            })
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        return [
            'unlinked_messages' => $messages,
            'overdue_tasks' => $overdue,
            'stale_opportunities' => $stale,
            'meet_missing' => $meetMissing,
            'google_unsynced' => $unsynced,
        ];
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string, url: string}>
     */
    public function search(string $term, int $limit = 12): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $hits = [];

        Contact::query()
            ->with('clientCompany')
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhereHas('clientCompany', fn ($c) => $c->where('name', 'like', $like));
            })
            ->limit($limit)
            ->get()
            ->each(function (Contact $contact) use (&$hits) {
                $hits[] = [
                    'type' => 'contato',
                    'id' => $contact->id,
                    'title' => $contact->name,
                    'subtitle' => trim(($contact->email ?: '').' · '.($contact->companyLabel() ?: ''), ' ·'),
                    'url' => route('admin.contacts.show', $contact),
                ];
            });

        Company::query()
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('trade_name', 'like', $like);
            })
            ->limit(6)
            ->get()
            ->each(function (Company $company) use (&$hits) {
                $hits[] = [
                    'type' => 'empresa',
                    'id' => $company->id,
                    'title' => $company->displayName(),
                    'subtitle' => $company->document ?: 'Empresa',
                    'url' => route('admin.companies.show', $company),
                ];
            });

        Opportunity::query()
            ->with('contact')
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->limit(8)
            ->get()
            ->each(function (Opportunity $opportunity) use (&$hits) {
                $hits[] = [
                    'type' => 'oportunidade',
                    'id' => $opportunity->id,
                    'title' => $opportunity->title,
                    'subtitle' => $opportunity->contact?->name ?? 'Sem contato',
                    'url' => route('admin.opportunities.edit', $opportunity),
                ];
            });

        CrmActivity::query()
            ->with('contact')
            ->where(function ($q) use ($like) {
                $q->where('subject', 'like', $like)->orWhere('body', 'like', $like);
            })
            ->limit(6)
            ->get()
            ->each(function (CrmActivity $activity) use (&$hits) {
                if (! $activity->contact) {
                    return;
                }
                $hits[] = [
                    'type' => 'atividade',
                    'id' => $activity->id,
                    'title' => $activity->subject ?: $activity->type->label(),
                    'subtitle' => $activity->contact->name,
                    'url' => route('admin.contacts.activities.edit', [$activity->contact, $activity]),
                ];
            });

        return array_slice($hits, 0, $limit);
    }
}
