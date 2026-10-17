<?php

namespace App\Http\Controllers;

use App\Data\AdminWishlistFilterData;
use App\Http\Requests\Admin\AdminWishlistFilterRequest;
use App\Queries\AdminWishlistQuery;
use Illuminate\Contracts\View\View;

final class AdminWishlistWebController extends Controller
{
    /**
     * Display a paginated, filterable listing of customer wishlist items.
     * Guaranteed strictly read-only for admin staff.
     */
    public function index(AdminWishlistFilterRequest $request, AdminWishlistQuery $query): View
    {
        $filters = AdminWishlistFilterData::fromValidated($request->validated());
        $items = $query->paginate($filters);
        $metrics = $query->getMetrics();

        return view('admin.wishlists.index', [
            'items' => $items,
            'metrics' => $metrics,
            'filters' => $request->validated(),
        ]);
    }
}
