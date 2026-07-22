<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\ContactIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function __construct(private ContactIngestService $ingest) {}

    public function index(): View
    {
        $messages = ContactMessage::query()->with('contact')->latest()->paginate(15);

        return view('admin.messages.index', compact('messages'));
    }

    public function show(ContactMessage $message): View
    {
        $message->load('contact');
        $message->markAsRead();

        return view('admin.messages.show', compact('message'));
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

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
}
