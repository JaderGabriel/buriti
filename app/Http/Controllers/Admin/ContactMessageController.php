<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use App\Services\ContactIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function __construct(
        private ContactIngestService $ingest,
        private AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return $this->messenger();
    }

    public function show(ContactMessage $message): View
    {
        $message->load('contact');
        $wasUnread = $message->isUnread();
        $message->markAsRead();

        if ($wasUnread) {
            $this->audit->record('message.read', $message, [
                'summary' => $message->subject,
                'email' => $message->email,
            ]);
        }

        return $this->messenger($message);
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $summary = $message->subject;
        $email = $message->email;
        $message->delete();

        $this->audit->record('message.deleted', null, [
            'summary' => $summary,
            'email' => $email,
        ]);

        return redirect()
            ->route('admin.messages.index')
            ->with('success', 'Mensagem removida.');
    }

    public function linkContact(ContactMessage $message): RedirectResponse
    {
        $contact = $this->ingest->linkOrCreateFromMessage($message);

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato vinculado à mensagem.');
    }

    private function messenger(?ContactMessage $selected = null): View
    {
        $messages = ContactMessage::query()
            ->with('contact')
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'selected' => $selected,
            'unreadCount' => ContactMessage::query()->unread()->count(),
        ]);
    }
}
