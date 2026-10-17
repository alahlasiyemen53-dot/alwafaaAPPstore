<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateDriverAction;
use App\Actions\ToggleDriverStatusAction;
use App\Actions\UpdateDriverAction;
use App\Enums\DeliveryStatus;
use App\Http\Requests\Admin\StoreDriverRequest;
use App\Http\Requests\Admin\UpdateDriverRequest;
use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminDriverWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = Driver::query()
            ->withCount('deliveries')
            ->latest('id');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('driver_code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('vehicle_plate_number', 'like', "%{$term}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($vehicleType = $request->input('vehicle_type')) {
            $query->where('vehicle_type', $vehicleType);
        }

        $drivers = $query->paginate(15)->withQueryString();

        $metrics = [
            'total' => Driver::query()->count(),
            'active' => Driver::query()->where('is_active', true)->count(),
            'inactive' => Driver::query()->where('is_active', false)->count(),
            'delivering' => Delivery::query()->where('status', DeliveryStatus::Assigned)->distinct('driver_id')->count('driver_id'),
        ];

        return view('admin.drivers.index', [
            'drivers' => $drivers,
            'metrics' => $metrics,
            'filters' => $request->only(['search', 'status', 'vehicle_type']),
        ]);
    }

    public function create(): View
    {
        return view('admin.drivers.create');
    }

    public function store(StoreDriverRequest $request, CreateDriverAction $action): RedirectResponse
    {
        $driver = $action->execute($request->toDto());

        return redirect()->route('admin.drivers.index')
            ->with('success', "تم إضافة مندوب التوصيل بنجاح برمز ({$driver->driver_code})");
    }

    public function edit(Driver $driver): View
    {
        return view('admin.drivers.edit', [
            'driver' => $driver,
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver, UpdateDriverAction $action): RedirectResponse
    {
        $action->execute($driver, $request->toDto());

        return redirect()->route('admin.drivers.index')
            ->with('success', "تم تحديث بيانات المندوب ({$driver->name}) بنجاح");
    }

    public function toggleStatus(Driver $driver, ToggleDriverStatusAction $action): RedirectResponse
    {
        $action->execute($driver);

        $statusMessage = $driver->fresh()->is_active ? 'تفعيل' : 'إلغاء تفعيل';

        return back()->with('success', "تم {$statusMessage} المندوب ({$driver->name}) بنجاح");
    }
}
