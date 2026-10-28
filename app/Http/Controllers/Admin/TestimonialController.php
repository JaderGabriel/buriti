<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TestimonialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteTestimonialRequest;
use App\Http\Requests\Admin\LinkTestimonialProjectRequest;
use App\Http\Requests\Admin\ModerateTestimonialRequest;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Testimonial;
use App\Services\TestimonialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function __construct(private TestimonialService $testimonials) {}

    public function index(Request $request): View
    {
        $testimonials = Testimonial::query()
            ->with(['contact.clientCompany', 'project', 'invitedBy', 'moderatedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('original_body', 'like', $term)
                        ->orWhere('published_body', 'like', $term)
                        ->orWhere('verification_code', 'like', $term)
                        ->orWhere('original_display_name', 'like', $term)
                        ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.testimonials.index', [
            'testimonials' => $testimonials,
            'statuses' => TestimonialStatus::options(),
            'statusCounts' => Testimonial::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.testimonials.create', [
            'contacts' => Contact::query()->orderBy('name')->limit(400)->get(['id', 'name', 'email', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedContactId' => $request->integer('contact_id') ?: null,
            'selectedProjectId' => $request->integer('project_id') ?: null,
        ]);
    }

    public function store(InviteTestimonialRequest $request): RedirectResponse
    {
        $contact = Contact::query()->findOrFail($request->integer('contact_id'));
        $project = $request->filled('project_id')
            ? Project::query()->findOrFail($request->integer('project_id'))
            : null;

        $invite = $this->testimonials->invite($contact, $request->user(), $project);

        return redirect()
            ->route('admin.testimonials.show', $invite['testimonial'])
            ->with('success', 'Link gerado. Copie e envie ao cliente — o sistema não dispara e-mail.')
            ->with('invite_url', $invite['url']);
    }

    public function show(Testimonial $testimonial): View
    {
        $testimonial->load(['contact.clientCompany', 'project', 'company', 'invitedBy', 'moderatedBy']);

        return view('admin.testimonials.show', [
            'testimonial' => $testimonial,
            'authenticity' => $testimonial->authenticity(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'inviteUrl' => session('invite_url'),
        ]);
    }

    public function moderate(ModerateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        if ($testimonial->status === TestimonialStatus::Archived) {
            return redirect()
                ->route('admin.testimonials.show', $testimonial)
                ->with('error', 'Este depoimento está arquivado. A mediação está encerrada.');
        }

        $status = TestimonialStatus::from($request->string('decision')->toString());

        $this->testimonials->moderate($testimonial, $request->user(), $status, [
            'use_original' => $request->boolean('use_original'),
            'published_stars' => $request->integer('published_stars') ?: null,
            'published_body' => $request->input('published_body'),
            'published_display_name' => $request->input('published_display_name'),
            'moderation_notes' => $request->input('moderation_notes'),
        ]);

        return redirect()
            ->route('admin.testimonials.show', $testimonial)
            ->with('success', 'Mediação salva. O selo do resultado foi recalculado; o original do cliente permanece intacto.');
    }

    public function project(LinkTestimonialProjectRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $project = $request->filled('project_id')
            ? Project::query()->findOrFail($request->integer('project_id'))
            : null;

        $this->testimonials->linkProject($testimonial, $project);

        return redirect()
            ->route('admin.testimonials.show', $testimonial)
            ->with('success', $project
                ? 'Projeto vinculado. O selo do resultado inclui agora essa referência.'
                : 'Projeto desvinculado.');
    }

    public function reinvite(Testimonial $testimonial): RedirectResponse
    {
        $invite = $this->testimonials->resealInvite($testimonial);

        return redirect()
            ->route('admin.testimonials.show', $testimonial)
            ->with('success', 'Novo link gerado. Copie e envie ao cliente — o anterior deixa de funcionar.')
            ->with('invite_url', $invite['url']);
    }
}
