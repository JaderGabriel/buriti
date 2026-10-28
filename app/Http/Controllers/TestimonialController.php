<?php

namespace App\Http\Controllers;

use App\Enums\TestimonialStatus;
use App\Http\Requests\SubmitTestimonialRequest;
use App\Models\Testimonial;
use App\Services\TestimonialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TestimonialController extends Controller
{
    public function __construct(private TestimonialService $testimonials) {}

    public function write(string $token): Response
    {
        $testimonial = $this->testimonials->findOpenInvite($token);
        if ($testimonial === null) {
            return $this->unlistedView('testimonials.expired', [], 404);
        }

        $testimonial->load('contact', 'project');

        return $this->unlistedView('testimonials.form', [
            'testimonial' => $testimonial,
            'token' => $token,
            'defaultName' => TestimonialService::fallbackName($testimonial),
        ]);
    }

    public function submit(SubmitTestimonialRequest $request, string $token): RedirectResponse|Response
    {
        $testimonial = $this->testimonials->findOpenInvite($token);
        if ($testimonial === null) {
            return $this->unlistedView('testimonials.expired', [], 404);
        }

        $saved = $this->testimonials->submit($testimonial, $request->validated(), $request);

        return redirect()
            ->route('testimonials.thanks', $saved->uuid);
    }

    public function thanks(string $uuid): Response
    {
        $testimonial = $this->testimonialsFindSubmitted($uuid);
        if ($testimonial === null) {
            abort(404);
        }

        return $this->unlistedView('testimonials.thanks', [
            'testimonial' => $testimonial,
        ]);
    }

    public function seal(Request $request, ?string $code = null): Response
    {
        $code = strtoupper(trim((string) ($code ?: $request->query('code', ''))));
        $testimonial = $code !== '' ? $this->testimonials->findByVerificationCode($code) : null;
        $approved = $testimonial?->status === TestimonialStatus::Approved;
        $authenticity = $approved ? $testimonial->authenticity() : null;

        return $this->unlistedView('testimonials.seal', [
            'code' => $code,
            'testimonial' => $approved ? $testimonial->load('project') : null,
            'authenticity' => $authenticity,
        ]);
    }

    private function testimonialsFindSubmitted(string $uuid): ?Testimonial
    {
        return Testimonial::query()
            ->where('uuid', $uuid)
            ->whereNotNull('submitted_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function unlistedView(string $view, array $data = [], int $status = 200): Response
    {
        return response()
            ->view($view, $data, $status)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
