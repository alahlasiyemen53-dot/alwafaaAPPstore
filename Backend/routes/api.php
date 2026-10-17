<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\MasterDataController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Cart\CustomerCartController;
use App\Http\Controllers\Api\V1\Catalog\CustomerBrandController;
use App\Http\Controllers\Api\V1\Catalog\CustomerCategoryController;
use App\Http\Controllers\Api\V1\Catalog\CustomerCollectionController;
use App\Http\Controllers\Api\V1\Catalog\CustomerHomeController;
use App\Http\Controllers\Api\V1\Catalog\CustomerProductController;
use App\Http\Controllers\Api\V1\Catalog\CustomerRecentlyViewedController;
use App\Http\Controllers\Api\V1\Catalog\CustomerSearchController;
use App\Http\Controllers\Api\V1\Catalog\CustomerVisualSearchController;
use App\Http\Controllers\Api\V1\Catalog\CustomerWishlistController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\CustomerDeliveryZoneController;
use App\Http\Controllers\Api\V1\Customer\CustomerDeviceController;
use App\Http\Controllers\Api\V1\Customer\CustomerLoyaltyController;
use App\Http\Controllers\Api\V1\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\V1\Customer\CustomerProofController;
use App\Http\Controllers\Api\V1\Customer\CustomerReferenceDataController;
use App\Http\Controllers\Api\V1\Customer\CustomerSearchHistoryController;
use App\Http\Controllers\Api\V1\Customer\CustomerSupportChatController;
use App\Http\Controllers\Api\V1\Customer\CustomerSupportController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Order\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\Order\CustomerOrderController;
use App\Http\Controllers\Api\V1\Order\CustomerPaymentController;
use App\Http\Controllers\Api\V1\Reviews\CustomerProductReviewController;
use App\Http\Controllers\Api\V1\Reviews\CustomerReviewCommentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');
    Route::get('/app/version', [AppVersionController::class, 'show'])->name('api.v1.app.version');
    Route::post('/app/version/check', [AppVersionController::class, 'check'])->name('api.v1.app.version.check');

    /*
    |--------------------------------------------------------------------------
    | Customer API v1 Routes (REST Baseline & Proof Endpoints)
    |--------------------------------------------------------------------------
    */
    Route::middleware([
        'request.id',
        'content.negotiation',
        'api.logging',
    ])->group(function (): void {
        Route::get('/ping', [CustomerProofController::class, 'ping'])
            ->middleware('throttle:api.customer.public')
            ->name('api.v1.customer.ping');

        Route::get('/proof/empty', [CustomerProofController::class, 'empty'])
            ->middleware('throttle:api.customer.public')
            ->name('api.v1.customer.proof.empty');

        Route::post('/proof/echo', [CustomerProofController::class, 'echo'])
            ->middleware(['throttle:api.customer.public', 'idempotent'])
            ->name('api.v1.customer.proof.echo');

        // Customer Catalog Endpoints (Sprint 24)
        Route::middleware('throttle:api.customer.public')->group(function (): void {
            Route::get('/categories', [CustomerCategoryController::class, 'index'])
                ->name('api.v1.customer.categories.index');

            Route::get('/brands', [CustomerBrandController::class, 'index'])
                ->name('api.v1.customer.brands.index');

            Route::get('/brands/{brand}', [CustomerBrandController::class, 'show'])
                ->name('api.v1.customer.brands.show');

            Route::get('/categories/{category}', [CustomerCategoryController::class, 'show'])
                ->name('api.v1.customer.categories.show');

            Route::get('/products', [CustomerProductController::class, 'index'])
                ->name('api.v1.customer.products.index');

            // Customer Search Endpoint (Sprint 25)
            Route::get('/products/search', [CustomerSearchController::class, 'index'])
                ->name('api.v1.customer.products.search');

            // Customer Visual Search Endpoints (Sprint 26)
            Route::post('/search/visual', [CustomerVisualSearchController::class, 'search'])
                ->name('api.v1.customer.search.visual');
            Route::post('/products/search/visual', [CustomerVisualSearchController::class, 'search'])
                ->name('api.v1.customer.products.search.visual');

            Route::get('/products/{product}', [CustomerProductController::class, 'show'])
                ->name('api.v1.customer.products.show');

            Route::get('/products/{product}/related', [CustomerProductController::class, 'related'])
                ->name('api.v1.customer.products.related');

            Route::get('/products/{product}/reviews', [CustomerProductReviewController::class, 'index'])
                ->name('api.v1.customer.products.reviews.index');

            Route::get('/reviews/{review}/comments', [CustomerReviewCommentController::class, 'index'])
                ->name('api.v1.customer.reviews.comments.index');

            // Geography & Reference Endpoints (Country → Governorate)
            Route::get('/countries', [CustomerReferenceDataController::class, 'countries'])
                ->name('api.v1.customer.reference.countries');
            Route::get('/countries/{id}/governorates', [CustomerReferenceDataController::class, 'governoratesByCountry'])
                ->name('api.v1.customer.reference.countries.governorates');
            Route::get('/governorates', [CustomerReferenceDataController::class, 'governorates'])
                ->name('api.v1.customer.reference.governorates');
            Route::get('/governorates/{id}/cities', [CustomerReferenceDataController::class, 'cities'])
                ->name('api.v1.customer.reference.cities');
            Route::get('/cities/{id}/districts', [CustomerReferenceDataController::class, 'districts'])
                ->name('api.v1.customer.reference.districts');
            Route::get('/currencies', [CustomerReferenceDataController::class, 'currencies'])
                ->name('api.v1.customer.reference.currencies');
            Route::get('/delivery/sanaa-zone', [CustomerDeliveryZoneController::class, 'sanaaZone'])
                ->name('api.v1.customer.delivery.sanaa_zone');

            // Customer Home & Support Endpoints (Sprint 30)
            Route::get('/home', [CustomerHomeController::class, 'index'])
                ->name('api.v1.customer.home');
            Route::get('/support', [CustomerSupportController::class, 'index'])
                ->name('api.v1.customer.support');

            Route::get('/collections', [CustomerCollectionController::class, 'index'])
                ->name('api.v1.customer.collections.index');
            Route::get('/collections/{collection}', [CustomerCollectionController::class, 'show'])
                ->name('api.v1.customer.collections.show');
        });

        // Customer Authentication Endpoints (Sprint 23)
        Route::prefix('auth')->group(function (): void {
            Route::post('/otp/request', [CustomerAuthController::class, 'requestOtp'])
                ->middleware('throttle:api.customer.otp-request')
                ->name('api.v1.customer.auth.otp.request');

            Route::post('/otp/verify', [CustomerAuthController::class, 'verifyOtp'])
                ->middleware(['throttle:api.customer.otp-verify', 'idempotent'])
                ->name('api.v1.customer.auth.otp.verify');

            Route::post('/refresh', [CustomerAuthController::class, 'refreshToken'])
                ->middleware('throttle:api.customer.public')
                ->name('api.v1.customer.auth.refresh');

            Route::post('/logout', [CustomerAuthController::class, 'logout'])
                ->middleware('auth:customer_jwt')
                ->name('api.v1.customer.auth.logout');
        });

        // Customer Authenticated Routes (Customer JWT Guard)
        Route::middleware([
            'auth:customer_jwt',
            'throttle:api.customer.auth',
        ])->group(function (): void {
            Route::get('/me', [CustomerAuthController::class, 'me'])
                ->name('api.v1.customer.me.root');

            Route::patch('/me', [CustomerAuthController::class, 'updateProfile'])
                ->name('api.v1.customer.me.update');

            Route::get('/customer/me', [CustomerAuthController::class, 'me'])
                ->name('api.v1.customer.me');

            // Customer Addresses Endpoints (Sprint 29)
            Route::prefix('addresses')->group(function (): void {
                Route::get('/', [CustomerAddressController::class, 'index'])
                    ->name('api.v1.customer.addresses.index');
                Route::post('/', [CustomerAddressController::class, 'store'])
                    ->name('api.v1.customer.addresses.store');
                Route::get('/{address}', [CustomerAddressController::class, 'show'])
                    ->name('api.v1.customer.addresses.show');
                Route::patch('/{address}', [CustomerAddressController::class, 'update'])
                    ->name('api.v1.customer.addresses.update');
                Route::delete('/{address}', [CustomerAddressController::class, 'destroy'])
                    ->name('api.v1.customer.addresses.destroy');
            });

            // Customer Wishlist Endpoints (Sprint 27)
            Route::get('/wishlist', [CustomerWishlistController::class, 'index'])
                ->name('api.v1.customer.wishlist.index');
            Route::post('/wishlist', [CustomerWishlistController::class, 'store'])
                ->name('api.v1.customer.wishlist.store');
            Route::delete('/wishlist/{product}', [CustomerWishlistController::class, 'destroy'])
                ->name('api.v1.customer.wishlist.destroy');

            Route::get('/me/recently-viewed', [CustomerRecentlyViewedController::class, 'index'])
                ->name('api.v1.customer.recently-viewed.index');
            Route::post('/me/recently-viewed/merge', [CustomerRecentlyViewedController::class, 'merge'])
                ->name('api.v1.customer.recently-viewed.merge');
            Route::post('/products/{product}/view', [CustomerRecentlyViewedController::class, 'storeView'])
                ->name('api.v1.customer.products.view');

            Route::get('/me/search-history', [CustomerSearchHistoryController::class, 'index'])
                ->name('api.v1.customer.search-history.index');
            Route::post('/me/search-history', [CustomerSearchHistoryController::class, 'store'])
                ->name('api.v1.customer.search-history.store');
            Route::post('/me/search-history/merge', [CustomerSearchHistoryController::class, 'merge'])
                ->name('api.v1.customer.search-history.merge');
            Route::delete('/me/search-history/{item}', [CustomerSearchHistoryController::class, 'destroy'])
                ->name('api.v1.customer.search-history.destroy');
            Route::delete('/me/search-history', [CustomerSearchHistoryController::class, 'clear'])
                ->name('api.v1.customer.search-history.clear');

            Route::get('/me/notifications', [CustomerNotificationController::class, 'index'])
                ->name('api.v1.customer.notifications.index');
            Route::patch('/me/notifications/{notification}/read', [CustomerNotificationController::class, 'markRead'])
                ->name('api.v1.customer.notifications.read');
            Route::patch('/me/notifications/read-all', [CustomerNotificationController::class, 'markAllRead'])
                ->name('api.v1.customer.notifications.read-all');

            // Customer Cart & Coupon Endpoints (Sprint 28)
            Route::prefix('cart')->group(function (): void {
                Route::get('/', [CustomerCartController::class, 'index'])
                    ->name('api.v1.customer.cart.index');
                Route::post('/items', [CustomerCartController::class, 'addItem'])
                    ->middleware('idempotent')
                    ->name('api.v1.customer.cart.items.add');
                Route::patch('/items/{item}', [CustomerCartController::class, 'updateItem'])
                    ->name('api.v1.customer.cart.items.update');
                Route::delete('/items/{item}', [CustomerCartController::class, 'removeItem'])
                    ->name('api.v1.customer.cart.items.remove');
                Route::delete('/', [CustomerCartController::class, 'clear'])
                    ->name('api.v1.customer.cart.clear');
                Route::post('/coupon', [CustomerCartController::class, 'applyCoupon'])
                    ->name('api.v1.customer.cart.coupon.apply');
                Route::delete('/coupon', [CustomerCartController::class, 'removeCoupon'])
                    ->name('api.v1.customer.cart.coupon.remove');
            });

            // Customer Loyalty & Push Devices Endpoints (Sprint 30)
            Route::get('/loyalty', [CustomerLoyaltyController::class, 'show'])
                ->name('api.v1.customer.loyalty.show');

            Route::post('/reviews/{review}/comments', [CustomerReviewCommentController::class, 'store'])
                ->middleware('throttle:api.customer.review-comments')
                ->name('api.v1.customer.reviews.comments.store');

            Route::prefix('devices')->group(function (): void {
                Route::post('/', [CustomerDeviceController::class, 'register'])
                    ->name('api.v1.customer.devices.register');
                Route::delete('/{device}', [CustomerDeviceController::class, 'destroy'])
                    ->name('api.v1.customer.devices.destroy');
            });

            // Customer Checkout & Order Creation Endpoints (Sprint 31)
            Route::post('/checkout/preview', [CustomerCheckoutController::class, 'preview'])
                ->name('api.v1.customer.checkout.preview');
            Route::post('/orders', [CustomerCheckoutController::class, 'createOrder'])
                ->middleware('idempotent:required')
                ->name('api.v1.customer.orders.store');
            Route::post('/payments/initiate', [CustomerPaymentController::class, 'initiate'])
                ->name('api.v1.customer.payments.initiate');
            Route::post('/payments/{paymentAttempt}/confirm', [CustomerPaymentController::class, 'confirm'])
                ->name('api.v1.customer.payments.confirm');
            Route::get('/orders', [CustomerOrderController::class, 'index'])
                ->name('api.v1.customer.orders.index');
            Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])
                ->name('api.v1.customer.orders.show');
            Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking'])
                ->name('api.v1.customer.orders.tracking');
            Route::post('/orders/{order}/confirm-delivery', [CustomerOrderController::class, 'confirmDelivery'])
                ->middleware('idempotent')
                ->name('api.v1.customer.orders.confirm-delivery');
            Route::get('/orders/{order}/ratings', [CustomerOrderController::class, 'ratingsStatus'])
                ->name('api.v1.customer.orders.ratings.status');
            Route::post('/orders/{order}/ratings', [CustomerOrderController::class, 'submitRatings'])
                ->middleware('idempotent')
                ->name('api.v1.customer.orders.ratings.store');
            Route::post('/orders/{order}/reorder', [CustomerOrderController::class, 'reorder'])
                ->middleware('idempotent')
                ->name('api.v1.customer.orders.reorder');

            // Customer Support Live Chat
            Route::prefix('support')->group(function (): void {
                Route::post('/firebase-token', [CustomerSupportChatController::class, 'firebaseToken'])
                    ->middleware('throttle:30,1')
                    ->name('api.v1.customer.support.firebase-token');
                Route::get('/conversations', [CustomerSupportChatController::class, 'index'])
                    ->name('api.v1.customer.support.conversations.index');
                Route::post('/conversations', [CustomerSupportChatController::class, 'store'])
                    ->middleware('throttle:10,1')
                    ->name('api.v1.customer.support.conversations.store');
                Route::get('/conversations/{conversation}', [CustomerSupportChatController::class, 'show'])
                    ->name('api.v1.customer.support.conversations.show');
                Route::get('/conversations/{conversation}/messages', [CustomerSupportChatController::class, 'messages'])
                    ->name('api.v1.customer.support.conversations.messages');
                Route::post('/conversations/{conversation}/messages', [CustomerSupportChatController::class, 'storeMessage'])
                    ->middleware('throttle:60,1')
                    ->name('api.v1.customer.support.conversations.messages.store');
                Route::post('/conversations/{conversation}/read', [CustomerSupportChatController::class, 'markRead'])
                    ->name('api.v1.customer.support.conversations.read');
                Route::get('/conversations/{conversation}/messages/{message}/attachment', [CustomerSupportChatController::class, 'attachment'])
                    ->name('api.v1.customer.support.conversations.messages.attachment');
            });
        });
    });

    Route::prefix('admin')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login')->name('api.admin.login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.admin.logout');
            Route::get('/profile', [AuthController::class, 'profile'])->middleware('permission:admin.profile.view')->name('api.admin.profile.show');
            Route::patch('/profile', [AuthController::class, 'updateProfile'])->middleware('permission:admin.profile.update')->name('api.admin.profile.update');
            Route::put('/password', [AuthController::class, 'changePassword'])->middleware(['permission:admin.password.change', 'throttle:password-change'])->name('api.admin.password.update');

            Route::get('/users', [AdminUserController::class, 'index'])->middleware('permission:admin.users.view')->name('api.admin.users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->middleware('permission:admin.users.create')->name('api.admin.users.store');
            Route::patch('/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:admin.users.update')->name('api.admin.users.update');
            Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate'])->middleware('permission:admin.users.activate')->name('api.admin.users.activate');
            Route::patch('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->middleware('permission:admin.users.deactivate')->name('api.admin.users.deactivate');
            Route::put('/users/{user}/roles', [AdminUserController::class, 'roles'])->middleware('permission:admin.roles.assign')->name('api.admin.users.roles');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:admin.roles.view')->name('api.admin.roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:admin.roles.create')->name('api.admin.roles.store');
            Route::patch('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:admin.roles.update')->name('api.admin.roles.update');
            Route::put('/roles/{role}/permissions', [RoleController::class, 'permissions'])->middleware('permission:admin.roles.assign')->name('api.admin.roles.permissions');
            Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:admin.permissions.view')->name('api.admin.permissions.index');

            foreach ([
                'currencies' => 'currencies', 'units' => 'units', 'governorates' => 'locations',
                'cities' => 'locations', 'districts' => 'locations', 'brands' => 'brands',
                'categories' => 'categories', 'warehouses' => 'warehouses',
            ] as $entity => $permission) {
                Route::get("/$entity", [MasterDataController::class, 'index'])->middleware("permission:admin.$permission.view");
                Route::post("/$entity", [MasterDataController::class, 'store'])->middleware("permission:admin.$permission.create");
                Route::patch("/$entity/{masterData}", [MasterDataController::class, 'update'])->middleware("permission:admin.$permission.update");
                Route::patch("/$entity/{masterData}/activate", [MasterDataController::class, 'activate'])->middleware("permission:admin.$permission.activate");
                Route::patch("/$entity/{masterData}/deactivate", [MasterDataController::class, 'deactivate'])->middleware("permission:admin.$permission.deactivate");
            }
            Route::get('/exchange-rates', [MasterDataController::class, 'exchangeRates'])->middleware('permission:admin.exchange_rates.view');
            Route::post('/exchange-rates', [MasterDataController::class, 'storeExchangeRate'])->middleware('permission:admin.exchange_rates.create');
            Route::patch('/exchange-rates/{exchangeRate}', [MasterDataController::class, 'updateExchangeRate'])->middleware('permission:admin.exchange_rates.update');
            Route::get('/settings', [MasterDataController::class, 'settings'])->middleware('permission:admin.settings.view');
            Route::put('/settings', [MasterDataController::class, 'updateSettings'])->middleware('permission:admin.settings.update');
        });
    });
});
