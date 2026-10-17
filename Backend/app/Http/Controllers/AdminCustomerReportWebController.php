<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ReportFilterRequest;
use App\Queries\Reporting\CustomerReportQuery;
use App\Queries\Reporting\CustomerWalletReportQuery;
use App\Queries\Reporting\LoyaltyReportQuery;
use App\Services\ReportCsvExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCustomerReportWebController extends Controller
{
    public function __construct(
        private readonly CustomerReportQuery $customerReportQuery,
        private readonly LoyaltyReportQuery $loyaltyReportQuery,
        private readonly CustomerWalletReportQuery $customerWalletReportQuery,
        private readonly ReportCsvExporter $csvExporter,
    ) {}

    public function customers(ReportFilterRequest $request): View
    {
        $filters = $request->toData('created_at');
        $report = $this->customerReportQuery->execute($filters);

        return view('admin.reports.customers', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function customersExport(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->toData('created_at');
        $report = $this->customerReportQuery->execute($filters);

        $rows = [];
        foreach ($report['customers']->items() as $customer) {
            $rows[] = [
                $customer->name,
                $customer->phone,
                $customer->orders_count,
                $customer->last_order_at ?? '',
                $customer->created_at->toDateTimeString(),
            ];
        }

        return $this->csvExporter->stream(
            'customers-report',
            ['الاسم', 'الهاتف', 'عدد الطلبات المؤكدة', 'آخر طلب', 'تاريخ التسجيل'],
            $rows,
            Auth::user(),
            'customer_report',
            ['range' => [$filters->range->startAt->toDateString(), $filters->range->endAt->toDateString()]],
        );
    }

    public function loyalty(ReportFilterRequest $request): View
    {
        $filters = $request->toData('created_at');
        $report = $this->loyaltyReportQuery->execute($filters);

        return view('admin.reports.loyalty-report', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function wallets(ReportFilterRequest $request): View
    {
        $filters = $request->toData('created_at');
        $report = $this->customerWalletReportQuery->execute($filters);

        return view('admin.reports.customer-wallets', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }
}
