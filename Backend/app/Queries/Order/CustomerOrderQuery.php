<?php

declare(strict_types=1);

namespace App\Queries\Order;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CustomerOrderQuery
{
    public function paginate(Customer $customer, int $perPage, int $page): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->with(['currency:id,code,symbol', 'items:id,order_id,product_name_snapshot,variant_name_snapshot,quantity,line_total'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(50, max(1, $perPage)), ['*'], 'page', max(1, $page));
    }

    public function find(Customer $customer, int $orderId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('customer_id', $customer->id)
            ->with([
                'currency:id,code,symbol',
                'deliveryAddress',
                'items:id,order_id,product_variant_id,product_name_snapshot,variant_name_snapshot,sku_snapshot,fulfillment_mode_snapshot,quantity,original_unit_price,unit_price,line_subtotal,discount_amount,line_total,supplier_name_snapshot,supplier_item_number_snapshot,sourcing_supplier_id_snapshot',
                'items.productVariant.media',
                'items.productVariant.product.media',
                'items.productVariant.supplier',
                'items.sourcingSupplier',
                'deliveries:id,order_id,status,assigned_at,delivered_at,failed_at,returned_at,driver_id',
            ])
            ->first();
    }
}
