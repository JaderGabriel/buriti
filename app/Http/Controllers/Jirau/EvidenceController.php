<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\StoreCardLinkRequest;
use App\Http\Requests\Jirau\StoreCardPhotoRequest;
use App\Models\Attachment;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauCardLink;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauEvidenceService $evidence,
    ) {}

    public function storePhotos(StoreCardPhotoRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->evidence->assertManage($request->user(), $card);
        $this->evidence->attachPhotos($card, $request->user(), JirauEvidenceService::filesFromRequest($request));

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Fotos adicionadas ao cartão.');
    }

    public function storeLink(StoreCardLinkRequest $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->evidence->assertManage($request->user(), $card);
        $this->evidence->attachLinks($card, $request->user(), [(string) $request->validated('drive_url')]);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Link do Drive adicionado.');
    }

    public function destroyPhoto(Request $request, Jirau $jirau, JirauCard $card, Attachment $attachment): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->evidence->assertManage($request->user(), $card);
        $this->evidence->deletePhoto($card, $attachment, $request->user());

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Foto removida.');
    }

    public function destroyLink(Request $request, Jirau $jirau, JirauCard $card, JirauCardLink $link): RedirectResponse
    {
        $this->assertCard($request, $jirau, $card);
        $this->evidence->assertManage($request->user(), $card);
        $this->evidence->deleteLink($card, $link);

        return redirect()
            ->route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id])
            ->with('success', 'Link removido.');
    }

    public function showPhoto(Request $request, Jirau $jirau, JirauCard $card, Attachment $attachment): StreamedResponse
    {
        $this->assertCard($request, $jirau, $card, write: false);
        abort_unless((int) $attachment->attachable_id === (int) $card->id, 404);
        abort_unless($attachment->attachable_type === $card->getMorphClass(), 404);
        abort_unless($attachment->kind === 'photo', 404);
        abort_if($attachment->trashed(), 404);
        abort_unless($attachment->existsOnDisk(), 404);
        abort_unless($attachment->isImage(), 415);

        $filename = str_replace(['"', '\\', "\r", "\n"], '', basename((string) $attachment->original_name) ?: 'print.jpg');

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $filename,
            [
                'Content-Type' => $attachment->mime_type ?: 'image/jpeg',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ],
        );
    }

    private function assertCard(Request $request, Jirau $jirau, JirauCard $card, bool $write = true): void
    {
        if ($write) {
            $this->access->assertWritable($request->user(), $jirau);
        } else {
            $this->access->assertView($request->user(), $jirau);
        }
        abort_unless((int) $card->jirau_id === (int) $jirau->id, 404);
        $card->loadMissing('column');
    }
}
