<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DriverCodStatementService
{
    /**
     * @return array{
     *     driver: Driver,
     *     currency_id: int|null,
     *     start_date: string|null,
     *     end_date: string|null,
     *     total_debit: float,
     *     total_credit: float,
     *     ending_balance: float,
     *     lines: Collection<int, array{
     *         date: string,
     *         type: string,
     *         type_label: string,
     *         reference: string,
     *         notes: string,
     *         debit: float,
     *         credit: float,
     *         balance: float,
     *         status: string,
     *         status_badge: string
     *     }>
     * }
     */
    public function generate(int $driverId, ?int $currencyId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        /** @var Driver $driver */
        $driver = Driver::query()->findOrFail($driverId);

        // 1. Fetch DELIVERED COD Deliveries (Debit: Driver owes company)
        $deliveriesQuery = Delivery::query()
            ->with(['order', 'codCurrency'])
            ->where('driver_id', $driverId)
            ->where('status', DeliveryStatus::Delivered)
            ->where('cod_collected_amount', '>', 0);

        if ($currencyId) {
            $deliveriesQuery->where('cod_currency_id', $currencyId);
        }

        if ($startDate) {
            $deliveriesQuery->where('delivered_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $deliveriesQuery->where('delivered_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $deliveries = $deliveriesQuery->get();

        // 2. Fetch POSTED Settlements (Credit: Driver settles into company treasury)
        $settlementsQuery = DriverCodSettlement::query()
            ->with(['currency', 'treasuryAccount'])
            ->where('driver_id', $driverId)
            ->where('status', DriverCodSettlementStatus::Posted);

        if ($currencyId) {
            $settlementsQuery->where('currency_id', $currencyId);
        }

        if ($startDate) {
            $settlementsQuery->where('posted_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $settlementsQuery->where('posted_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $settlements = $settlementsQuery->get();

        // 3. Merge and Sort chronologically
        $events = collect();

        foreach ($deliveries as $delivery) {
            $delDate = $delivery->delivered_at ? Carbon::parse($delivery->delivered_at) : null;
            $orderNum = $delivery->order ? (string) ($delivery->order->getAttribute('order_number') ?? '-') : '-';
            $events->push([
                'timestamp' => $delDate->timestamp ?? 0,
                'date' => $delDate ? $delDate->format('d-m-Y H:i') : '',
                'type' => 'delivery',
                'type_label' => 'تحصيل شحنة COD',
                'reference' => sprintf('شحنة: %s (طلب: %s)', $delivery->delivery_number, $orderNum),
                'notes' => (string) ($delivery->notes ?? ''),
                'debit' => (float) $delivery->cod_collected_amount,
                'credit' => 0.0,
                'status' => 'تم التسليم',
                'status_badge' => 'badge bg-info-subtle text-info',
            ]);
        }

        foreach ($settlements as $settlement) {
            $postDate = $settlement->posted_at ? Carbon::parse($settlement->posted_at) : null;
            $tAcc = $settlement->treasuryAccount;
            $treasuryName = $tAcc ? (string) ($tAcc->getAttribute('name_ar') ?? $tAcc->getAttribute('name') ?? $tAcc->getAttribute('bank_name') ?? 'الخزينة') : 'الخزينة';
            $events->push([
                'timestamp' => $postDate->timestamp ?? 0,
                'date' => $postDate ? $postDate->format('d-m-Y H:i') : '',
                'type' => 'settlement',
                'type_label' => 'توريد تسوية مالية',
                'reference' => sprintf('تسوية: %s -> %s', $settlement->settlement_number, $treasuryName),
                'notes' => (string) ($settlement->notes ?? ''),
                'debit' => 0.0,
                'credit' => (float) $settlement->total_settled,
                'status' => 'مرحل',
                'status_badge' => 'badge bg-success-subtle text-success',
            ]);
        }

        $sortedEvents = $events->sortBy('timestamp')->values();

        // 4. Calculate running balance
        $runningBalance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $lines = $sortedEvents->map(function (array $event) use (&$runningBalance, &$totalDebit, &$totalCredit): array {
            $totalDebit += $event['debit'];
            $totalCredit += $event['credit'];
            $runningBalance = round($runningBalance + $event['debit'] - $event['credit'], 4);

            return $this->withBalance($event, $runningBalance);
        });

        return [
            'driver' => $driver,
            'currency_id' => $currencyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_debit' => round($totalDebit, 4),
            'total_credit' => round($totalCredit, 4),
            'ending_balance' => round($runningBalance, 4),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{date: string, type: string, type_label: string, reference: string, notes: string, debit: float, credit: float, status: string, status_badge: string}  $event
     * @return array{date: string, type: string, type_label: string, reference: string, notes: string, debit: float, credit: float, balance: float, status: string, status_badge: string}
     */
    private function withBalance(array $event, float $balance): array
    {
        return [
            'date' => $event['date'],
            'type' => $event['type'],
            'type_label' => $event['type_label'],
            'reference' => $event['reference'],
            'notes' => $event['notes'],
            'debit' => $event['debit'],
            'credit' => $event['credit'],
            'balance' => $balance,
            'status' => $event['status'],
            'status_badge' => $event['status_badge'],
        ];
    }
}
