<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\MoveCardRequest;
use App\Http\Requests\Jirau\StoreCardRequest;
use App\Http\Requests\Jirau\StoreChecklistItemRequest;
use App\Http\Requests\Jirau\StoreCommentRequest;
use App\Http\Requests\Jirau\StoreDemandRequest;
use App\Http\Requests\Jirau\UpdateCardRequest;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauChecklistItem;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauBoardService;
use App\Services\Jirau\JirauEvidenceService;
use App\Services\Jirau\JirauNotifier;
use App\Services\Jirau\JirauStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauBoardService $boards,
        private JirauNotifier $notifier,
        private JirauEvidenceService $evidence,
    ) {}

    public function storeDemand(StoreDemandRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertWritable($request->user(), $jirau);

        $card = $this->boards->createDemand($jirau, $request->user(), $request->validated());
        $this->attachEvidence($request, $card);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Demanda enviada. O admin do quadro foi notificado para posicionar e estimar.');
    }

    public function store(StoreCardRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);

        $card = $this->boards->createCard($jirau, $request->user(), $request->validated());
        $this->attachEvidence($request, $card);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Cartão criado no Jirau.');
    }

    public function update(UpdateCardRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);

        $this->boards->updateCard($card, $request->user(), $request->validated());

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Cartão atualizado.');
    }

    public function move(MoveCardRequest $request, Jirau $jirau, JirauCard $card): JsonResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->access->assertManage($request->user());

        $data = $request->validated();
        [$moved, $tracking] = $this->boards->moveCard(
            $card,
            $request->user(),
            (int) $data['column_id'],
            $data['ordered_ids'] ?? [],
        );

        app(JirauStepService::class)
            ->syncCompletionFromCard($moved->fresh(['column', 'projectStep']));

        $moved = $moved->fresh();

        return response()->json([
            'ok' => true,
            'id' => $moved->id,
            'column_id' => $moved->column_id,
            'due_at' => $moved->due_at?->timezone(config('app.timezone'))?->format('d/m'),
            'due_past' => (bool) $moved->due_at?->isPast(),
            'tracking' => $tracking,
        ]);
    }

    public function archive(Request $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->access->assertManage($request->user());

        $this->boards->archiveCard($card, $request->user());

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Cartão arquivado.');
    }

    public function comment(StoreCommentRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->syncDrawerCard($request, $card);

        $comment = $card->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $this->notifier->commentFromMember($jirau, $card, $comment, $request->user());

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Comentário publicado.');
    }

    public function storeChecklist(StoreChecklistItemRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->syncDrawerCard($request, $card);

        $max = (int) $card->checklistItems()->max('sort_order');
        $card->checklistItems()->create([
            'title' => $request->itemTitle(),
            'sort_order' => $max + 10,
        ]);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);
    }

    public function toggleChecklist(Request $request, Jirau $jirau, JirauCard $card, JirauChecklistItem $item): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        abort_unless((int) $item->card_id === (int) $card->id, 404);

        $item->update(['is_done' => ! $item->is_done]);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);
    }

    public function destroyChecklist(Request $request, Jirau $jirau, JirauCard $card, JirauChecklistItem $item): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->access->assertManage($request->user());
        abort_unless((int) $item->card_id === (int) $card->id, 404);

        $item->delete();

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);
    }

    private function syncDrawerCard(Request $request, JirauCard $card): void
    {
        if ($request->input('save_card') !== '1' || ! $request->filled('title')) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            return;
        }

        if (! $user->is_admin && (! $card->isInbox() || (int) $card->created_by !== (int) $user->id)) {
            return;
        }

        foreach (['assigned_to', 'estimate', 'due_at'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $this->boards->updateCard(
            $card,
            $user,
            $request->validate(UpdateCardRequest::rulesFor($user)),
        );
    }

    private function attachEvidence(Request $request, JirauCard $card): void
    {
        $this->evidence->attachPhotos($card, $request->user(), JirauEvidenceService::filesFromRequest($request));
        $driveUrls = array_values(array_filter(array_map(
            'trim',
            preg_split('/\R+/', (string) $request->input('drive_url', '')) ?: [],
        )));
        if ($driveUrls !== []) {
            $this->evidence->attachLinks($card, $request->user(), $driveUrls);
        }
    }

    private function assertCard(Request $request, Jirau $jirau, JirauCard $card): void
    {
        $this->access->assertWritable($request->user(), $jirau);
        abort_unless((int) $card->jirau_id === (int) $jirau->id, 404);
    }
}
