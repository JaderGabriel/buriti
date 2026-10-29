<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailMarker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnotateMailboxRequest;
use App\Http\Requests\Admin\BulkMailboxRequest;
use App\Http\Requests\Admin\SendMailboxMessageRequest;
use App\Http\Requests\Admin\UpdateMailboxSignatureRequest;
use App\Models\ContactMessage;
use App\Models\MailLabel;
use App\Models\MailRecord;
use App\Models\User;
use App\Services\CrmMailboxService;
use App\Services\MailAnnotationService;
use App\Services\MailSettingsService;
use App\Support\MailContacts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MailboxController extends Controller
{
    public function __construct(
        private CrmMailboxService $mailbox,
        private MailAnnotationService $annotate,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeMailAdmin($request->user());

        $folder = (string) $request->query('pasta', 'INBOX');
        $uid = $request->integer('uid') ?: null;
        $page = max(1, $request->integer('page', 1));
        $query = trim((string) $request->query('buscar', ''));

        $workspace = $this->mailbox->workspace($folder, $page, $uid, $query !== '' ? $query : null);

        return view('admin.mailbox.index', [
            'workspace' => $workspace,
            'mode' => 'read',
            'compose' => null,
            'query' => $query,
            'catalog' => $this->annotate->catalog(),
        ]);
    }

    public function compose(Request $request): View
    {
        $this->authorizeMailAdmin($request->user());

        $workspace = $this->mailbox->workspace('INBOX', 1, null, null);
        $reply = null;
        $replyFolder = (string) $request->query('responder_pasta', '');
        $replyUid = $request->integer('responder_uid');

        if ($replyFolder !== '' && $replyUid > 0) {
            $replyWorkspace = $this->mailbox->workspace($replyFolder, 1, $replyUid, null);
            $reply = $replyWorkspace['selected'];
        }

        $to = (string) $request->query('para', '');
        $cc = '';
        $subject = (string) $request->query('assunto', '');
        $modo = (string) $request->query('modo', 'new');
        $body = '';
        $inReplyTo = null;
        $contactMessageId = $request->integer('mensagem') ?: null;

        if ($contactMessageId) {
            $contactMessage = ContactMessage::query()->find($contactMessageId);
            if ($contactMessage) {
                $to = $to !== '' ? $to : (string) $contactMessage->email;
                $subject = $subject !== '' ? $subject : $this->replySubject((string) $contactMessage->subject);
                $body = $this->quoteContactMessage($contactMessage);
                $modo = 'contact-reply';
            } else {
                $contactMessageId = null;
            }
        }

        if (is_array($reply)) {
            $inReplyTo = $reply['message_id'] ?: null;
            $from = $this->extractEmail((string) $reply['from']);
            if ($modo === 'forward') {
                $subject = $subject !== '' ? $subject : 'Enc: '.$reply['subject'];
                $body = $this->quoteForward($reply);
            } else {
                $own = strtolower((string) ($workspace['identity']['address'] ?? ''));
                $to = $to !== '' ? $to : $from;
                $subject = $subject !== '' ? $subject : $this->replySubject((string) $reply['subject']);
                $body = $this->quoteReply($reply);
                if ($modo === 'reply-all') {
                    $peers = $this->replyAllAddresses($reply, $own);
                    $to = implode(', ', $peers['to']);
                    $cc = implode(', ', $peers['cc']);
                }
            }
        }

        return view('admin.mailbox.index', [
            'workspace' => $workspace,
            'mode' => 'compose',
            'compose' => [
                'to' => $to,
                'cc' => $cc,
                'bcc' => '',
                'subject' => $subject,
                'body' => $body,
                'in_reply_to' => $inReplyTo,
                'reply_folder' => $replyFolder,
                'reply_uid' => $replyUid > 0 ? $replyUid : null,
                'contact_message_id' => $contactMessageId,
                'modo' => $modo,
            ],
            'query' => '',
            'catalog' => $this->annotate->catalog(),
        ]);
    }

    public function send(SendMailboxMessageRequest $request): RedirectResponse
    {
        $to = $request->string('to')->toString();
        $subject = $request->string('subject')->toString();
        $body = $request->string('body')->toString();
        $withSignature = $request->boolean('signature', true);
        $files = $request->uploadedAttachments();

        try {
            if ($request->boolean('draft')) {
                $folder = $this->mailbox->saveDraft($to, $subject, $body, $request->user(), $withSignature, $files);

                return redirect()
                    ->route('admin.mailbox.index', ['pasta' => $folder])
                    ->with('success', 'Rascunho guardado em '.$folder.'.');
            }

            $sentFolder = $this->mailbox->send(
                $to,
                $subject,
                $body,
                $request->user(),
                $withSignature,
                $request->input('cc'),
                $request->input('in_reply_to'),
                $request->input('reply_folder'),
                $request->integer('reply_uid') ?: null,
                $request->input('bcc'),
                $files,
            );

            $contactMessageId = $request->integer('contact_message_id');
            if ($contactMessageId > 0) {
                ContactMessage::query()->find($contactMessageId)?->markAsRead();
            }

            $flash = 'Mensagem enviada para '.$to.'.';
            $archive = $this->mailbox->lastSentArchive();
            if (is_string($sentFolder) && $sentFolder !== '') {
                $flash .= ' Cópia em '.$sentFolder.'.';
            } elseif (is_string($archive['error'] ?? null) && $archive['error'] !== '') {
                $flash .= ' A cópia em Enviados falhou: '.$archive['error'];
            }

            return redirect()
                ->route('admin.mailbox.index', ['pasta' => $sentFolder ?: 'INBOX'])
                ->with(is_string($sentFolder) && $sentFolder !== '' ? 'success' : 'warning', $flash);
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeMailAdmin($request->user());

        $folder = (string) $request->input('pasta', 'INBOX');
        $uid = $request->integer('uid');

        try {
            $this->mailbox->destroy($folder, $uid, $request->user());

            return redirect()
                ->route('admin.mailbox.index', ['pasta' => $folder])
                ->with('success', 'Mensagem enviada para o Lixo.');
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }
    }

    public function move(Request $request): RedirectResponse
    {
        $this->authorizeMailAdmin($request->user());

        $folder = (string) $request->input('pasta', 'INBOX');
        $uid = $request->integer('uid');
        $destination = (string) $request->input('destino', '');

        try {
            $this->mailbox->move($folder, $uid, $destination, $request->user());

            return redirect()
                ->route('admin.mailbox.index', ['pasta' => $destination])
                ->with('success', 'Mensagem movida.');
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }
    }

    public function seen(Request $request): RedirectResponse
    {
        $this->authorizeMailAdmin($request->user());

        $folder = (string) $request->input('pasta', 'INBOX');
        $uid = $request->integer('uid');

        try {
            $this->mailbox->toggleSeen($folder, $uid, $request->boolean('seen', true), $request->user());
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.mailbox.index', ['pasta' => $folder, 'uid' => $uid]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $this->authorizeMailAdmin($request->user());

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return back()->with('error', 'Indica o nome da pasta.');
        }

        try {
            $this->mailbox->createFolder($name, $request->user());

            return redirect()
                ->route('admin.mailbox.index', ['pasta' => $name])
                ->with('success', 'Pasta criada.');
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }
    }

    public function signature(UpdateMailboxSignatureRequest $request): RedirectResponse
    {
        $this->mailbox->saveSignature((string) $request->input('mail_signature', ''), $request->user());

        return back()->with('success', 'Assinatura guardada. Entra nas mensagens novas a seguir ao texto.');
    }

    public function contacts(Request $request): JsonResponse
    {
        $this->authorizeMailAdmin($request->user());

        return response()->json([
            'contacts' => MailContacts::search($request->string('q')->toString()),
        ]);
    }

    public function attachment(Request $request): Response
    {
        $this->authorizeMailAdmin($request->user());

        $folder = (string) $request->query('pasta', 'INBOX');
        $uid = $request->integer('uid');
        $index = $request->integer('i');

        try {
            $file = $this->mailbox->attachment($folder, $uid, $index, $request->user());
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);
            abort(404, $e->getMessage());
        }

        $name = $file['name'] !== '' ? $file['name'] : 'anexo';

        return response($file['content'], 200, [
            'Content-Type' => $file['type'] ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $name).'"',
        ]);
    }

    public function bulk(BulkMailboxRequest $request): RedirectResponse
    {
        $folder = $request->string('pasta')->toString();
        $uids = $request->uids();
        $acao = $request->string('acao')->toString();
        $admin = $request->user();

        try {
            match ($acao) {
                'star' => $this->mailbox->setFlag($folder, $uids, '\\Flagged', true, $admin),
                'unstar' => $this->mailbox->setFlag($folder, $uids, '\\Flagged', false, $admin),
                'seen' => $this->mailbox->setFlag($folder, $uids, '\\Seen', true, $admin),
                'unseen' => $this->mailbox->setFlag($folder, $uids, '\\Seen', false, $admin),
                'destroy' => $this->mailbox->destroyMany($folder, $uids, $admin),
                'move' => $this->mailbox->moveMany($folder, $uids, (string) $request->input('destino'), $admin),
                'marker' => $this->applyMarker($folder, $uids, $admin, $request->input('marker')),
                'label' => $this->applyLabel($folder, $uids, $admin, $request->integer('label_id')),
                default => null,
            };
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        $flash = match ($acao) {
            'star' => 'Mensagens marcadas com estrela.',
            'unstar' => 'Estrela removida.',
            'seen' => 'Mensagens marcadas como lidas.',
            'unseen' => 'Mensagens marcadas como não lidas.',
            'destroy' => 'Mensagens enviadas para o Lixo.',
            'move' => 'Mensagens movidas.',
            'marker' => 'Marcador actualizado.',
            'label' => 'Etiqueta aplicada.',
            default => 'Acção aplicada.',
        };

        $redirectFolder = $acao === 'move' ? (string) $request->input('destino') : $folder;

        return redirect()
            ->route('admin.mailbox.index', ['pasta' => $redirectFolder])
            ->with('success', $flash);
    }

    public function marker(AnnotateMailboxRequest $request): RedirectResponse
    {
        try {
            $record = $this->recordFrom($request);
            $value = trim((string) $request->input('marker', ''));
            $this->annotate->setMarker($record, MailMarker::tryFrom($value));
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marcador actualizado.');
    }

    public function label(AnnotateMailboxRequest $request): RedirectResponse
    {
        try {
            if (filled($request->input('name'))) {
                $this->annotate->createLabel(
                    (string) $request->input('name'),
                    (string) $request->input('color', 'slate'),
                );

                return back()->with('success', 'Etiqueta criada.');
            }

            $record = $this->recordFrom($request);
            $label = MailLabel::query()->findOrFail($request->integer('label_id'));
            if ($request->boolean('detach_label')) {
                $this->annotate->detachLabel($record, $label);

                return back()->with('success', 'Etiqueta removida.');
            }
            $this->annotate->attachLabel($record, $label);
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Etiqueta aplicada.');
    }

    public function link(AnnotateMailboxRequest $request): RedirectResponse
    {
        try {
            $record = $this->recordFrom($request);
            $this->annotate->addLink(
                $record,
                (string) $request->input('kind'),
                $request->integer('linkable_id'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'E-mail ligado no CRM (só visível no admin).');
    }

    public function unlink(AnnotateMailboxRequest $request): RedirectResponse
    {
        try {
            $record = $this->recordFrom($request);
            $this->annotate->removeLink($record, $request->integer('link_id'));
        } catch (Throwable $e) {
            $this->rethrowAuthorization($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Vínculo removido.');
    }

    private function recordFrom(AnnotateMailboxRequest $request): MailRecord
    {
        $this->authorizeMailAdmin($request->user());

        return $this->annotate->remember($this->mailboxAccount(), [
            'folder' => $request->string('pasta')->toString(),
            'uid' => $request->integer('uid'),
            'message_id' => $request->input('message_id'),
            'subject' => $request->input('subject'),
            'from' => $request->input('from'),
        ]);
    }

    /**
     * @param  list<int>  $uids
     */
    private function applyMarker(string $folder, array $uids, User $admin, mixed $marker): void
    {
        $value = is_string($marker) ? trim($marker) : '';
        $enum = MailMarker::tryFrom($value);
        $account = $this->mailboxAccount();
        foreach ($this->mailbox->headersFor($folder, $uids, $admin) as $message) {
            $this->annotate->setMarker($this->annotate->remember($account, $message), $enum);
        }
    }

    /**
     * @param  list<int>  $uids
     */
    private function applyLabel(string $folder, array $uids, User $admin, int $labelId): void
    {
        $label = MailLabel::query()->findOrFail($labelId);
        $account = $this->mailboxAccount();
        foreach ($this->mailbox->headersFor($folder, $uids, $admin) as $message) {
            $this->annotate->attachLabel($this->annotate->remember($account, $message), $label);
        }
    }

    private function mailboxAccount(): string
    {
        app(MailSettingsService::class)->apply();

        return (string) config('mail.from.address');
    }

    private function extractEmail(string $from): string
    {
        $emails = CrmMailboxService::extractAddresses($from);

        return $emails[0] ?? trim($from);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{to: list<string>, cc: list<string>}
     */
    private function replyAllAddresses(array $message, string $own): array
    {
        $from = CrmMailboxService::extractAddresses((string) ($message['from'] ?? ''));
        $to = CrmMailboxService::extractAddresses((string) ($message['to'] ?? ''));
        $cc = CrmMailboxService::extractAddresses((string) ($message['cc'] ?? ''));
        $own = strtolower($own);
        $exclude = array_filter([$own, ...$from]);
        $copied = array_values(array_diff([...$to, ...$cc], $exclude));

        return [
            'to' => $from !== [] ? $from : $to,
            'cc' => $copied,
        ];
    }

    private function quoteContactMessage(ContactMessage $message): string
    {
        $when = $message->created_at?->format('d/m/Y H:i') ?: '';
        $quoted = trim((string) $message->message);
        $quoted = $quoted !== '' ? preg_replace('/^/m', '> ', $quoted) : '> (sem conteúdo)';

        return "\n\n\nEm ".$when.', '.$message->name.' <'.$message->email."> escreveu:\n".$quoted;
    }

    private function replySubject(string $subject): string
    {
        $subject = trim($subject);

        return preg_match('/^re:\s/i', $subject) === 1 ? $subject : 'Re: '.$subject;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function quoteReply(array $message): string
    {
        $quoted = $this->plainBody($message);

        return "\n\n\nEm ".$message['date'].', '.$message['from']." escreveu:\n"
            .preg_replace('/^/m', '> ', $quoted);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function quoteForward(array $message): string
    {
        return "\n\n---------- Mensagem encaminhada ----------\n"
            .'De: '.$message['from']."\n"
            .'Data: '.$message['date']."\n"
            .'Assunto: '.$message['subject']."\n"
            .'Para: '.$message['to']."\n\n"
            .$this->plainBody($message);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function plainBody(array $message): string
    {
        if (($message['text'] ?? '') !== '') {
            return (string) $message['text'];
        }

        return trim(html_entity_decode(strip_tags((string) ($message['html'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function authorizeMailAdmin(?User $user): void
    {
        abort_unless($user?->can('manageMail') === true, 403, 'O serviço de e-mail é exclusivo do admin.');
    }

    private function rethrowAuthorization(Throwable $e): void
    {
        if ($e instanceof HttpException || $e instanceof AuthorizationException) {
            throw $e;
        }
    }
}
