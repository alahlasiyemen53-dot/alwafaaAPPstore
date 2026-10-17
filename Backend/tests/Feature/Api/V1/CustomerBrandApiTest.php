<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CustomerBrandApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_brands_index_returns_only_active_brands_ordered_by_sort_order(): void
    {
        $second = Brand::create(['name_ar' => 'علامة ثانية', 'slug' => 'second-brand', 'is_active' => true, 'sort_order' => 2]);
        $first = Brand::create(['name_ar' => 'علامة أولى', 'slug' => 'first-brand', 'is_active' => true, 'sort_order' => 1]);
        Brand::create(['name_ar' => 'علامة معطلة', 'slug' => 'inactive-brand', 'is_active' => false, 'sort_order' => 0]);

        $response = $this->getJson('/api/v1/brands');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);

        self::assertCount(2, $response->json('data'));
    }

    public function test_brand_resource_returns_null_logo_when_no_media(): void
    {
        Brand::create(['name_ar' => 'علامة بدون شعار', 'slug' => 'no-logo-brand', 'is_active' => true]);

        $response = $this->getJson('/api/v1/brands');

        $response->assertJsonPath('data.0.logo', null);
    }

    public function test_brand_resource_returns_logo_url_when_media_present(): void
    {
        Storage::fake('public');
        $brand = Brand::create(['name_ar' => 'علامة بشعار', 'slug' => 'logo-brand', 'is_active' => true]);
        $brand->addMedia(UploadedFile::fake()->image('logo.jpg', 300, 300))->toMediaCollection('brand_logo');

        $response = $this->getJson('/api/v1/brands');

        $response->assertStatus(200);
        self::assertNotNull($response->json('data.0.logo.url'));
    }

    public function test_brand_show_returns_404_for_inactive_brand(): void
    {
        $inactive = Brand::create(['name_ar' => 'علامة معطلة', 'slug' => 'disabled-brand', 'is_active' => false]);

        $this->getJson("/api/v1/brands/{$inactive->id}")->assertStatus(404);
    }

    public function test_brand_show_returns_active_brand(): void
    {
        $brand = Brand::create(['name_ar' => 'علامة نشطة', 'slug' => 'active-brand', 'is_active' => true]);

        $this->getJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $brand->id);
    }

    public function test_brands_index_does_not_cause_n_plus_one_media_queries(): void
    {
        Storage::fake('public');
        foreach (range(1, 5) as $i) {
            $brand = Brand::create(['name_ar' => "علامة $i", 'slug' => "brand-$i", 'is_active' => true, 'sort_order' => $i]);
            $brand->addMedia(UploadedFile::fake()->image("logo$i.jpg", 200, 200))->toMediaCollection('brand_logo');
        }

        DB::enableQueryLog();
        $this->getJson('/api/v1/brands')->assertStatus(200);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One query for brands + one for the eager-loaded media relation (bounded, not N+1).
        self::assertLessThanOrEqual(4, $queryCount);
    }
}
