<?php

namespace Tests\Unit;

use App\Services\Delivery\SanaaServiceAreaValidator;
use LogicException;
use Tests\TestCase;

final class SanaaServiceAreaValidatorTest extends TestCase
{
    public function test_it_remains_unconfigured_without_an_owner_approved_polygon(): void
    {
        config()->set('delivery.sanaa_polygon', null);
        $validator = new SanaaServiceAreaValidator;

        self::assertFalse($validator->isConfigured());
        $this->expectException(LogicException::class);
        $validator->contains(15.0, 44.0);
    }

    public function test_it_evaluates_an_approved_polygon_when_one_is_configured(): void
    {
        config()->set('delivery.sanaa_polygon', [[44.0, 15.0], [45.0, 15.0], [45.0, 16.0], [44.0, 16.0]]);
        $validator = new SanaaServiceAreaValidator;

        self::assertTrue($validator->isConfigured());
        self::assertTrue($validator->contains(15.5, 44.5));
        self::assertFalse($validator->contains(16.5, 44.5));
    }
}
