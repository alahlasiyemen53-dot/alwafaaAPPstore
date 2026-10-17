<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ConfirmCustomerDeliveryAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

final class AutoConfirmExpiredCustomerDeliveriesCommand extends Command
{
    protected $signature = 'orders:auto-confirm-deliveries';

    protected $description = 'Auto-complete orders awaiting customer delivery confirmation past their deadline';

    public function handle(ConfirmCustomerDeliveryAction $confirmAction): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::AwaitingCustomerConfirmation)
            ->whereNotNull('customer_confirm_deadline_at')
            ->where('customer_confirm_deadline_at', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        $closed = 0;
        foreach ($orders as $order) {
            try {
                $confirmAction->execute($order, null, true, 0);
                $closed++;
            } catch (\Throwable $e) {
                $this->error("Order {$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-confirmed {$closed} order(s).");

        return self::SUCCESS;
    }
}
