<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildDashboardData;
use App\Http\Requests\DashboardRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly BuildDashboardData $buildDashboardData) {}

    /**
     * Render the fleet overview for the requested time range.
     */
    public function __invoke(DashboardRequest $request): Response
    {
        return Inertia::render(
            'Dashboard',
            $this->buildDashboardData->handle($request->range()),
        );
    }
}
