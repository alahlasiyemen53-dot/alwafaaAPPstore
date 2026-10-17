<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_yemen_mobile_numbers_correctly(): void
    {
        // 9 digits starting with 7
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('771234567'));
        $this->assertSame('+967731234567', PhoneNormalizer::normalize('731234567'));
        $this->assertSame('+967711234567', PhoneNormalizer::normalize('711234567'));
        $this->assertSame('+967701234567', PhoneNormalizer::normalize('701234567'));

        // Leading zero local format (0771234567)
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('0771234567'));

        // Formatted with spaces and hyphens
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('77 123-4567'));
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('(771) 234-567'));

        // International format with 00 or +
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('+967 771 234 567'));
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('00967771234567'));
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('967771234567'));
    }

    public function test_converts_arabic_eastern_numerals(): void
    {
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('٧٧١٢٣٤٥٦٧'));
        $this->assertSame('+967771234567', PhoneNormalizer::normalize('٠٧٧١٢٣٤٥٦٧'));
    }

    public function test_validates_phone_numbers(): void
    {
        $this->assertTrue(PhoneNormalizer::isValid('771234567'));
        $this->assertTrue(PhoneNormalizer::isValid('+967771234567'));
        $this->assertTrue(PhoneNormalizer::isValid('00967771234567'));
        $this->assertTrue(PhoneNormalizer::isValid('+14155552671'));

        $this->assertFalse(PhoneNormalizer::isValid(''));
        $this->assertFalse(PhoneNormalizer::isValid(null));
        $this->assertFalse(PhoneNormalizer::isValid('abc'));
        $this->assertFalse(PhoneNormalizer::isValid('123'));
    }

    public function test_distinguishes_between_canonical_change_and_format_change(): void
    {
        // Formatting only change - canonical phones are equal
        $this->assertTrue(PhoneNormalizer::areCanonicalEqual('771234567', '+967 77 123 4567'));
        $this->assertTrue(PhoneNormalizer::areCanonicalEqual('0771234567', '771234567'));
        $this->assertTrue(PhoneNormalizer::areCanonicalEqual('771-234-567', '00967 771234567'));

        // Real canonical phone change
        $this->assertFalse(PhoneNormalizer::areCanonicalEqual('771234567', '779999999'));
        $this->assertFalse(PhoneNormalizer::areCanonicalEqual('771234567', '731234567'));
    }
}
