<?php

namespace App\Http\Controllers;

use App\Actions\CreateStockTransferAction;
use App\Actions\DeleteStockTransferAction;
use App\Actions\PostStockTransferAction;
use App\Actions\UpdateStockTransferAction;
use App\Data\StockTransferData;
use App\Enums\StockTransferStatus;
use App\Http\Requests\Admin\StoreStockTransferRequest;
use App\Http\Requests\Admin\UpdateStockTransferRequest;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Queries\AdminStockTransferQuery;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminStockTransferWebController extends Controller
{
    public function index(Request $request, AdminStockTransferQuery $query): View
    {
        $transfers = $query->paginate($request->query(), perPage: 20);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.stock_transfers.index', [
            'transfers' => $transfers,
            'warehouses' => $warehouses,
        ]);
    }

    public function create(): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $variants = ProductVariant::query()
            ->with(['product'])
            ->orderBy('id')
            ->get();

        return view('admin.stock_transfers.create', [
            'warehouses' => $warehouses,
            'variants' => $variants,
        ]);
    }

    public function store(StoreStockTransferRequest $request, CreateStockTransferAction $action): RedirectResponse
    {
        try {
            $transfer = $action->execute(StockTransferData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-transfers.show', $transfer)
            ->with('success', "تم إنشاء مسودة أمر التحويل رقم {$transfer->transfer_number} بنجاح.");
    }

    public function show(StockTransfer $transfer): View
    {
        $transfer->loadMissing([
            'fromWarehouse',
            'toWarehouse',
            'postedBy',
            'createdBy',
            'updatedBy',
            'items.productVariant.product',
        ]);

        return view('admin.stock_transfers.show', [
            'transfer' => $transfer,
        ]);
    }

    public function edit(StockTransfer $transfer): View|RedirectResponse
    {
        if ($transfer->status === StockTransferStatus::Posted) {
            return redirect()->route('admin.stock-transfers.show', $transfer)
                ->with('error', 'لا يمكن تعديل أمر تحويل تم ترحيله مسبقاً.');
        }

        $transfer->loadMissing(['items.productVariant.product', 'fromWarehouse', 'toWarehouse']);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $variants = ProductVariant::query()->with('product')->orderBy('id')->get();

        return view('admin.stock_transfers.edit', [
            'transfer' => $transfer,
            'warehouses' => $warehouses,
            'variants' => $variants,
        ]);
    }

    public function update(
        UpdateStockTransferRequest $request,
        StockTransfer $transfer,
        UpdateStockTransferAction $action
    ): RedirectResponse {
        try {
            $updated = $action->execute($transfer, StockTransferData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-transfers.show', $updated)
            ->with('success', "تم تحديث أمر التحويل رقم {$updated->transfer_number} بنجاح.");
    }

    public function destroy(StockTransfer $transfer, DeleteStockTransferAction $action): RedirectResponse
    {
        $number = $transfer->transfer_number;
        try {
            $action->execute($transfer);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-transfers.index')
            ->with('success', "تم حذف أمر التحويل {$number} بنجاح.");
    }

    public function post(StockTransfer $transfer, PostStockTransferAction $action): RedirectResponse
    {
        try {
            $posted = $action->execute($transfer);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-transfers.show', $posted)
            ->with('success', "تم ترحيل أمر التحويل رقم {$posted->transfer_number} وتحديث أرصدة المستودعات بنجاح.");
    }
}
