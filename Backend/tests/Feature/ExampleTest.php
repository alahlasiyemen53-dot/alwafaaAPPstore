<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_serves_marketing_home(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('الأناقة والجمال', false);
    }
}
