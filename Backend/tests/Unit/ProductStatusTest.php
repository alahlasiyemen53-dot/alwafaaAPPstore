<?php

namespace Tests\Unit;

use App\Enums\ProductStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductStatusTest extends TestCase
{
    #[DataProvider('transitionCases')]
    public function test_product_lifecycle_allows_only_explicit_active_inactive_transitions(
        ProductStatus $current,
        ProductStatus $target,
        bool $allowed,
    ): void {
        self::assertSame($allowed, $current->canTransitionTo($target));
    }

    public static function transitionCases(): array
    {
        return [
            'draft to active' => [ProductStatus::Draft, ProductStatus::Active, true],
            'draft to inactive' => [ProductStatus::Draft, ProductStatus::Inactive, true],
            'inactive to active' => [ProductStatus::Inactive, ProductStatus::Active, true],
            'active to inactive' => [ProductStatus::Active, ProductStatus::Inactive, true],
            'active to draft' => [ProductStatus::Active, ProductStatus::Draft, false],
            'same status' => [ProductStatus::Active, ProductStatus::Active, false],
        ];
    }
}
