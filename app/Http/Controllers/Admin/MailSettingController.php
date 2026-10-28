<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Http\Requests\Admin\UpdateMailSettingsRequest;
use App\Services\MailSettingsService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private MailSettingsService $mail,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.mail', [
            'form' => $this->mail->formValues(),
            'mail' => $this->mail->snapshot(),
            'defaultTo' => $this->settings->get('contact_email') ?: auth()->user()?->email,
        ]);
    }

    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('mail_password');
        $data['mail_port'] = $request->filled('mail_port')
            ? (string) $request->integer('mail_port')
            : null;

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

        return redirect()
            ->route('admin.settings.mail')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
