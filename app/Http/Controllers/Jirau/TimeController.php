<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauTimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauTimeService $time,
    ) {}

    public function toggle(Request $request, Jirau $jirau, JirauCard $card): RedirectResponse
    {
        $this->access->assertTrackTime($request->user(), $jirau);
        abort_unless((int) $card->jirau_id === (int) $jirau->id, 404);

        $result = $this->time->toggle($request->user(), $card);

        return redirect()
            ->back(fallback: route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->with('success', $result['message']);
    }
}
