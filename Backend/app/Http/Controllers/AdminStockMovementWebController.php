<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Warehouse;
use App\Queries\AdminStockMovementQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminStockMovementWebController extends Controller
{
    public function index(Request $request, AdminStockMovementQuery $query): View
    {
        $movements = $query->paginate($request->query(), perPage: 25);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.stock_movements.index', [
            'movements' => $movements,
            'warehouses' => $warehouses,
            'movementTypes' => StockMovementType::cases(),
            'directions' => StockMovementDirection::cases(),
        ]);
    }
}
