<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function test_normalize_formats_to_four_decimals(): void
    {
        $this->assertSame('10.0000', Decimal::normalize('10'));
        $this->assertSame('10.5000', Decimal::normalize(10.5));
        $this->assertSame('0.1234', Decimal::normalize('0.12345')); // Truncates or parses to 4
        $this->assertSame('0.0000', Decimal::normalize(''));
    }

    public function test_add_sub_mul_div(): void
    {
        $this->assertSame('15.0000', Decimal::add('10.5000', '4.5000'));
        $this->assertSame('6.0000', Decimal::sub('10.5000', '4.5000'));
        $this->assertSame('25.0000', Decimal::mul('5.0000', '5.0000'));
        $this->assertSame('2.5000', Decimal::div('5.0000', '2.0000'));
    }

    public function test_div_throws_on_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::div('10.0000', '0');
    }

    public function test_min_max_compare(): void
    {
        $this->assertSame(-1, Decimal::compare('5.0000', '10.0000'));
        $this->assertSame(1, Decimal::compare('10.0000', '5.0000'));
        $this->assertSame(0, Decimal::compare('5.0000', '5.0000'));

        $this->assertSame('5.0000', Decimal::min('5.0000', '10.0000'));
        $this->assertSame('10.0000', Decimal::max('5.0000', '10.0000'));
    }
}
