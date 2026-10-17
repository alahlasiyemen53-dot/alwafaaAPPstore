<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class MediaUrlVersioningTest extends TestCase
{
    public function test_public_media_urls_are_versioned_for_cache_invalidation(): void
    {
        self::assertTrue((bool) config('media-library.version_urls'));
    }
}
