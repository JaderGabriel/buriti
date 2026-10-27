<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ProductRoadmap;
use Illuminate\View\View;

class RoadmapController extends Controller
{
    public function show(): View
    {
        return view('admin.roadmap.show', ProductRoadmap::page());
    }
}
