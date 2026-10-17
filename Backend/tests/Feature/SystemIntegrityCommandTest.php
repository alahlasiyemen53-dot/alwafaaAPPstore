<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SystemIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_integrity_command_executes_successfully_on_clean_state(): void
    {
        $this->artisan('app:verify-integrity', ['--days' => 30])
            ->expectsOutputToContain('بدء فحص تكامل النظام عبر السجلات المحاسبية والمخزنية...')
            ->expectsOutputToContain('1. ميزان المراجعة والأستاذ العام (Trial Balance & GL):')
            ->expectsOutputToContain('اكتمل فحص التكامل بنجاح')
            ->assertExitCode(0);
    }
}
