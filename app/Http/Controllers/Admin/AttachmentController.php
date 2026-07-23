<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachments) {}

    public function store(StoreAttachmentRequest $request, string $type, int $id): RedirectResponse
    {
        $attachable = $this->resolveAttachable($type, $id);
        $kind = (string) $request->validated('kind', 'document');

        $this->assertKindAllowed($attachable, $kind);

        $this->attachments->store(
            $attachable,
            $request->file('file'),
            $kind,
            $request->user()?->id,
            $request->validated('title'),
        );

        return back()->with('success', 'Ficheiro adicionado.');
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->attachments->delete($attachment);

        return back()->with('success', 'Ficheiro removido.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    private function resolveAttachable(string $type, int $id): Model
    {
        $model = match ($type) {
            'contacts' => Contact::query()->findOrFail($id),
            'opportunities' => Opportunity::query()->findOrFail($id),
            'tasks' => Task::query()->findOrFail($id),
            'projects' => Project::query()->findOrFail($id),
            'users' => User::query()->findOrFail($id),
            default => abort(404),
        };

        abort_unless(method_exists($model, 'attachments'), 404);

        return $model;
    }

    private function assertKindAllowed(Model $attachable, string $kind): void
    {
        $allowed = match (true) {
            $attachable instanceof User => ['photo'],
            $attachable instanceof Project => ['document', 'media', 'photo'],
            $attachable instanceof Contact,
            $attachable instanceof Opportunity,
            $attachable instanceof Task => ['document'],
            default => [],
        };

        abort_unless(in_array($kind, $allowed, true), 422, 'Tipo de anexo não permitido.');
    }
}
