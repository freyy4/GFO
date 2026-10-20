<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Support\DashboardAccess;
use App\Support\DashboardData;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request, DashboardData $data): View
    {
        $domain = $request->domain();
        Gate::authorize('view-dashboard', $domain);
        $available = array_filter(DashboardAccess::LABELS, fn ($label, $key) => Gate::allows('view-dashboard', $key), ARRAY_FILTER_USE_BOTH);

        return view('dashboard', $data->build($request->user(), $domain, $request->validated(), $request->timezone()) + compact('available'));
    }
}
