<?php

namespace App\DTOs;

final readonly class UpdateOrderData
{
    /**
     * @param  array<int, OrderItemData>  $items
     */
    public function __construct(
        public int $customerId,
        public int $customerAddressId,
        public int $warehouseId,
        public int $currencyId,
        public array $items,
        public ?string $notes = null,
        public ?string $couponCode = null,
        public bool $giftWrappingRequested = false,
        public ?string $giftMessage = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $item) => OrderItemData::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            customerId: (int) $data['customer_id'],
            customerAddressId: (int) $data['customer_address_id'],
            warehouseId: (int) $data['warehouse_id'],
            currencyId: (int) $data['currency_id'],
            items: $items,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            couponCode: isset($data['coupon_code']) ? (string) $data['coupon_code'] : null,
            giftWrappingRequested: filter_var($data['gift_wrapping_requested'] ?? false, FILTER_VALIDATE_BOOLEAN),
            giftMessage: isset($data['gift_message']) && trim((string) $data['gift_message']) !== '' ? (string) $data['gift_message'] : null,
        );
    }
}
