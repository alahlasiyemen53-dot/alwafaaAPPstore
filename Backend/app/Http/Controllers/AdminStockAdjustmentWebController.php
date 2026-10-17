<?php

namespace App\Http\Controllers;

use App\Actions\CreateStockAdjustmentAction;
use App\Actions\DeleteStockAdjustmentAction;
use App\Actions\PostStockAdjustmentAction;
use App\Actions\UpdateStockAdjustmentAction;
use App\Data\StockAdjustmentData;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockAdjustmentStatus;
use App\Http\Requests\Admin\StoreStockAdjustmentRequest;
use App\Http\Requests\Admin\UpdateStockAdjustmentRequest;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Queries\AdminStockAdjustmentQuery;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminStockAdjustmentWebController extends Controller
{
    public function index(Request $request, AdminStockAdjustmentQuery $query): View
    {
        $adjustments = $query->paginate($request->query(), perPage: 20);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.stock_adjustments.index', [
            'adjustments' => $adjustments,
            'warehouses' => $warehouses,
            'reasons' => StockAdjustmentReason::cases(),
        ]);
    }

    public function create(): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $variants = ProductVariant::query()->with('product')->orderBy('id')->get();

        return view('admin.stock_adjustments.create', [
            'warehouses' => $warehouses,
            'variants' => $variants,
            'reasons' => StockAdjustmentReason::cases(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, CreateStockAdjustmentAction $action): RedirectResponse
    {
        try {
            $adjustment = $action->execute(StockAdjustmentData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-adjustments.show', $adjustment)
            ->with('success', "تم إنشاء مسودة أمر التسوية رقم {$adjustment->adjustment_number} بنجاح.");
    }

    public function show(StockAdjustment $adjustment): View
    {
        $adjustment->loadMissing([
            'warehouse',
            'postedBy',
            'createdBy',
            'updatedBy',
            'items.productVariant.product',
        ]);

        return view('admin.stock_adjustments.show', [
            'adjustment' => $adjustment,
        ]);
    }

    public function edit(StockAdjustment $adjustment): View|RedirectResponse
    {
        if ($adjustment->status === StockAdjustmentStatus::Posted) {
            return redirect()->route('admin.stock-adjustments.show', $adjustment)
                ->with('error', 'لا يمكن تعديل أمر تسوية تم ترحيله مسبقاً.');
        }

        $adjustment->loadMissing(['items.productVariant.product', 'warehouse']);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $variants = ProductVariant::query()->with('product')->orderBy('id')->get();

        return view('admin.stock_adjustments.edit', [
            'adjustment' => $adjustment,
            'warehouses' => $warehouses,
            'variants' => $variants,
            'reasons' => StockAdjustmentReason::cases(),
        ]);
    }

    public function update(
        UpdateStockAdjustmentRequest $request,
        StockAdjustment $adjustment,
        UpdateStockAdjustmentAction $action
    ): RedirectResponse {
        try {
            $updated = $action->execute($adjustment, StockAdjustmentData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-adjustments.show', $updated)
            ->with('success', "تم تحديث أمر التسوية رقم {$updated->adjustment_number} بنجاح.");
    }

    public function destroy(StockAdjustment $adjustment, DeleteStockAdjustmentAction $action): RedirectResponse
    {
        $number = $adjustment->adjustment_number;
        try {
            $action->execute($adjustment);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-adjustments.index')
            ->with('success', "تم حذف أمر التسوية {$number} بنجاح.");
    }

    public function post(StockAdjustment $adjustment, PostStockAdjustmentAction $action): RedirectResponse
    {
        try {
            $posted = $action->execute($adjustment);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-adjustments.show', $posted)
            ->with('success', "تم ترحيل أمر التسوية رقم {$posted->adjustment_number} وتعديل المخزون بنجاح.");
    }
}
