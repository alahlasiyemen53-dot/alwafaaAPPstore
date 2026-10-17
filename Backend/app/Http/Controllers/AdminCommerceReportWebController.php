<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Currency;
use App\Queries\Reporting\MarketingReportQuery;
use App\Queries\Reporting\PurchasingReportQuery;
use App\Queries\Reporting\SalesReportQuery;
use App\Queries\Reporting\SalesReturnReportQuery;
use App\Services\ReportCsvExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCommerceReportWebController extends Controller
{
    public function __construct(
        private readonly SalesReportQuery $salesReportQuery,
        private readonly SalesReturnReportQuery $salesReturnReportQuery,
        private readonly MarketingReportQuery $marketingReportQuery,
        private readonly PurchasingReportQuery $purchasingReportQuery,
        private readonly ReportCsvExporter $csvExporter,
    ) {}

    public function sales(ReportFilterRequest $request): View
    {
        $filters = $request->toData('confirmed_at');
        $report = $this->salesReportQuery->execute($filters);

        return view('admin.reports.sales', [
            'filters' => $filters,
            'report' => $report,
            'currencies' => Currency::query()->where('is_active', true)->get(),
        ]);
    }

    public function salesExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('confirmed_at');
        $report = $this->salesReportQuery->execute($filters);

        return $this->csvExporter->stream(
            'sales-report',
            ['رقم الطلب', 'العميل', 'العملة', 'الإجمالي الفرعي', 'الخصم', 'الإجمالي', 'تاريخ التأكيد'],
            $this->salesReportQuery->executeForExport($filters),
            Auth::user(),
            'sales_report',
            ['range' => [$filters->range->startAt->toDateString(), $filters->range->endAt->toDateString()]],
        );
    }

    public function returns(ReportFilterRequest $request): View
    {
        $filters = $request->toData('posted_at');
        $report = $this->salesReturnReportQuery->execute($filters);

        return view('admin.reports.returns', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function returnsExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('posted_at');

        return $this->csvExporter->stream(
            'sales-returns-report',
            ['رقم المرتجع', 'الطلب', 'المستودع', 'العملة', 'الإجمالي', 'تاريخ الاعتماد'],
            $this->salesReturnReportQuery->executeForExport($filters),
            Auth::user(),
            'sales_return_report',
            ['range' => [$filters->range->startAt->toDateString(), $filters->range->endAt->toDateString()]],
        );
    }

    public function marketing(ReportFilterRequest $request): View
    {
        $filters = $request->toData('confirmed_at');
        $report = $this->marketingReportQuery->execute($filters);

        return view('admin.reports.marketing', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function purchasing(ReportFilterRequest $request): View
    {
        $filters = $request->toData('invoice_date');
        $report = $this->purchasingReportQuery->execute($filters);

        return view('admin.reports.purchasing', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function purchasingExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('invoice_date');

        return $this->csvExporter->stream(
            'purchasing-report',
            ['رقم الفاتورة', 'المورد', 'المستودع', 'العملة', 'الإجمالي', 'تاريخ الفاتورة'],
            $this->purchasingReportQuery->executeForExport($filters),
            Auth::user(),
            'purchasing_report',
            ['range' => [$filters->range->startAt->toDateString(), $filters->range->endAt->toDateString()]],
        );
    }
}
