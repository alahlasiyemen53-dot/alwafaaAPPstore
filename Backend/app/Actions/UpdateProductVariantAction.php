<?php

namespace App\Actions;

use App\Data\ProductVariantData;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ReservationStatus;
use App\Enums\SourcingStatus;
use App\Models\InventoryReservation;
use App\Models\OrderSourcingRequirement;
use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Rules\VariantPriceRule;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Services\VariantInvariantValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepository $variants,
        private readonly VariantInvariantValidator $validator,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant, ProductVariantData $data): ProductVariant
    {
        $comparePrice = $data->compareAtPrice !== null ? (float) $data->compareAtPrice : null;
        if (! VariantPriceRule::passes((float) $data->price, $comparePrice)) {
            throw ValidationException::withMessages([
                'price' => ['سعر المنتج يجب أن يكون أكبر من أو يساوي الصفر، وسعر المقارنة يجب أن يكون أعلى من السعر.'],
            ]);
        }

        // Safe Mode Change Validation (Section 50)
        $currentMode = $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked;
        $newMode = $data->fulfillmentMode;

        if ($currentMode !== $newMode) {
            if ($currentMode === ProductVariantFulfillmentMode::Stocked && $newMode === ProductVariantFulfillmentMode::OnDemand) {
                $hasActiveReservations = InventoryReservation::query()
                    ->where('product_variant_id', $variant->id)
                    ->where('status', ReservationStatus::Active)
                    ->exists();

                if ($hasActiveReservations) {
                    throw ValidationException::withMessages([
                        'fulfillment_mode' => ['لا يمكن تحويل طريقة التوفير إلى "توفير عند الطلب" لوجود حجوزات مخزنية نشطة لطلبات قيد التنفيذ.'],
                    ]);
                }
            }

            if ($currentMode === ProductVariantFulfillmentMode::OnDemand && $newMode === ProductVariantFulfillmentMode::Stocked) {
                $hasActiveSourcing = OrderSourcingRequirement::query()
                    ->where('product_variant_id', $variant->id)
                    ->whereIn('status', [SourcingStatus::Pending, SourcingStatus::Assigned])
                    ->exists();

                if ($hasActiveSourcing) {
                    throw ValidationException::withMessages([
                        'fulfillment_mode' => ['لا يمكن تحويل طريقة التوفير إلى "مخزني" لوجود مهام توفير جارية (بانتظار الإسناد أو مسندة لمندوب).'],
                    ]);
                }
            }
        }

        $attributeMap = $this->validator->validateAttributeValues($data->attributeValueIds);
        $this->validator->validateUniqueCombination($variant->product_id, $data->attributeValueIds, $variant->id);

        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        // If this variant is already default and the request tries to un-default it without another default, preserve default
        $isDefault = $data->isDefault || $variant->is_default;

        $adjustedData = new ProductVariantData(
            productId: $variant->product_id,
            sku: $variant->sku,
            barcode: $data->barcode,
            status: $data->status,
            isDefault: $isDefault,
            attributeValueIds: $data->attributeValueIds,
            price: $data->price,
            compareAtPrice: $data->compareAtPrice,
            fulfillmentMode: $data->fulfillmentMode,
            supplierId: $data->supplierId,
            supplierItemNumber: $data->supplierItemNumber,
        );

        return DB::transaction(function () use ($variant, $adjustedData, $attributeMap, $baseCurrency): ProductVariant {
            $before = [
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'status' => $variant->status->value,
                'is_default' => $variant->is_default,
                'fulfillment_mode' => ($variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked)->value,
                'supplier_id' => $variant->supplier_id,
            ];

            $updated = $this->variants->update($variant, $adjustedData, $attributeMap, $baseCurrency->id);

            $this->auditLogger->record('admin.product_variant.updated', $updated, metadata: [
                'before' => $before,
                'after' => [
                    'sku' => $updated->sku,
                    'barcode' => $updated->barcode,
                    'status' => $updated->status->value,
                    'is_default' => $updated->is_default,
                    'fulfillment_mode' => ($updated->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked)->value,
                    'supplier_id' => $updated->supplier_id,
                ],
                'base_currency' => $baseCurrency->code,
                'price' => $adjustedData->price,
            ]);

            return $updated;
        });
    }
}
