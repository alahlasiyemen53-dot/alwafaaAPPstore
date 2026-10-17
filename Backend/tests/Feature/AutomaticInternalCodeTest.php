<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutomaticInternalCodeTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);

        $admin = User::factory()->create(['email' => 'auto-code@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->token = $admin->createToken('test')->plainTextToken;
    }

    public function test_warehouse_code_is_auto_generated_when_omitted_on_create(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/admin/warehouses', [
            'name_ar' => 'مستودع تلقائي',
            'name_en' => 'Auto Warehouse',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $warehouseId = $response->json('data.id');
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        $this->assertNotEmpty($warehouse->code);
    }

    public function test_unit_code_cannot_be_changed_on_update(): void
    {
        $unit = Unit::query()->firstOrFail();
        $originalCode = $unit->code;

        $this->withToken($this->token)->patchJson("/api/v1/admin/units/{$unit->id}", [
            'name_ar' => $unit->name_ar,
            'code' => 'CHANGED',
        ])->assertOk();

        $this->assertSame($originalCode, $unit->fresh()->code);
    }
}
