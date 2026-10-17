<?php

namespace Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

final class FoundationProbeJob implements ShouldQueue
{
    public function handle(): void
    {
        // Test-only job used to verify queue dispatch wiring.
    }
}
