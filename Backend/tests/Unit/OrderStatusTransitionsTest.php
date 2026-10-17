<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTransitionsTest extends TestCase
{
    public function test_draft_status_capabilities(): void
    {
        $status = OrderStatus::Draft;

        $this->assertTrue($status->canBeEdited());
        $this->assertTrue($status->canBeConfirmed());
        $this->assertTrue($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertSame('قيد المراجعة', $status->label());
        $this->assertStringContainsString('text-warning', $status->badgeClass());
    }

    public function test_confirmed_status_capabilities(): void
    {
        $status = OrderStatus::Confirmed;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertTrue($status->canBeCancelled());
        $this->assertTrue($status->canBeAssignedToDriver());
        $this->assertSame('تم تأكيد الطلب', $status->label());
        $this->assertStringContainsString('text-success', $status->badgeClass());
    }

    public function test_assigned_to_driver_status_capabilities(): void
    {
        $status = OrderStatus::AssignedToDriver;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertFalse($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertTrue($status->canAwaitCustomerConfirmation());
        $this->assertSame('جاهز للتوصيل', $status->label());
        $this->assertStringContainsString('text-primary', $status->badgeClass());
    }

    public function test_awaiting_customer_confirmation_capabilities(): void
    {
        $status = OrderStatus::AwaitingCustomerConfirmation;

        $this->assertTrue($status->canBeCustomerConfirmed());
        $this->assertFalse($status->canAwaitCustomerConfirmation());
        $this->assertSame('تم تسليم الطلب من قبل المندوب', $status->label());
    }

    public function test_delivered_status_capabilities(): void
    {
        $status = OrderStatus::Delivered;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertFalse($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertSame('مكتمل', $status->label());
        $this->assertStringContainsString('text-success', $status->badgeClass());
    }

    public function test_delivery_failed_status_capabilities(): void
    {
        $status = OrderStatus::DeliveryFailed;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertFalse($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertSame('تعذر التوصيل (مع المندوب)', $status->label());
        $this->assertStringContainsString('text-danger', $status->badgeClass());
    }

    public function test_returned_to_warehouse_status_capabilities(): void
    {
        $status = OrderStatus::ReturnedToWarehouse;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertFalse($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertSame('مرتجع للمستودع', $status->label());
        $this->assertStringContainsString('text-secondary', $status->badgeClass());
    }

    public function test_cancelled_status_capabilities(): void
    {
        $status = OrderStatus::Cancelled;

        $this->assertFalse($status->canBeEdited());
        $this->assertFalse($status->canBeConfirmed());
        $this->assertFalse($status->canBeCancelled());
        $this->assertFalse($status->canBeAssignedToDriver());
        $this->assertSame('ملغي', $status->label());
        $this->assertStringContainsString('text-danger', $status->badgeClass());
    }
}
