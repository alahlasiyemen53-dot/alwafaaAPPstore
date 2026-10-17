<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Models\User;

/**
 * Composes the domain report queries into dashboard KPI cards, charts, and
 * deterministic alerts. Only invokes a domain query when the viewer holds
 * the corresponding report permission. No new business truth is created
 * here; every figure traces back to an existing authoritative query.
 */
final class ManagementDashboardQuery
{
    public function __construct(
        private readonly SalesReportQuery $salesReportQuery,
        private readonly InventoryReportQuery $inventoryReportQuery,
        private readonly DeliveryReportQuery $deliveryReportQuery,
        private readonly DriverCodReportQuery $driverCodReportQuery,
        private readonly FinancialIntegrityQuery $financialIntegrityQuery,
        private readonly CustomerReportQuery $customerReportQuery,
        private readonly LoyaltyReportQuery $loyaltyReportQuery,
        private readonly CustomerWalletReportQuery $customerWalletReportQuery,
    ) {}

    /**
     * @return array{
     *     filters: ReportFilterData,
     *     kpis: array<string, mixed>,
     *     charts: array<string, mixed>,
     *     alerts: array<int, array{level: 'info'|'warning'|'danger', message: string}>
     * }
     */
    public function execute(ReportFilterData $filters, User $viewer): array
    {
        $kpis = [];
        $charts = [];
        $alerts = [];

        if ($viewer->hasPermission('admin.reports.sales.view')) {
            $sales = $this->salesReportQuery->execute($filters);
            $kpis['sales'] = $sales['summary'];
            $charts['sales_trend'] = $sales['trend'];
        }

        if ($viewer->hasPermission('admin.reports.inventory.view')) {
            $inventory = $this->inventoryReportQuery->execute($filters);
            $kpis['inventory'] = $inventory['summary'];
            if ($inventory['summary']['out_of_stock_count'] > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'message' => sprintf('يوجد %d موقف مخزون بدون كمية متاحة (نفاد مخزون).', $inventory['summary']['out_of_stock_count']),
                ];
            }
        }

        if ($viewer->hasPermission('admin.reports.delivery.view')) {
            $delivery = $this->deliveryReportQuery->execute($filters);
            $kpis['delivery'] = [
                'status_counts' => $delivery['status_counts'],
                'success_rate' => $delivery['success_rate'],
            ];
        }

        if ($viewer->hasPermission('admin.reports.driver_cod.view')) {
            $cod = $this->driverCodReportQuery->execute($filters);
            $kpis['driver_cod'] = $cod['totals_by_currency'];
            foreach ($cod['totals_by_currency'] as $row) {
                if ((float) $row['outstanding'] > 0) {
                    $alerts[] = [
                        'level' => 'info',
                        'message' => sprintf('عهدة COD غير مسواة (%s %s) بانتظار تسوية المناديب.', $row['outstanding'], $row['currency_code']),
                    ];
                }
            }
        }

        if ($viewer->hasPermission('admin.reports.customers.view')) {
            $kpis['customers'] = $this->customerReportQuery->execute($filters)['summary'];
        }

        if ($viewer->hasPermission('admin.reports.loyalty.view')) {
            $kpis['loyalty'] = $this->loyaltyReportQuery->execute($filters)['activity'];
        }

        if ($viewer->hasPermission('admin.reports.customer_wallets.view')) {
            $kpis['customer_wallets'] = $this->customerWalletReportQuery->execute($filters)['liability_by_currency'];
        }

        if ($viewer->hasPermission('admin.reports.finance.view') || $viewer->hasPermission('admin.reports.treasury.view')) {
            $integrity = $this->financialIntegrityQuery->execute($filters->range);
            $kpis['integrity'] = $integrity;

            if (! $integrity['trial_balance']['has_open_period']) {
                $alerts[] = ['level' => 'danger', 'message' => 'لا توجد فترة محاسبية مفتوحة حالياً.'];
            }
            if (! $integrity['trial_balance']['is_balanced']) {
                $alerts[] = ['level' => 'danger', 'message' => 'ميزان المراجعة غير متوازن.'];
            }
            if ($integrity['treasury']['status'] === 'ISSUES') {
                $alerts[] = ['level' => 'danger', 'message' => 'يوجد خلل في مطابقة عملة حسابات الخزينة.'];
            }
            if ($integrity['cod']['status'] === 'DISCREPANCY_FOUND') {
                $alerts[] = ['level' => 'warning', 'message' => 'يوجد خلل في مطابقة عهد المناديب.'];
            }
            if ($integrity['inventory']['status'] === 'ISSUES') {
                $alerts[] = ['level' => 'warning', 'message' => 'يوجد خلل في مطابقة حركات المخزون.'];
            }
            if ($integrity['wallet']['status'] === 'ISSUES') {
                $alerts[] = ['level' => 'danger', 'message' => 'رصيد محافظ العملاء لا يطابق الأستاذ العام.'];
            }
            if ($integrity['coupons']['status'] === 'ISSUES') {
                $alerts[] = ['level' => 'warning', 'message' => 'يوجد خلل في مطابقة استخدام الكوبونات.'];
            }
        }

        return [
            'filters' => $filters,
            'kpis' => $kpis,
            'charts' => $charts,
            'alerts' => $alerts,
        ];
    }
}
