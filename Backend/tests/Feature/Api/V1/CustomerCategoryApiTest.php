<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_returns_active_categories_in_hierarchical_tree(): void
    {
        $perfumes = Category::create([
            'name_ar' => 'العطور',
            'name_en' => 'Perfumes',
            'slug' => 'perfumes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $oriental = Category::create([
            'parent_id' => $perfumes->id,
            'name_ar' => 'عطور شرقية',
            'name_en' => 'Oriental Perfumes',
            'slug' => 'oriental-perfumes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $inactiveSub = Category::create([
            'parent_id' => $perfumes->id,
            'name_ar' => 'عطور قديمة',
            'name_en' => 'Old Perfumes',
            'slug' => 'old-perfumes',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $inactiveRoot = Category::create([
            'name_ar' => 'قسم معطل',
            'name_en' => 'Disabled Category',
            'slug' => 'disabled-category',
            'sort_order' => 3,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.id', $perfumes->id)
            ->assertJsonPath('data.0.slug', 'perfumes')
            ->assertJsonPath('data.0.children.0.id', $oriental->id);

        $data = $response->json('data');
        $this->assertCount(1, $data); // Only active root
        $this->assertCount(1, $data[0]['children']); // Only active child
    }

    public function test_category_show_returns_active_category_details(): void
    {
        $category = Category::create([
            'name_ar' => 'العناية بالبشرة',
            'name_en' => 'Skincare',
            'slug' => 'skincare',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $responseById = $this->getJson("/api/v1/categories/{$category->id}");
        $responseById->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.slug', 'skincare');

        $responseBySlug = $this->getJson('/api/v1/categories/skincare');
        $responseBySlug->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_category_show_returns_404_for_inactive_or_nonexistent_category(): void
    {
        $inactive = Category::create([
            'name_ar' => 'مكياج تجريبي',
            'name_en' => 'Draft Makeup',
            'slug' => 'draft-makeup',
            'is_active' => false,
        ]);

        $responseInactive = $this->getJson("/api/v1/categories/{$inactive->id}");
        $responseInactive->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::CategoryNotFound->value);

        $responseNonexistent = $this->getJson('/api/v1/categories/unknown-category');
        $responseNonexistent->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::CategoryNotFound->value);
    }
}
