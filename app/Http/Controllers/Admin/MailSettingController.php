<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Http\Requests\Admin\UpdateMailSettingsRequest;
use App\Models\User;
use App\Services\MailSettingsService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private MailSettingsService $mail,
    ) {}

    public function edit(Request $request): View
    {
        $this->authorizeMailAdmin($request->user());

        return view('admin.settings.mail', [
            'form' => $this->mail->formValues(),
            'mail' => $this->mail->snapshot(),
            'defaultTo' => $this->settings->get('contact_email') ?: auth()->user()?->email,
        ]);
    }

    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['mail_password', 'mail_autosave_drafts', 'mail_autosave_seconds']);
        $data['mail_port'] = $request->filled('mail_port')
            ? (string) $request->integer('mail_port')
            : null;
        $data['mail_imap_port'] = $request->filled('mail_imap_port')
            ? (string) $request->integer('mail_imap_port')
            : null;
        if ($request->exists('mail_autosave_drafts') || $request->exists('mail_autosave_seconds')) {
            $data['mail_autosave_drafts'] = $request->boolean('mail_autosave_drafts') ? '1' : '0';
            $seconds = $request->integer('mail_autosave_seconds');
            $data['mail_autosave_seconds'] = (string) (in_array($seconds, SettingService::MAIL_AUTOSAVE_INTERVALS, true) ? $seconds : 30);
        }

        $this->settings->putMany($data);

        $password = $request->input('mail_password');
        if (is_string($password) && trim($password) !== '') {
            $this->settings->putSecret('mail_password', trim($password));
        }

        $this->mail->apply();

        return redirect()
            ->route('admin.settings.mail')
            ->with('success', 'Configuração de e-mail salva. Convites de depoimento continuam só com link para copiar.');
    }

    public function test(SendTestMailRequest $request): RedirectResponse
    {
        $result = $this->mail->trySendTest(
            $request->string('to')->toString(),
            $request->user(),
        );

        $flash = 'success';
        if (! $result['ok']) {
            $flash = 'error';
        } elseif (str_contains($result['message'], 'cópia IMAP')) {
            $flash = 'warning';
        }

        return redirect()
            ->route('admin.settings.mail')
            ->with($flash, $result['message']);
    }

    public function inbox(Request $request): RedirectResponse
    {
        $this->authorizeMailAdmin($request->user());

        $preview = $this->mail->inboxPreview($request->user());

        return redirect()
            ->route('admin.settings.mail')
            ->with($preview['ok'] ? 'success' : 'error', $preview['message'])
            ->with('mail_inbox', $preview['messages']);
    }

    private function authorizeMailAdmin(?User $user): void
    {
        abort_unless($user?->can('manageMail') === true, 403, 'O serviço de e-mail é exclusivo do admin.');
    }
}
