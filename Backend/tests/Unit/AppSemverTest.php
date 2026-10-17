<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AppSemver;
use PHPUnit\Framework\TestCase;

final class AppSemverTest extends TestCase
{
    public function test_max_picks_highest_version(): void
    {
        $this->assertSame(
            '1.1.0+1',
            AppSemver::max('1.0.1+5', '1.0.2+6', '1.1.0+1', null, '')
        );
    }

    public function test_compare_patch_and_build(): void
    {
        $this->assertGreaterThan(0, AppSemver::compare('1.0.2+6', '1.0.1+5'));
        $this->assertGreaterThan(0, AppSemver::compare('1.0.1+6', '1.0.1+5'));
        $this->assertSame(0, AppSemver::compare('1.0.1+5', '1.0.1+5'));
    }
}
