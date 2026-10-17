<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\AppReleaseService;
use App\Services\DeepLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeepLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['deep_link.base_url' => 'https://lamma.alwafaamed.com']);
        config(['deep_link.scheme' => 'lamma']);
        app(AppReleaseService::class)->clearCache();
    }

    public function test_assetlinks_returns_valid_json_contract(): void
    {
        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.alwafaa.lamma_app',
                ],
            ],
        ]);
    }

    public function test_apple_app_site_association_returns_valid_json_contract(): void
    {
        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'applinks' => [
                'apps',
                'details' => [
                    '*' => [
                        'appID',
                        'paths',
                    ],
                ],
            ],
        ]);
    }

    public function test_product_deep_link_returns_landing_view_with_og_tags(): void
    {
        $brand = Brand::create([
            'name_ar' => 'ديور',
            'name_en' => 'Dior',
            'slug' => 'dior',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'عطور',
            'name_en' => 'Perfumes',
            'slug' => 'perfumes',
            'is_active' => true,
        ]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'عطر سوفاج لوكس',
            'name_en' => 'Sauvage Luxe',
            'slug' => 'sauvage-luxe',
            'description_ar' => 'عطر رجالي فاخر وجذاب',
            'description_en' => 'Luxury men fragrance',
            'status' => ProductStatus::Active,
        ]);

        $response = $this->get('/p/'.$product->id);

        $response->assertOk();
        $response->assertSee('عطر سوفاج لوكس');
        $response->assertSee('lamma://product/'.$product->id);
        $response->assertSee('https://lamma.alwafaamed.com/p/'.$product->id);
        $response->assertSee('og:title', false);
        $response->assertSee('twitter:card', false);
    }

    public function test_product_deep_link_returns_404_when_not_found(): void
    {
        $response = $this->get('/p/999999');

        $response->assertNotFound();
    }

    public function test_offer_deep_link_returns_landing_view(): void
    {
        $promotion = Promotion::create([
            'code' => 'SUMMER2026',
            'name' => 'عرض الصيف الفاخر',
            'description' => 'خصم 20% على كل العطور',
            'type' => \App\Enums\PromotionType::Percentage,
            'scope' => \App\Enums\PromotionScope::All,
            'value' => '20.00',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);

        $response = $this->get('/offers/'.$promotion->id);

        $response->assertOk();
        $response->assertSee('عرض الصيف الفاخر');
        $response->assertSee('lamma://offer/'.$promotion->id);
    }

    public function test_store_urls_reflect_admin_settings_override(): void
    {
        Setting::updateOrCreate(
            ['key' => 'app_android_store_url'],
            ['value' => 'https://play.google.com/store/apps/details?id=custom.override', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'app_ios_store_url'],
            ['value' => 'https://testflight.apple.com/join/custom123', 'type' => 'string']
        );

        app(AppReleaseService::class)->clearCache();
        $service = app(DeepLinkService::class);
        $stores = $service->resolveStoreUrls();

        $this->assertSame('https://play.google.com/store/apps/details?id=custom.override', $stores['android']);
        $this->assertSame('https://testflight.apple.com/join/custom123', $stores['ios']);
    }
}
