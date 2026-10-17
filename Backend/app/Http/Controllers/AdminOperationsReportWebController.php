<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ReportFilterRequest;
use App\Queries\Reporting\DeliveryReportQuery;
use App\Queries\Reporting\DriverCodReportQuery;
use App\Queries\Reporting\InventoryReportQuery;
use App\Services\ReportCsvExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminOperationsReportWebController extends Controller
{
    public function __construct(
        private readonly InventoryReportQuery $inventoryReportQuery,
        private readonly DeliveryReportQuery $deliveryReportQuery,
        private readonly DriverCodReportQuery $driverCodReportQuery,
        private readonly ReportCsvExporter $csvExporter,
    ) {}

    public function inventory(ReportFilterRequest $request): View
    {
        $filters = $request->toData('id');
        $report = $this->inventoryReportQuery->execute($filters);

        return view('admin.reports.inventory', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function inventoryExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('id');

        return $this->csvExporter->stream(
            'inventory-report',
            ['المستودع', 'المنتج', 'SKU', 'الكمية', 'المحجوز', 'المتاح'],
            $this->inventoryReportQuery->executeForExport($filters),
            Auth::user(),
            'inventory_report',
            ['warehouse_id' => $filters->warehouseId],
        );
    }

    public function delivery(ReportFilterRequest $request): View
    {
        $filters = $request->toData('id');
        $report = $this->deliveryReportQuery->execute($filters);

        return view('admin.reports.delivery', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function driverCod(ReportFilterRequest $request): View
    {
        $filters = $request->toData('driver_name');
        $report = $this->driverCodReportQuery->execute($filters);

        return view('admin.reports.driver-cod', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function driverCodExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('driver_name');
        $report = $this->driverCodReportQuery->execute($filters);

        $rows = [];
        foreach ($report['drivers']->items() as $row) {
            $rows[] = [$row['driver_name'], $row['currency_code'], $row['collected'], $row['settled'], $row['outstanding']];
        }

        return $this->csvExporter->stream(
            'driver-cod-report',
            ['المندوب', 'العملة', 'المحصل', 'المسوى', 'المتبقي'],
            $rows,
            Auth::user(),
            'driver_cod_report',
            ['driver_id' => $filters->driverId],
        );
    }
}
