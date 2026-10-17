<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ArabicTextNormalizer;
use PHPUnit\Framework\TestCase;

final class ArabicTextNormalizerTest extends TestCase
{
    public function test_normalizes_alef_and_teh_marbuta_variants(): void
    {
        $this->assertSame('اساس', ArabicTextNormalizer::normalize('أساس'));
        $this->assertSame('اساس', ArabicTextNormalizer::normalize('اساس'));
        $this->assertSame('فاكهه', ArabicTextNormalizer::normalize('فاكهة'));
        $this->assertSame('علي', ArabicTextNormalizer::normalize('على'));
    }
}
