<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Promotion;

final class DeepLinkService
{
    public function __construct(
        private readonly AppReleaseService $appReleaseService,
    ) {}

    public function getBaseUrl(): string
    {
        return rtrim((string) config('deep_link.base_url', 'https://wafapp.alwafaamed.com'), '/');
    }

    public function getScheme(): string
    {
        return (string) config('deep_link.scheme', 'storeapp');
    }

    public function productUrl(Product|int|string $product): string
    {
        $id = $product instanceof Product ? $product->id : $product;

        return $this->getBaseUrl().'/p/'.$id;
    }

    public function productCustomScheme(int|string $productId): string
    {
        return $this->getScheme().'://product/'.$productId;
    }

    public function offerUrl(Promotion|int|string $offer): string
    {
        $id = $offer instanceof Promotion ? $offer->id : $offer;

        return $this->getBaseUrl().'/offers/'.$id;
    }

    public function productIntentUrl(int|string $productId): string
    {
        $package = (string) config('deep_link.android.package_name', 'com.alwafaamed.storeapp');
        $scheme = $this->getScheme();

        return "intent://product/{$productId}#Intent;scheme={$scheme};package={$package};end";
    }

    public function offerCustomScheme(int|string $offerId): string
    {
        return $this->getScheme().'://offer/'.$offerId;
    }

    public function offerIntentUrl(int|string $offerId): string
    {
        $package = (string) config('deep_link.android.package_name', 'com.alwafaamed.storeapp');
        $scheme = $this->getScheme();

        return "intent://offer/{$offerId}#Intent;scheme={$scheme};package={$package};end";
    }

    public function collectionUrl(ProductCollection|int|string $collection): string
    {
        $id = $collection instanceof ProductCollection ? $collection->id : $collection;

        return $this->getBaseUrl().'/c/'.$id;
    }

    public function collectionCustomScheme(int|string $collectionId): string
    {
        return $this->getScheme().'://collection/'.$collectionId;
    }

    public function collectionIntentUrl(int|string $collectionId): string
    {
        $package = (string) config('deep_link.android.package_name', 'com.alwafaamed.storeapp');
        $scheme = $this->getScheme();

        return "intent://collection/{$collectionId}#Intent;scheme={$scheme};package={$package};end";
    }

    /**
     * @return array{android: string, ios: string}
     */
    public function resolveStoreUrls(): array
    {
        $release = $this->appReleaseService?->getReleaseInfo() ?? [
            'android_store_url' => null,
            'ios_store_url' => null,
        ];

        $android = $release['android_store_url']
            ?: (string) config('deep_link.android.store_url', 'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp');

        $ios = $release['ios_store_url']
            ?: (string) config('deep_link.ios.store_url', '');

        // Fallback placeholder if iOS store URL is not yet configured on server
        if (empty($ios)) {
            $bundleId = (string) config('deep_link.ios.bundle_id', 'com.alwafaamed.storeapp');
            $ios = 'https://apps.apple.com/app/id-pending?bundle='.$bundleId;
        }

        return [
            'android' => $android,
            'ios' => $ios,
        ];
    }

    /**
     * Build Android Digital Asset Links payload.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssetLinksPayload(): array
    {
        $package = (string) config('deep_link.android.package_name', 'com.alwafaamed.storeapp');
        /** @var array<int, string> $fingerprints */
        $fingerprints = (array) config('deep_link.android.sha256_cert_fingerprints', []);

        return [
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ];
    }

    /**
     * Build Apple App Site Association payload.
     *
     * @return array<string, mixed>
     */
    public function getAppleAppSiteAssociationPayload(): array
    {
        $teamId = (string) config('deep_link.ios.team_id', '');
        $bundleId = (string) config('deep_link.ios.bundle_id', 'com.alwafaamed.storeapp');
        $appId = ! empty($teamId) ? "{$teamId}.{$bundleId}" : $bundleId;

        return [
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => $appId,
                        'paths' => [
                            '/p/*',
                            '/offers/*',
                            '/c/*',
                        ],
                    ],
                ],
            ],
        ];
    }
}
