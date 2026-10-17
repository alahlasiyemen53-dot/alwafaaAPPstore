<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\User;
use App\Queries\Reporting\ManagementDashboardQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AdminManagementDashboardWebController extends Controller
{
    public function __construct(
        private readonly ManagementDashboardQuery $dashboardQuery,
    ) {}

    public function __invoke(ReportFilterRequest $request): View
    {
        $filters = $request->toData();

        /** @var User $viewer */
        $viewer = Auth::user();

        $dashboard = $this->dashboardQuery->execute($filters, $viewer);

        return view('admin.dashboard', [
            'profile' => $viewer->load('roles'),
            'dashboard' => $dashboard,
        ]);
    }
}
