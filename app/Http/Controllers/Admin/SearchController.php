<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CrmInboxService;
use App\Support\AdminNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(private CrmInboxService $inbox) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $navHits = AdminNavigation::search($q, $request->user());
        $crmHits = mb_strlen($q) >= 2 ? $this->inbox->search($q) : [];
        $hits = array_values(array_merge($navHits, $crmHits));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['q' => $q, 'hits' => $hits]);
        }

        return view('admin.search.index', [
            'q' => $q,
            'hits' => $hits,
        ]);
    }
}
