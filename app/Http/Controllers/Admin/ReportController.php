<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProductDailyMetric;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function statistics(): View
    {
        return view('admin.statistics.index', ['metrics' => ProductDailyMetric::with('product')->where('date', '>=', today()->subDays(29))->orderByDesc('date')->paginate(30)]);
    }

    public function activity(): View
    {
        return view('admin.activity.index', ['entries' => ActivityLog::with('user')->latest('id')->paginate(30)]);
    }

    public function activityShow(ActivityLog $entry): View
    {
        return view('admin.activity.show', ['entry' => $entry->load('user')]);
    }
}
