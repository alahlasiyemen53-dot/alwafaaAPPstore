<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Queries\AdminInventoryQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminInventoryWebController extends Controller
{
    public function index(Request $request, AdminInventoryQuery $query): View
    {
        $inventories = $query->paginate($request->query(), perPage: 20);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.inventory.index', [
            'inventories' => $inventories,
            'warehouses' => $warehouses,
        ]);
    }
}
