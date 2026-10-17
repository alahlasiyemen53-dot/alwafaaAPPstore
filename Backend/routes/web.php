<?php

use App\Http\Controllers\AdminAccountingPeriodWebController;
use App\Http\Controllers\AdminAccountWebController;
use App\Http\Controllers\AdminAttributeWebController;
use App\Http\Controllers\AdminBankAccountWebController;
use App\Http\Controllers\AdminBundleWebController;
use App\Http\Controllers\AdminCashboxWebController;
use App\Http\Controllers\AdminChartOfAccountsWebController;
use App\Http\Controllers\AdminCommerceReportWebController;
use App\Http\Controllers\AdminCouponWebController;
use App\Http\Controllers\AdminCostPriceWebController;
use App\Http\Controllers\AdminCustomerAddressWebController;
use App\Http\Controllers\AdminCustomerOtpWebController;
use App\Http\Controllers\AdminCustomerReportWebController;
use App\Http\Controllers\AdminCustomerTierWebController;
use App\Http\Controllers\AdminCustomerWalletWebController;
use App\Http\Controllers\AdminCustomerPaymentMethodWebController;
use App\Http\Controllers\AdminCustomerWebController;
use App\Http\Controllers\AdminDeliveryWebController;
use App\Http\Controllers\AdminDriverCodSettlementWebController;
use App\Http\Controllers\AdminDriverWebController;
use App\Http\Controllers\AdminElectronicWalletWebController;
use App\Http\Controllers\AdminFinancialReportsWebController;
use App\Http\Controllers\AdminHomeBannerWebController;
use App\Http\Controllers\AdminHomeSectionWebController;
use App\Http\Controllers\AdminInventoryWebController;
use App\Http\Controllers\AdminJournalEntryWebController;
use App\Http\Controllers\AdminLoyaltyWebController;
use App\Http\Controllers\AdminManagementDashboardWebController;
use App\Http\Controllers\AdminGovernorateDeliveryMethodWebController;
use App\Http\Controllers\AdminMasterDataWebController;
use App\Http\Controllers\AdminNotificationWebController;
use App\Http\Controllers\AdminOperationsReportWebController;
use App\Http\Controllers\AdminOrderWebController;
use App\Http\Controllers\AdminProductMediaWebController;
use App\Http\Controllers\AdminProductImportWebController;
use App\Http\Controllers\AdminProductReviewWebController;
use App\Http\Controllers\AdminProductVariantWebController;
use App\Http\Controllers\AdminProductCollectionWebController;
use App\Http\Controllers\AdminProductWebController;
use App\Http\Controllers\AdminPromotionWebController;
use App\Http\Controllers\AdminPurchaseInvoiceWebController;
use App\Http\Controllers\AdminPurchaseOrderWebController;
use App\Http\Controllers\AdminPurchaseReceiptWebController;
use App\Http\Controllers\AdminRoleWebController;
use App\Http\Controllers\AdminSupportChatWebController;
use App\Http\Controllers\AdminSalesReturnWebController;
use App\Http\Controllers\AdminSearchOperationsWebController;
use App\Http\Controllers\AdminSourcingWebController;
use App\Http\Controllers\AdminStockAdjustmentWebController;
use App\Http\Controllers\AdminStockMovementWebController;
use App\Http\Controllers\AdminStockTransferWebController;
use App\Http\Controllers\AdminSupplierWebController;
use App\Http\Controllers\AdminTreasuryCurrencyAccountWebController;
use App\Http\Controllers\AdminTreasuryReportsWebController;
use App\Http\Controllers\AdminTreasuryTransactionWebController;
use App\Http\Controllers\AdminTreasuryTransferWebController;
use App\Http\Controllers\AdminUserWebController;
use App\Http\Controllers\AdminVariantMediaWebController;
use App\Http\Controllers\AdminWebController;
use App\Http\Controllers\AdminWishlistWebController;
use App\Http\Controllers\AdminSecuritySessionWebController;
use App\Http\Controllers\DeepLinkWebController;
use App\Http\Controllers\Marketing\MarketingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingPageController::class, 'home'])->name('marketing.home');
Route::get('/about', [MarketingPageController::class, 'about'])->name('marketing.about');
Route::get('/contact', [MarketingPageController::class, 'contact'])->name('marketing.contact');
Route::get('/privacy', [MarketingPageController::class, 'privacy'])->name('marketing.privacy');
Route::get('/delete-account', [MarketingPageController::class, 'deleteAccount'])->name('marketing.delete-account');

// First-Party Deep Link & Social Share Routes
Route::get('/p/{product}', [DeepLinkWebController::class, 'product'])->name('deep-link.product');
Route::get('/offers/{offer}', [DeepLinkWebController::class, 'offer'])->name('deep-link.offer');
Route::get('/c/{collection}', [DeepLinkWebController::class, 'collection'])->name('deep-link.collection');
Route::get('/.well-known/assetlinks.json', [DeepLinkWebController::class, 'assetLinks'])->name('deep-link.assetlinks');
Route::get('/.well-known/apple-app-site-association', [DeepLinkWebController::class, 'appleAppSiteAssociation'])->name('deep-link.apple-app-site-association');

Route::get('/admin/login', [AdminWebController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AdminWebController::class, 'authenticate'])->middleware('throttle:admin-login')->name('admin.authenticate');
Route::middleware(['auth', 'admin.active', 'admin.session'])->prefix('admin')->group(function (): void {
    Route::get('/', AdminManagementDashboardWebController::class)->middleware('permission:admin.dashboard.view')->name('admin.dashboard');
    Route::post('/logout', [AdminWebController::class, 'logout'])->name('admin.logout');

    Route::get('/profile', [AdminAccountWebController::class, 'edit'])->middleware('permission:admin.profile.view')->name('admin.profile.edit');
    Route::put('/profile', [AdminAccountWebController::class, 'update'])->middleware('permission:admin.profile.update')->name('admin.profile.update');
    Route::get('/password', [AdminAccountWebController::class, 'password'])->middleware('permission:admin.password.change')->name('admin.password.edit');
    Route::put('/password', [AdminAccountWebController::class, 'updatePassword'])->middleware(['permission:admin.password.change', 'password.confirm', 'throttle:password-change'])->name('admin.password.update.web');

    Route::get('/security/sessions', [AdminSecuritySessionWebController::class, 'index'])
        ->middleware('permission:admin.security.sessions.view')
        ->name('admin.security.sessions.index');
    Route::delete('/security/sessions/others', [AdminSecuritySessionWebController::class, 'destroyOthers'])
        ->middleware(['permission:admin.security.sessions.manage', 'password.confirm'])
        ->name('admin.security.sessions.destroy-others');
    Route::delete('/security/sessions/{session}', [AdminSecuritySessionWebController::class, 'destroy'])
        ->middleware(['permission:admin.security.sessions.manage', 'password.confirm'])
        ->name('admin.security.sessions.destroy');

    Route::get('/confirm-password', [AdminSecuritySessionWebController::class, 'showConfirmPassword'])->name('password.confirm');
    Route::post('/confirm-password', [AdminSecuritySessionWebController::class, 'confirmPassword'])->middleware('throttle:password-change')->name('password.confirm.store');

    Route::get('/users', [AdminUserWebController::class, 'index'])->middleware('permission:admin.users.view')->name('admin.users.web.index');
    Route::get('/users/create', [AdminUserWebController::class, 'create'])->middleware('permission:admin.users.create')->name('admin.users.web.create');
    Route::post('/users', [AdminUserWebController::class, 'store'])->middleware(['permission:admin.users.create', 'password.confirm'])->name('admin.users.web.store');
    Route::get('/users/{user}/edit', [AdminUserWebController::class, 'edit'])->middleware('permission:admin.users.update')->name('admin.users.web.edit');
    Route::put('/users/{user}', [AdminUserWebController::class, 'update'])->middleware(['permission:admin.users.update', 'password.confirm'])->name('admin.users.web.update');
    Route::patch('/users/{user}/activate', [AdminUserWebController::class, 'activate'])->middleware(['permission:admin.users.activate', 'password.confirm'])->name('admin.users.web.activate');
    Route::patch('/users/{user}/deactivate', [AdminUserWebController::class, 'deactivate'])->middleware(['permission:admin.users.deactivate', 'password.confirm'])->name('admin.users.web.deactivate');

    Route::get('/roles', [AdminRoleWebController::class, 'index'])->middleware('permission:admin.roles.view')->name('admin.roles.web.index');
    Route::get('/roles/create', [AdminRoleWebController::class, 'create'])->middleware('permission:admin.roles.create')->name('admin.roles.web.create');
    Route::post('/roles', [AdminRoleWebController::class, 'store'])->middleware(['permission:admin.roles.create', 'password.confirm'])->name('admin.roles.web.store');
    Route::get('/roles/{role}/edit', [AdminRoleWebController::class, 'edit'])->middleware('permission:admin.roles.update')->name('admin.roles.web.edit');
    Route::put('/roles/{role}', [AdminRoleWebController::class, 'update'])->middleware(['permission:admin.roles.update', 'password.confirm'])->name('admin.roles.web.update');
    Route::put('/roles/{role}/permissions', [AdminRoleWebController::class, 'permissions'])->middleware(['permission:admin.roles.assign', 'password.confirm'])->name('admin.roles.web.permissions');
    Route::get('/permissions', [AdminRoleWebController::class, 'permissionIndex'])->middleware('permission:admin.permissions.view')->name('admin.permissions.web.index');

    Route::get('/support-chat', [AdminSupportChatWebController::class, 'index'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.index');
    Route::get('/support-chat/live', [AdminSupportChatWebController::class, 'liveIndex'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.live-index');
    Route::get('/support-chat/unread-count', [AdminSupportChatWebController::class, 'unreadCount'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.unread-count');
    Route::get('/support-chat/firebase-token', [AdminSupportChatWebController::class, 'firebaseToken'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.firebase-token');
    Route::get('/support-chat/{conversation}', [AdminSupportChatWebController::class, 'show'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.show');
    Route::get('/support-chat/{conversation}/live', [AdminSupportChatWebController::class, 'liveMessages'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.live-messages');
    Route::post('/support-chat/{conversation}/reply', [AdminSupportChatWebController::class, 'reply'])->middleware('permission:admin.support_chat.reply')->name('admin.support-chat.reply');
    Route::post('/support-chat/{conversation}/assign', [AdminSupportChatWebController::class, 'assign'])->middleware('permission:admin.support_chat.assign')->name('admin.support-chat.assign');
    Route::post('/support-chat/{conversation}/close', [AdminSupportChatWebController::class, 'close'])->middleware('permission:admin.support_chat.close')->name('admin.support-chat.close');
    Route::post('/support-chat/{conversation}/reopen', [AdminSupportChatWebController::class, 'reopen'])->middleware('permission:admin.support_chat.reopen')->name('admin.support-chat.reopen');
    Route::get('/support-chat/{conversation}/messages/{message}/attachment', [AdminSupportChatWebController::class, 'attachment'])->middleware('permission:admin.support_chat.view')->name('admin.support-chat.attachment');

    Route::get('/products', [AdminProductWebController::class, 'index'])->middleware('permission:admin.products.view')->name('admin.products.index');
    Route::get('/cost-prices', [AdminCostPriceWebController::class, 'index'])->middleware('permission:admin.products.update')->name('admin.cost-prices.index');
    Route::put('/cost-prices', [AdminCostPriceWebController::class, 'update'])->middleware('permission:admin.products.update')->name('admin.cost-prices.update');
    Route::get('/products/import', [AdminProductImportWebController::class, 'create'])->middleware('permission:admin.products.create')->name('admin.products.import.create');
    Route::get('/products/import/template', [AdminProductImportWebController::class, 'template'])->middleware('permission:admin.products.create')->name('admin.products.import.template');
    Route::post('/products/import/preview', [AdminProductImportWebController::class, 'preview'])->middleware('permission:admin.products.create')->name('admin.products.import.preview');
    Route::post('/products/import/confirm', [AdminProductImportWebController::class, 'confirm'])->middleware('permission:admin.products.create')->name('admin.products.import.confirm');
    Route::get('/products/create', [AdminProductWebController::class, 'create'])->middleware('permission:admin.products.create')->name('admin.products.create');
    Route::post('/products', [AdminProductWebController::class, 'store'])->middleware('permission:admin.products.create')->name('admin.products.store');
    Route::get('/products/{product}', [AdminProductWebController::class, 'show'])->middleware('permission:admin.products.view')->name('admin.products.show');
    Route::get('/products/{product}/edit', [AdminProductWebController::class, 'edit'])->middleware('permission:admin.products.update')->name('admin.products.edit');
    Route::put('/products/{product}', [AdminProductWebController::class, 'update'])->middleware('permission:admin.products.update')->name('admin.products.update');
    Route::patch('/products/{product}/activate', [AdminProductWebController::class, 'activate'])->middleware('permission:admin.products.activate')->name('admin.products.activate');
    Route::patch('/products/{product}/deactivate', [AdminProductWebController::class, 'deactivate'])->middleware('permission:admin.products.deactivate')->name('admin.products.deactivate');

    Route::post('/products/{product}/variants', [AdminProductVariantWebController::class, 'store'])->middleware('permission:admin.variants.create')->name('admin.products.variants.store');
    Route::put('/products/{product}/variants/{variant}', [AdminProductVariantWebController::class, 'update'])->middleware('permission:admin.variants.update')->name('admin.products.variants.update');
    Route::patch('/products/{product}/variants/{variant}/set-default', [AdminProductVariantWebController::class, 'setDefault'])->middleware('permission:admin.variants.set_default')->name('admin.products.variants.set-default');
    Route::patch('/products/{product}/variants/{variant}/activate', [AdminProductVariantWebController::class, 'activate'])->middleware('permission:admin.variants.activate')->name('admin.products.variants.activate');
    Route::patch('/products/{product}/variants/{variant}/deactivate', [AdminProductVariantWebController::class, 'deactivate'])->middleware('permission:admin.variants.deactivate')->name('admin.products.variants.deactivate');
    Route::put('/products/{product}/variants/{variant}/price', [AdminProductVariantWebController::class, 'updatePrice'])->middleware('permission:admin.variant_prices.update')->name('admin.products.variants.price.update');

    // Product Media Routes
    Route::post('/products/{product}/media', [AdminProductMediaWebController::class, 'upload'])->middleware(['permission:admin.products.media.upload', 'throttle:media-upload'])->name('admin.products.media.upload');
    Route::delete('/products/{product}/media/{media}', [AdminProductMediaWebController::class, 'destroy'])->middleware('permission:admin.products.media.delete')->name('admin.products.media.destroy');
    Route::patch('/products/{product}/media/{media}/primary', [AdminProductMediaWebController::class, 'setPrimary'])->middleware('permission:admin.products.media.update')->name('admin.products.media.primary');
    Route::post('/products/{product}/media/reorder', [AdminProductMediaWebController::class, 'reorder'])->middleware('permission:admin.products.media.update')->name('admin.products.media.reorder');

    // Variant Media Routes
    Route::post('/products/{product}/variants/{variant}/media', [AdminVariantMediaWebController::class, 'upload'])->middleware(['permission:admin.variants.media.upload', 'throttle:media-upload'])->name('admin.products.variants.media.upload');
    Route::delete('/products/{product}/variants/{variant}/media/{media}', [AdminVariantMediaWebController::class, 'destroy'])->middleware('permission:admin.variants.media.delete')->name('admin.products.variants.media.destroy');
    Route::patch('/products/{product}/variants/{variant}/media/{media}/primary', [AdminVariantMediaWebController::class, 'setPrimary'])->middleware('permission:admin.variants.media.update')->name('admin.products.variants.media.primary');
    Route::post('/products/{product}/variants/{variant}/media/reorder', [AdminVariantMediaWebController::class, 'reorder'])->middleware('permission:admin.variants.media.update')->name('admin.products.variants.media.reorder');

    Route::get('/attributes', [AdminAttributeWebController::class, 'index'])->middleware('permission:admin.attributes.view')->name('admin.attributes.index');
    Route::get('/attributes/create', [AdminAttributeWebController::class, 'create'])->middleware('permission:admin.attributes.create')->name('admin.attributes.create');
    Route::post('/attributes', [AdminAttributeWebController::class, 'store'])->middleware('permission:admin.attributes.create')->name('admin.attributes.store');
    Route::get('/attributes/{attribute}/edit', [AdminAttributeWebController::class, 'edit'])->middleware('permission:admin.attributes.update')->name('admin.attributes.edit');
    Route::put('/attributes/{attribute}', [AdminAttributeWebController::class, 'update'])->middleware('permission:admin.attributes.update')->name('admin.attributes.update');
    Route::patch('/attributes/{attribute}/activate', [AdminAttributeWebController::class, 'activate'])->middleware('permission:admin.attributes.activate')->name('admin.attributes.activate');
    Route::patch('/attributes/{attribute}/deactivate', [AdminAttributeWebController::class, 'deactivate'])->middleware('permission:admin.attributes.deactivate')->name('admin.attributes.deactivate');
    Route::get('/attributes/{attribute}/values', [AdminAttributeWebController::class, 'values'])->middleware('permission:admin.attributes.view')->name('admin.attributes.values.index');
    Route::post('/attributes/{attribute}/values', [AdminAttributeWebController::class, 'storeValue'])->middleware('permission:admin.attributes.create')->name('admin.attributes.values.store');
    Route::put('/attributes/{attribute}/values/{value}', [AdminAttributeWebController::class, 'updateValue'])->middleware('permission:admin.attributes.update')->name('admin.attributes.values.update');
    Route::patch('/attributes/{attribute}/values/{value}/activate', [AdminAttributeWebController::class, 'activateValue'])->middleware('permission:admin.attributes.activate')->name('admin.attributes.values.activate');
    Route::patch('/attributes/{attribute}/values/{value}/deactivate', [AdminAttributeWebController::class, 'deactivateValue'])->middleware('permission:admin.attributes.deactivate')->name('admin.attributes.values.deactivate');

    // Customers Routes
    Route::get('/customers', [AdminCustomerWebController::class, 'index'])->middleware('permission:admin.customers.view')->name('admin.customers.index');
    Route::get('/customers/create', [AdminCustomerWebController::class, 'create'])->middleware('permission:admin.customers.create')->name('admin.customers.create');
    Route::get('/customers/otp-challenges', [AdminCustomerOtpWebController::class, 'index'])->middleware('permission:admin.otp.view')->name('admin.otp-challenges.index');
    Route::post('/customers', [AdminCustomerWebController::class, 'store'])->middleware('permission:admin.customers.create')->name('admin.customers.store');
    Route::get('/customers/{customer}', [AdminCustomerWebController::class, 'show'])->middleware('permission:admin.customers.view')->name('admin.customers.show');
    Route::get('/customers/{customer}/edit', [AdminCustomerWebController::class, 'edit'])->middleware('permission:admin.customers.update')->name('admin.customers.edit');
    Route::put('/customers/{customer}', [AdminCustomerWebController::class, 'update'])->middleware('permission:admin.customers.update')->name('admin.customers.update');
    Route::patch('/customers/{customer}/activate', [AdminCustomerWebController::class, 'activate'])->middleware('permission:admin.customers.activate')->name('admin.customers.activate');
    Route::patch('/customers/{customer}/deactivate', [AdminCustomerWebController::class, 'deactivate'])->middleware('permission:admin.customers.deactivate')->name('admin.customers.deactivate');
    Route::post('/customers/{customer}/verify-phone', [AdminCustomerWebController::class, 'verifyPhone'])->middleware('permission:admin.customers.verify_phone')->name('admin.customers.verify-phone');

    // Customer Addresses Routes
    Route::post('/customers/{customer}/addresses', [AdminCustomerAddressWebController::class, 'store'])->middleware('permission:admin.customer_addresses.create')->name('admin.customers.addresses.store');
    Route::put('/customers/{customer}/addresses/{address}', [AdminCustomerAddressWebController::class, 'update'])->middleware('permission:admin.customer_addresses.update')->name('admin.customers.addresses.update');
    Route::delete('/customers/{customer}/addresses/{address}', [AdminCustomerAddressWebController::class, 'destroy'])->middleware('permission:admin.customer_addresses.delete')->name('admin.customers.addresses.destroy');
    Route::patch('/customers/{customer}/addresses/{address}/default', [AdminCustomerAddressWebController::class, 'setDefault'])->middleware('permission:admin.customer_addresses.set_default')->name('admin.customers.addresses.set-default');

    // Customer Tier Assignment
    Route::post('/customers/{customer}/tier', [AdminCustomerWebController::class, 'assignTier'])->middleware('permission:admin.customer_tiers.assign')->name('admin.customers.tier.assign');

    // Customer Tiers & VIP Management Routes (Sprint 21.8)
    Route::get('/customer-tiers', [AdminCustomerTierWebController::class, 'index'])->middleware('permission:admin.customer_tiers.view')->name('admin.customer-tiers.index');
    Route::get('/customer-tiers/create', [AdminCustomerTierWebController::class, 'create'])->middleware('permission:admin.customer_tiers.create')->name('admin.customer-tiers.create');
    Route::post('/customer-tiers', [AdminCustomerTierWebController::class, 'store'])->middleware('permission:admin.customer_tiers.create')->name('admin.customer-tiers.store');
    Route::get('/customer-tiers/{tier}/edit', [AdminCustomerTierWebController::class, 'edit'])->middleware('permission:admin.customer_tiers.update')->name('admin.customer-tiers.edit');
    Route::put('/customer-tiers/{tier}', [AdminCustomerTierWebController::class, 'update'])->middleware('permission:admin.customer_tiers.update')->name('admin.customer-tiers.update');
    Route::delete('/customer-tiers/{tier}', [AdminCustomerTierWebController::class, 'destroy'])->middleware('permission:admin.customer_tiers.update')->name('admin.customer-tiers.destroy');
    Route::patch('/customer-tiers/{tier}/toggle-status', [AdminCustomerTierWebController::class, 'toggleStatus'])->middleware('permission:admin.customer_tiers.activate|admin.customer_tiers.deactivate')->name('admin.customer-tiers.toggle-status');

    // Customer Wishlists Routes (Sprint 21.6)
    Route::get('/wishlists', [AdminWishlistWebController::class, 'index'])->middleware('permission:admin.wishlists.view')->name('admin.wishlists.index');

    // Product Reviews Routes (Sprint 21.7)
    Route::get('/reviews', [AdminProductReviewWebController::class, 'index'])->middleware('permission:admin.reviews.view')->name('admin.reviews.index');
    Route::get('/reviews/{review}', [AdminProductReviewWebController::class, 'show'])->middleware('permission:admin.reviews.view')->name('admin.reviews.show');
    Route::post('/reviews/{review}/moderate', [AdminProductReviewWebController::class, 'moderate'])->middleware('permission:admin.reviews.moderate')->name('admin.reviews.moderate');
    Route::post('/reviews/{review}/comments', [AdminProductReviewWebController::class, 'reply'])->middleware('permission:admin.reviews.moderate')->name('admin.reviews.comments.reply');
    Route::post('/reviews/{review}/comments/{comment}/moderate', [AdminProductReviewWebController::class, 'moderateComment'])->middleware('permission:admin.reviews.moderate')->name('admin.reviews.comments.moderate');

    // Sales Orders Routes
    Route::get('/orders', [AdminOrderWebController::class, 'index'])->middleware('permission:admin.orders.view')->name('admin.orders.index');
    Route::get('/orders/create', [AdminOrderWebController::class, 'create'])->middleware('permission:admin.orders.create')->name('admin.orders.create');
    Route::post('/orders', [AdminOrderWebController::class, 'store'])->middleware('permission:admin.orders.create')->name('admin.orders.store');
    Route::get('/orders/{order}', [AdminOrderWebController::class, 'show'])->middleware('permission:admin.orders.view')->name('admin.orders.show');
    Route::get('/orders/{order}/print', [AdminOrderWebController::class, 'print'])->middleware('permission:admin.orders.view')->name('admin.orders.print');
    Route::get('/orders/{order}/edit', [AdminOrderWebController::class, 'edit'])->middleware('permission:admin.orders.update')->name('admin.orders.edit');
    Route::put('/orders/{order}', [AdminOrderWebController::class, 'update'])->middleware('permission:admin.orders.update')->name('admin.orders.update');
    Route::post('/orders/{order}/confirm', [AdminOrderWebController::class, 'confirm'])->middleware('permission:admin.orders.confirm')->name('admin.orders.confirm');
    Route::post('/orders/{order}/cancel', [AdminOrderWebController::class, 'cancel'])->middleware('permission:admin.orders.cancel')->name('admin.orders.cancel');
    Route::post('/orders/{order}/assign-pickup-driver', [AdminOrderWebController::class, 'assignPickupDriver'])->middleware('permission:admin.deliveries.assign')->name('admin.orders.assign-pickup-driver');
    Route::post('/orders/pricing-preview', [AdminOrderWebController::class, 'previewPricing'])->middleware('permission:admin.orders.create|admin.orders.update')->name('admin.orders.pricing-preview');
    Route::get('/orders-data/customer-addresses/{customer}', [AdminOrderWebController::class, 'customerAddresses'])->middleware('permission:admin.orders.view')->name('admin.orders.customer-addresses');
    Route::get('/orders-data/variant-stock', [AdminOrderWebController::class, 'variantStock'])->middleware('permission:admin.orders.view')->name('admin.orders.variant-stock');

    // Sales Returns Routes
    Route::get('/sales-returns', [AdminSalesReturnWebController::class, 'index'])->middleware('permission:admin.sales_returns.view')->name('admin.sales-returns.index');
    Route::get('/sales-returns/create', [AdminSalesReturnWebController::class, 'create'])->middleware('permission:admin.sales_returns.create')->name('admin.sales-returns.create');
    Route::post('/sales-returns', [AdminSalesReturnWebController::class, 'store'])->middleware('permission:admin.sales_returns.create')->name('admin.sales-returns.store');
    Route::get('/sales-returns/{salesReturn}', [AdminSalesReturnWebController::class, 'show'])->middleware('permission:admin.sales_returns.view')->name('admin.sales-returns.show');
    Route::get('/sales-returns/{salesReturn}/edit', [AdminSalesReturnWebController::class, 'edit'])->middleware('permission:admin.sales_returns.update')->name('admin.sales-returns.edit');
    Route::put('/sales-returns/{salesReturn}', [AdminSalesReturnWebController::class, 'update'])->middleware('permission:admin.sales_returns.update')->name('admin.sales-returns.update');
    Route::post('/sales-returns/{salesReturn}/post', [AdminSalesReturnWebController::class, 'post'])->middleware('permission:admin.sales_returns.post')->name('admin.sales-returns.post');
    Route::post('/sales-returns/{salesReturn}/cancel', [AdminSalesReturnWebController::class, 'cancel'])->middleware('permission:admin.sales_returns.cancel')->name('admin.sales-returns.cancel');
    Route::get('/sales-returns-data/order-items/{order}', [AdminSalesReturnWebController::class, 'getOrderItems'])->middleware('permission:admin.sales_returns.view')->name('admin.sales-returns.order-items');

    // Delivery Drivers Routes
    Route::get('/drivers', [AdminDriverWebController::class, 'index'])->middleware('permission:admin.drivers.view')->name('admin.drivers.index');
    Route::get('/drivers/create', [AdminDriverWebController::class, 'create'])->middleware('permission:admin.drivers.create')->name('admin.drivers.create');
    Route::post('/drivers', [AdminDriverWebController::class, 'store'])->middleware('permission:admin.drivers.create')->name('admin.drivers.store');
    Route::get('/drivers/{driver}/edit', [AdminDriverWebController::class, 'edit'])->middleware('permission:admin.drivers.update')->name('admin.drivers.edit');
    Route::put('/drivers/{driver}', [AdminDriverWebController::class, 'update'])->middleware('permission:admin.drivers.update')->name('admin.drivers.update');
    Route::patch('/drivers/{driver}/toggle-status', [AdminDriverWebController::class, 'toggleStatus'])->middleware('permission:admin.drivers.activate|admin.drivers.deactivate')->name('admin.drivers.toggle-status');

    // Deliveries Routes
    Route::get('/deliveries', [AdminDeliveryWebController::class, 'index'])->middleware('permission:admin.deliveries.view')->name('admin.deliveries.index');
    Route::get('/deliveries/{delivery}', [AdminDeliveryWebController::class, 'show'])->middleware('permission:admin.deliveries.view')->name('admin.deliveries.show');
    Route::post('/orders/{order}/assign-delivery', [AdminDeliveryWebController::class, 'assign'])->middleware('permission:admin.deliveries.assign')->name('admin.deliveries.assign');
    Route::post('/deliveries/{delivery}/delivered', [AdminDeliveryWebController::class, 'markDelivered'])->middleware('permission:admin.deliveries.mark_delivered')->name('admin.deliveries.mark-delivered');
    Route::post('/deliveries/{delivery}/failed', [AdminDeliveryWebController::class, 'markFailed'])->middleware('permission:admin.deliveries.mark_failed')->name('admin.deliveries.mark-failed');
    Route::post('/deliveries/{delivery}/return-to-warehouse', [AdminDeliveryWebController::class, 'returnToWarehouse'])->middleware('permission:admin.deliveries.return_to_warehouse')->name('admin.deliveries.return-to-warehouse');

    // Operations Sourcing Queue Routes (legacy per-item) + Purchase Orders (per supplier)
    Route::get('/operations/sourcing', [AdminSourcingWebController::class, 'index'])->middleware('permission:admin.sourcing.view')->name('admin.sourcing.index');
    Route::post('/operations/sourcing/{requirement}/assign-driver', [AdminSourcingWebController::class, 'assignDriver'])->middleware('permission:admin.sourcing.manage')->name('admin.sourcing.assign-driver');
    Route::post('/operations/sourcing/{requirement}/confirm-collected', [AdminSourcingWebController::class, 'confirmCollected'])->middleware('permission:admin.sourcing.manage')->name('admin.sourcing.confirm-collected');
    Route::post('/operations/sourcing/{requirement}/mark-failed', [AdminSourcingWebController::class, 'markFailed'])->middleware('permission:admin.sourcing.manage')->name('admin.sourcing.mark-failed');
    Route::post('/operations/sourcing/{requirement}/cancel', [AdminSourcingWebController::class, 'cancel'])->middleware('permission:admin.sourcing.manage')->name('admin.sourcing.cancel');

    Route::get('/operations/purchase-orders', [AdminPurchaseOrderWebController::class, 'index'])->middleware('permission:admin.sourcing.view')->name('admin.purchase-orders.index');
    Route::get('/operations/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderWebController::class, 'show'])->middleware('permission:admin.sourcing.view')->name('admin.purchase-orders.show');
    Route::post('/operations/purchase-orders/{purchaseOrder}/confirm-collected', [AdminPurchaseOrderWebController::class, 'confirmCollected'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.confirm-collected');
    Route::post('/operations/purchase-orders/{purchaseOrder}/change-supplier', [AdminPurchaseOrderWebController::class, 'changeSupplier'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.change-supplier');
    Route::post('/operations/purchase-orders/{purchaseOrder}/hold', [AdminPurchaseOrderWebController::class, 'hold'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.hold');
    Route::post('/operations/purchase-orders/{purchaseOrder}/mark-failed', [AdminPurchaseOrderWebController::class, 'markFailed'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.mark-failed');
    Route::post('/operations/purchase-orders/{purchaseOrder}/cancel', [AdminPurchaseOrderWebController::class, 'cancel'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.cancel');
    Route::post('/operations/purchase-order-items/{purchaseOrderItem}/cancel', [AdminPurchaseOrderWebController::class, 'cancelItem'])->middleware('permission:admin.sourcing.manage')->name('admin.purchase-orders.cancel-item');

    // Suppliers Routes
    Route::get('/suppliers', [AdminSupplierWebController::class, 'index'])->middleware('permission:admin.suppliers.view')->name('admin.suppliers.index');
    Route::get('/suppliers/create', [AdminSupplierWebController::class, 'create'])->middleware('permission:admin.suppliers.create')->name('admin.suppliers.create');
    Route::post('/suppliers', [AdminSupplierWebController::class, 'store'])->middleware('permission:admin.suppliers.create')->name('admin.suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [AdminSupplierWebController::class, 'edit'])->middleware('permission:admin.suppliers.update')->name('admin.suppliers.edit');
    Route::put('/suppliers/{supplier}', [AdminSupplierWebController::class, 'update'])->middleware('permission:admin.suppliers.update')->name('admin.suppliers.update');
    Route::patch('/suppliers/{supplier}/toggle-status', [AdminSupplierWebController::class, 'toggleStatus'])->middleware('permission:admin.suppliers.activate|admin.suppliers.deactivate')->name('admin.suppliers.toggle-status');
    Route::get('/suppliers/{supplier}/statement', [AdminSupplierWebController::class, 'statement'])->middleware('permission:admin.suppliers.statement.view')->name('admin.suppliers.statement');

    // Purchase Invoices Routes
    Route::get('/purchases', [AdminPurchaseInvoiceWebController::class, 'index'])->middleware('permission:admin.purchases.view')->name('admin.purchases.index');
    Route::get('/purchases/create', [AdminPurchaseInvoiceWebController::class, 'create'])->middleware('permission:admin.purchases.create')->name('admin.purchases.create');
    Route::post('/purchases', [AdminPurchaseInvoiceWebController::class, 'store'])->middleware('permission:admin.purchases.create')->name('admin.purchases.store');
    Route::get('/purchases/{invoice}', [AdminPurchaseInvoiceWebController::class, 'show'])->middleware('permission:admin.purchases.view')->name('admin.purchases.show');
    Route::get('/purchases/{invoice}/edit', [AdminPurchaseInvoiceWebController::class, 'edit'])->middleware('permission:admin.purchases.update')->name('admin.purchases.edit');
    Route::put('/purchases/{invoice}', [AdminPurchaseInvoiceWebController::class, 'update'])->middleware('permission:admin.purchases.update')->name('admin.purchases.update');
    Route::delete('/purchases/{invoice}', [AdminPurchaseInvoiceWebController::class, 'destroy'])->middleware('permission:admin.purchases.delete')->name('admin.purchases.destroy');
    Route::post('/purchases/{invoice}/post', [AdminPurchaseInvoiceWebController::class, 'post'])->middleware('permission:admin.purchases.post')->name('admin.purchases.post');

    // Inventory Balances Route
    Route::get('/inventory', [AdminInventoryWebController::class, 'index'])->middleware('permission:admin.inventory.view')->name('admin.inventory.index');

    // Purchase Receipts Routes
    Route::get('/purchase-receipts', [AdminPurchaseReceiptWebController::class, 'index'])->middleware('permission:admin.purchase_receipts.view')->name('admin.purchase-receipts.index');
    Route::get('/purchase-receipts/create', [AdminPurchaseReceiptWebController::class, 'create'])->middleware('permission:admin.purchase_receipts.create')->name('admin.purchase-receipts.create');
    Route::post('/purchase-receipts', [AdminPurchaseReceiptWebController::class, 'store'])->middleware('permission:admin.purchase_receipts.create')->name('admin.purchase-receipts.store');
    Route::get('/purchase-receipts/{receipt}', [AdminPurchaseReceiptWebController::class, 'show'])->middleware('permission:admin.purchase_receipts.view')->name('admin.purchase-receipts.show');
    Route::get('/purchase-receipts/{receipt}/edit', [AdminPurchaseReceiptWebController::class, 'edit'])->middleware('permission:admin.purchase_receipts.update')->name('admin.purchase-receipts.edit');
    Route::put('/purchase-receipts/{receipt}', [AdminPurchaseReceiptWebController::class, 'update'])->middleware('permission:admin.purchase_receipts.update')->name('admin.purchase-receipts.update');
    Route::delete('/purchase-receipts/{receipt}', [AdminPurchaseReceiptWebController::class, 'destroy'])->middleware('permission:admin.purchase_receipts.delete')->name('admin.purchase-receipts.destroy');
    Route::post('/purchase-receipts/{receipt}/post', [AdminPurchaseReceiptWebController::class, 'post'])->middleware('permission:admin.purchase_receipts.post')->name('admin.purchase-receipts.post');

    // Stock Transfers Routes
    Route::get('/stock-transfers', [AdminStockTransferWebController::class, 'index'])->middleware('permission:admin.stock_transfers.view')->name('admin.stock-transfers.index');
    Route::get('/stock-transfers/create', [AdminStockTransferWebController::class, 'create'])->middleware('permission:admin.stock_transfers.create')->name('admin.stock-transfers.create');
    Route::post('/stock-transfers', [AdminStockTransferWebController::class, 'store'])->middleware('permission:admin.stock_transfers.create')->name('admin.stock-transfers.store');
    Route::get('/stock-transfers/{transfer}', [AdminStockTransferWebController::class, 'show'])->middleware('permission:admin.stock_transfers.view')->name('admin.stock-transfers.show');
    Route::get('/stock-transfers/{transfer}/edit', [AdminStockTransferWebController::class, 'edit'])->middleware('permission:admin.stock_transfers.update')->name('admin.stock-transfers.edit');
    Route::put('/stock-transfers/{transfer}', [AdminStockTransferWebController::class, 'update'])->middleware('permission:admin.stock_transfers.update')->name('admin.stock-transfers.update');
    Route::delete('/stock-transfers/{transfer}', [AdminStockTransferWebController::class, 'destroy'])->middleware('permission:admin.stock_transfers.delete')->name('admin.stock-transfers.destroy');
    Route::post('/stock-transfers/{transfer}/post', [AdminStockTransferWebController::class, 'post'])->middleware('permission:admin.stock_transfers.post')->name('admin.stock-transfers.post');

    // Stock Adjustments Routes
    Route::get('/stock-adjustments', [AdminStockAdjustmentWebController::class, 'index'])->middleware('permission:admin.stock_adjustments.view')->name('admin.stock-adjustments.index');
    Route::get('/stock-adjustments/create', [AdminStockAdjustmentWebController::class, 'create'])->middleware('permission:admin.stock_adjustments.create')->name('admin.stock-adjustments.create');
    Route::post('/stock-adjustments', [AdminStockAdjustmentWebController::class, 'store'])->middleware('permission:admin.stock_adjustments.create')->name('admin.stock-adjustments.store');
    Route::get('/stock-adjustments/{adjustment}', [AdminStockAdjustmentWebController::class, 'show'])->middleware('permission:admin.stock_adjustments.view')->name('admin.stock-adjustments.show');
    Route::get('/stock-adjustments/{adjustment}/edit', [AdminStockAdjustmentWebController::class, 'edit'])->middleware('permission:admin.stock_adjustments.update')->name('admin.stock-adjustments.edit');
    Route::put('/stock-adjustments/{adjustment}', [AdminStockAdjustmentWebController::class, 'update'])->middleware('permission:admin.stock_adjustments.update')->name('admin.stock-adjustments.update');
    Route::delete('/stock-adjustments/{adjustment}', [AdminStockAdjustmentWebController::class, 'destroy'])->middleware('permission:admin.stock_adjustments.delete')->name('admin.stock-adjustments.destroy');
    Route::post('/stock-adjustments/{adjustment}/post', [AdminStockAdjustmentWebController::class, 'post'])->middleware('permission:admin.stock_adjustments.post')->name('admin.stock-adjustments.post');

    // Stock Movements Routes
    Route::get('/stock-movements', [AdminStockMovementWebController::class, 'index'])->middleware('permission:admin.stock_movements.view')->name('admin.stock-movements.index');

    foreach ([
        'countries' => 'locations',
        'currencies' => 'currencies', 'units' => 'units', 'governorates' => 'locations',
        'cities' => 'locations', 'districts' => 'locations', 'brands' => 'brands',
        'categories' => 'categories', 'warehouses' => 'warehouses',
        'delivery-methods' => 'locations',
    ] as $entity => $permission) {
        Route::get("/master-data/$entity", [AdminMasterDataWebController::class, 'index'])->middleware("permission:admin.$permission.view")->name("admin.master-data.$entity.index");
        Route::get("/master-data/$entity/create", [AdminMasterDataWebController::class, 'create'])->middleware("permission:admin.$permission.create")->name("admin.master-data.$entity.create");
        Route::post("/master-data/$entity", [AdminMasterDataWebController::class, 'store'])->middleware("permission:admin.$permission.create")->name("admin.master-data.$entity.store");
        Route::get("/master-data/$entity/{record}/edit", [AdminMasterDataWebController::class, 'edit'])->middleware("permission:admin.$permission.update")->name("admin.master-data.$entity.edit");
        Route::put("/master-data/$entity/{record}", [AdminMasterDataWebController::class, 'update'])->middleware("permission:admin.$permission.update")->name("admin.master-data.$entity.update");
        Route::patch("/master-data/$entity/{record}/activate", [AdminMasterDataWebController::class, 'activate'])->middleware("permission:admin.$permission.activate")->name("admin.master-data.$entity.activate");
        Route::patch("/master-data/$entity/{record}/deactivate", [AdminMasterDataWebController::class, 'deactivate'])->middleware("permission:admin.$permission.deactivate")->name("admin.master-data.$entity.deactivate");
    }
    Route::get('/master-data/delivery-methods/{deliveryMethod}/manage', [AdminGovernorateDeliveryMethodWebController::class, 'manage'])
        ->middleware('permission:admin.locations.view')
        ->name('admin.master-data.delivery-methods.manage');
    Route::delete('/master-data/countries/{record}', [AdminMasterDataWebController::class, 'destroy'])
        ->middleware('permission:admin.locations.deactivate')
        ->name('admin.master-data.countries.destroy');
    Route::patch('/master-data/countries/{record}/delivery-activate', [AdminMasterDataWebController::class, 'deliveryActivate'])
        ->middleware('permission:admin.locations.activate')
        ->name('admin.master-data.countries.delivery-activate');
    Route::patch('/master-data/countries/{record}/delivery-deactivate', [AdminMasterDataWebController::class, 'deliveryDeactivate'])
        ->middleware('permission:admin.locations.deactivate')
        ->name('admin.master-data.countries.delivery-deactivate');
    Route::patch('/master-data/governorates/{record}/delivery-activate', [AdminMasterDataWebController::class, 'deliveryActivate'])
        ->middleware('permission:admin.locations.activate')
        ->name('admin.master-data.governorates.delivery-activate');
    Route::patch('/master-data/governorates/{record}/delivery-deactivate', [AdminMasterDataWebController::class, 'deliveryDeactivate'])
        ->middleware('permission:admin.locations.deactivate')
        ->name('admin.master-data.governorates.delivery-deactivate');
    Route::post('/master-data/brands/{brand}/logo', [AdminMasterDataWebController::class, 'uploadBrandLogo'])->middleware(['permission:admin.brands.media.upload', 'throttle:media-upload'])->name('admin.master-data.brands.logo.upload');
    Route::delete('/master-data/brands/{brand}/logo', [AdminMasterDataWebController::class, 'deleteBrandLogo'])->middleware('permission:admin.brands.media.delete')->name('admin.master-data.brands.logo.delete');
    Route::post('/master-data/categories/{category}/image', [AdminMasterDataWebController::class, 'uploadCategoryImage'])->middleware(['permission:admin.categories.media.upload', 'throttle:media-upload'])->name('admin.master-data.categories.image.upload');
    Route::delete('/master-data/categories/{category}/image', [AdminMasterDataWebController::class, 'deleteCategoryImage'])->middleware('permission:admin.categories.media.delete')->name('admin.master-data.categories.image.delete');
    Route::get('/master-data/exchange-rates', [AdminMasterDataWebController::class, 'exchangeRates'])->middleware('permission:admin.exchange_rates.view')->name('admin.master-data.exchange-rates');
    Route::post('/master-data/exchange-rates', [AdminMasterDataWebController::class, 'storeExchangeRate'])->middleware('permission:admin.exchange_rates.create')->name('admin.master-data.exchange-rates.store');
    Route::get('/master-data/exchange-rates/{exchangeRate}/edit', [AdminMasterDataWebController::class, 'editExchangeRate'])->middleware('permission:admin.exchange_rates.update')->name('admin.master-data.exchange-rates.edit');
    Route::put('/master-data/exchange-rates/{exchangeRate}', [AdminMasterDataWebController::class, 'updateExchangeRate'])->middleware('permission:admin.exchange_rates.update')->name('admin.master-data.exchange-rates.update');
    Route::prefix('master-data/governorate-delivery-methods')->name('admin.master-data.governorate-delivery-methods.')->group(function (): void {
        Route::get('/', [AdminGovernorateDeliveryMethodWebController::class, 'index'])->middleware('permission:admin.locations.view')->name('index');
        Route::get('/create', [AdminGovernorateDeliveryMethodWebController::class, 'create'])->middleware('permission:admin.locations.create')->name('create');
        Route::post('/', [AdminGovernorateDeliveryMethodWebController::class, 'store'])->middleware('permission:admin.locations.create')->name('store');
        Route::get('/{assignment}/edit', [AdminGovernorateDeliveryMethodWebController::class, 'edit'])->middleware('permission:admin.locations.update')->name('edit');
        Route::put('/{assignment}', [AdminGovernorateDeliveryMethodWebController::class, 'update'])->middleware('permission:admin.locations.update')->name('update');
        Route::patch('/{assignment}/activate', [AdminGovernorateDeliveryMethodWebController::class, 'activate'])->middleware('permission:admin.locations.activate')->name('activate');
        Route::patch('/{assignment}/deactivate', [AdminGovernorateDeliveryMethodWebController::class, 'deactivate'])->middleware('permission:admin.locations.deactivate')->name('deactivate');
    });

    Route::prefix('master-data/customer-payment-methods')->name('admin.master-data.customer-payment-methods.')->group(function (): void {
        Route::get('/', [AdminCustomerPaymentMethodWebController::class, 'index'])->middleware('permission:admin.customer_payment_methods.view')->name('index');
        Route::get('/create', [AdminCustomerPaymentMethodWebController::class, 'create'])->middleware('permission:admin.customer_payment_methods.create')->name('create');
        Route::post('/', [AdminCustomerPaymentMethodWebController::class, 'store'])->middleware('permission:admin.customer_payment_methods.create')->name('store');
        Route::get('/{method}/edit', [AdminCustomerPaymentMethodWebController::class, 'edit'])->middleware('permission:admin.customer_payment_methods.update')->name('edit');
        Route::put('/{method}', [AdminCustomerPaymentMethodWebController::class, 'update'])->middleware('permission:admin.customer_payment_methods.update')->name('update');
        Route::patch('/{method}/activate', [AdminCustomerPaymentMethodWebController::class, 'activate'])->middleware('permission:admin.customer_payment_methods.activate')->name('activate');
        Route::patch('/{method}/deactivate', [AdminCustomerPaymentMethodWebController::class, 'deactivate'])->middleware('permission:admin.customer_payment_methods.deactivate')->name('deactivate');
        Route::post('/{method}/logo', [AdminCustomerPaymentMethodWebController::class, 'uploadLogo'])->middleware(['permission:admin.customer_payment_methods.media.upload', 'throttle:media-upload'])->name('logo.upload');
        Route::delete('/{method}/logo', [AdminCustomerPaymentMethodWebController::class, 'deleteLogo'])->middleware('permission:admin.customer_payment_methods.media.delete')->name('logo.delete');
    });

    Route::get('/master-data/settings', [AdminMasterDataWebController::class, 'settings'])->middleware('permission:admin.settings.view')->name('admin.master-data.settings');
    Route::put('/master-data/settings', [AdminMasterDataWebController::class, 'updateSettings'])->middleware('permission:admin.settings.update')->name('admin.master-data.settings.update');

    // Sprint 18 Management Reports
    Route::prefix('reports')->name('admin.reports.')->group(function (): void {
        Route::get('/sales', [AdminCommerceReportWebController::class, 'sales'])->middleware('permission:admin.reports.sales.view')->name('sales');
        Route::get('/sales/export', [AdminCommerceReportWebController::class, 'salesExport'])->middleware(['permission:admin.reports.sales.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('sales.export');
        Route::get('/returns', [AdminCommerceReportWebController::class, 'returns'])->middleware('permission:admin.reports.returns.view')->name('returns');
        Route::get('/returns/export', [AdminCommerceReportWebController::class, 'returnsExport'])->middleware(['permission:admin.reports.returns.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('returns.export');
        Route::get('/marketing', [AdminCommerceReportWebController::class, 'marketing'])->middleware('permission:admin.reports.marketing.view')->name('marketing');
        Route::get('/purchasing', [AdminCommerceReportWebController::class, 'purchasing'])->middleware('permission:admin.reports.purchasing.view')->name('purchasing');
        Route::get('/purchasing/export', [AdminCommerceReportWebController::class, 'purchasingExport'])->middleware(['permission:admin.reports.purchasing.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('purchasing.export');

        Route::get('/inventory', [AdminOperationsReportWebController::class, 'inventory'])->middleware('permission:admin.reports.inventory.view')->name('inventory');
        Route::get('/inventory/export', [AdminOperationsReportWebController::class, 'inventoryExport'])->middleware(['permission:admin.reports.inventory.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('inventory.export');
        Route::get('/delivery', [AdminOperationsReportWebController::class, 'delivery'])->middleware('permission:admin.reports.delivery.view')->name('delivery');
        Route::get('/driver-cod', [AdminOperationsReportWebController::class, 'driverCod'])->middleware('permission:admin.reports.driver_cod.view')->name('driver-cod');
        Route::get('/driver-cod/export', [AdminOperationsReportWebController::class, 'driverCodExport'])->middleware(['permission:admin.reports.driver_cod.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('driver-cod.export');

        Route::get('/customers', [AdminCustomerReportWebController::class, 'customers'])->middleware('permission:admin.reports.customers.view')->name('customers');
        Route::get('/customers/export', [AdminCustomerReportWebController::class, 'customersExport'])->middleware(['permission:admin.reports.customers.view', 'permission:admin.reports.export', 'throttle:reports-export'])->name('customers.export');
        Route::get('/loyalty', [AdminCustomerReportWebController::class, 'loyalty'])->middleware('permission:admin.reports.loyalty.view')->name('loyalty');
        Route::get('/customer-wallets', [AdminCustomerReportWebController::class, 'wallets'])->middleware('permission:admin.reports.customer_wallets.view')->name('customer-wallets');
    });

    // Finance & General Ledger Routes
    Route::prefix('finance')->name('admin.finance.')->group(function (): void {
        // Chart of Accounts
        Route::get('/accounts', [AdminChartOfAccountsWebController::class, 'index'])->middleware('permission:admin.accounts.view')->name('accounts.index');
        Route::get('/accounts/create', [AdminChartOfAccountsWebController::class, 'create'])->middleware('permission:admin.accounts.create')->name('accounts.create');
        Route::post('/accounts', [AdminChartOfAccountsWebController::class, 'store'])->middleware('permission:admin.accounts.create')->name('accounts.store');
        Route::get('/accounts/{account}/edit', [AdminChartOfAccountsWebController::class, 'edit'])->middleware('permission:admin.accounts.update')->name('accounts.edit');
        Route::put('/accounts/{account}', [AdminChartOfAccountsWebController::class, 'update'])->middleware('permission:admin.accounts.update')->name('accounts.update');
        Route::patch('/accounts/{account}/toggle', [AdminChartOfAccountsWebController::class, 'toggle'])->middleware('permission:admin.accounts.toggle')->name('accounts.toggle');

        // Accounting Periods
        Route::get('/periods', [AdminAccountingPeriodWebController::class, 'index'])->middleware('permission:admin.accounting_periods.view')->name('periods.index');
        Route::post('/periods', [AdminAccountingPeriodWebController::class, 'store'])->middleware('permission:admin.accounting_periods.create')->name('periods.store');
        Route::patch('/periods/{period}/close', [AdminAccountingPeriodWebController::class, 'close'])->middleware('permission:admin.accounting_periods.close')->name('periods.close');

        // Journal Entries
        Route::get('/journal-entries', [AdminJournalEntryWebController::class, 'index'])->middleware('permission:admin.journal_entries.view')->name('journal-entries.index');
        Route::get('/journal-entries/create', [AdminJournalEntryWebController::class, 'create'])->middleware('permission:admin.journal_entries.create')->name('journal-entries.create');
        Route::post('/journal-entries', [AdminJournalEntryWebController::class, 'store'])->middleware('permission:admin.journal_entries.create')->name('journal-entries.store');
        Route::get('/journal-entries/{journalEntry}', [AdminJournalEntryWebController::class, 'show'])->middleware('permission:admin.journal_entries.view')->name('journal-entries.show');
        Route::get('/journal-entries/{journalEntry}/edit', [AdminJournalEntryWebController::class, 'edit'])->middleware('permission:admin.journal_entries.update')->name('journal-entries.edit');
        Route::put('/journal-entries/{journalEntry}', [AdminJournalEntryWebController::class, 'update'])->middleware('permission:admin.journal_entries.update')->name('journal-entries.update');
        Route::post('/journal-entries/{journalEntry}/post', [AdminJournalEntryWebController::class, 'post'])->middleware('permission:admin.journal_entries.post')->name('journal-entries.post');
        Route::post('/journal-entries/{journalEntry}/reverse', [AdminJournalEntryWebController::class, 'reverse'])->middleware('permission:admin.journal_entries.reverse')->name('journal-entries.reverse');

        // Financial Reports
        Route::get('/reports/trial-balance', [AdminFinancialReportsWebController::class, 'trialBalance'])->middleware('permission:admin.trial_balance.view')->name('reports.trial-balance');
        Route::get('/reports/general-ledger', [AdminFinancialReportsWebController::class, 'generalLedger'])->middleware('permission:admin.general_ledger.view')->name('reports.general-ledger');
        Route::get('/reports/supplier-reconciliation', [AdminFinancialReportsWebController::class, 'reconcileSupplierLedger'])->middleware('permission:admin.general_ledger.view')->name('reports.supplier-reconciliation');
        Route::get('/reports/integrity', [AdminFinancialReportsWebController::class, 'integrity'])->middleware('permission:admin.reports.finance.view')->name('reports.integrity');

        // Treasury Management
        Route::prefix('treasury')->name('treasury.')->group(function (): void {
            // Cashboxes
            Route::get('/cashboxes', [AdminCashboxWebController::class, 'index'])->middleware('permission:admin.cashboxes.view')->name('cashboxes.index');
            Route::get('/cashboxes/create', [AdminCashboxWebController::class, 'create'])->middleware('permission:admin.cashboxes.create')->name('cashboxes.create');
            Route::post('/cashboxes', [AdminCashboxWebController::class, 'store'])->middleware('permission:admin.cashboxes.create')->name('cashboxes.store');
            Route::get('/cashboxes/{cashbox}/edit', [AdminCashboxWebController::class, 'edit'])->middleware('permission:admin.cashboxes.update')->name('cashboxes.edit');
            Route::put('/cashboxes/{cashbox}', [AdminCashboxWebController::class, 'update'])->middleware('permission:admin.cashboxes.update')->name('cashboxes.update');
            Route::patch('/cashboxes/{cashbox}/toggle', [AdminCashboxWebController::class, 'toggle'])->middleware('permission:admin.cashboxes.toggle')->name('cashboxes.toggle');

            // Bank Accounts
            Route::get('/bank-accounts', [AdminBankAccountWebController::class, 'index'])->middleware('permission:admin.bank_accounts.view')->name('bank-accounts.index');
            Route::get('/bank-accounts/create', [AdminBankAccountWebController::class, 'create'])->middleware('permission:admin.bank_accounts.create')->name('bank-accounts.create');
            Route::post('/bank-accounts', [AdminBankAccountWebController::class, 'store'])->middleware('permission:admin.bank_accounts.create')->name('bank-accounts.store');
            Route::get('/bank-accounts/{bank_account}/edit', [AdminBankAccountWebController::class, 'edit'])->middleware('permission:admin.bank_accounts.update')->name('bank-accounts.edit');
            Route::put('/bank-accounts/{bank_account}', [AdminBankAccountWebController::class, 'update'])->middleware('permission:admin.bank_accounts.update')->name('bank-accounts.update');
            Route::patch('/bank-accounts/{bank_account}/toggle', [AdminBankAccountWebController::class, 'toggle'])->middleware('permission:admin.bank_accounts.toggle')->name('bank-accounts.toggle');

            // Electronic Wallets
            Route::get('/electronic-wallets', [AdminElectronicWalletWebController::class, 'index'])->middleware('permission:admin.electronic_wallets.view')->name('electronic-wallets.index');
            Route::get('/electronic-wallets/create', [AdminElectronicWalletWebController::class, 'create'])->middleware('permission:admin.electronic_wallets.create')->name('electronic-wallets.create');
            Route::post('/electronic-wallets', [AdminElectronicWalletWebController::class, 'store'])->middleware('permission:admin.electronic_wallets.create')->name('electronic-wallets.store');
            Route::get('/electronic-wallets/{electronic_wallet}/edit', [AdminElectronicWalletWebController::class, 'edit'])->middleware('permission:admin.electronic_wallets.update')->name('electronic-wallets.edit');
            Route::put('/electronic-wallets/{electronic_wallet}', [AdminElectronicWalletWebController::class, 'update'])->middleware('permission:admin.electronic_wallets.update')->name('electronic-wallets.update');
            Route::patch('/electronic-wallets/{electronic_wallet}/toggle', [AdminElectronicWalletWebController::class, 'toggle'])->middleware('permission:admin.electronic_wallets.toggle')->name('electronic-wallets.toggle');

            // Transactions
            Route::get('/transactions', [AdminTreasuryTransactionWebController::class, 'index'])->middleware('permission:admin.treasury_transactions.view')->name('transactions.index');
            Route::get('/transactions/create', [AdminTreasuryTransactionWebController::class, 'create'])->middleware('permission:admin.treasury_transactions.create')->name('transactions.create');
            Route::post('/transactions', [AdminTreasuryTransactionWebController::class, 'store'])->middleware('permission:admin.treasury_transactions.create')->name('transactions.store');
            Route::get('/transactions/{treasuryTransaction}', [AdminTreasuryTransactionWebController::class, 'show'])->middleware('permission:admin.treasury_transactions.view')->name('transactions.show');
            Route::get('/transactions/{treasuryTransaction}/edit', [AdminTreasuryTransactionWebController::class, 'edit'])->middleware('permission:admin.treasury_transactions.update')->name('transactions.edit');
            Route::put('/transactions/{treasuryTransaction}', [AdminTreasuryTransactionWebController::class, 'update'])->middleware('permission:admin.treasury_transactions.update')->name('transactions.update');
            Route::post('/transactions/{treasuryTransaction}/post', [AdminTreasuryTransactionWebController::class, 'post'])->middleware('permission:admin.treasury_transactions.post')->name('transactions.post');
            Route::post('/transactions/{treasuryTransaction}/reverse', [AdminTreasuryTransactionWebController::class, 'reverse'])->middleware('permission:admin.treasury_transactions.reverse')->name('transactions.reverse');

            // Transfers
            Route::get('/transfers/create', [AdminTreasuryTransferWebController::class, 'create'])->middleware('permission:admin.treasury_transfers.create')->name('transfers.create');
            Route::post('/transfers', [AdminTreasuryTransferWebController::class, 'store'])->middleware('permission:admin.treasury_transfers.create')->name('transfers.store');

            // Currency Accounts (Sprint 21.3)
            Route::post('/currency-accounts', [AdminTreasuryCurrencyAccountWebController::class, 'store'])
                ->middleware('permission:admin.cashboxes.update|admin.bank_accounts.update|admin.electronic_wallets.update')
                ->name('currency-accounts.store');
            Route::patch('/currency-accounts/{currencyAccount}/toggle', [AdminTreasuryCurrencyAccountWebController::class, 'toggleStatus'])
                ->middleware('permission:admin.cashboxes.update|admin.bank_accounts.update|admin.electronic_wallets.update')
                ->name('currency-accounts.toggle');
            Route::get('/currency-accounts/{currencyAccount}/statement', [AdminTreasuryCurrencyAccountWebController::class, 'statement'])
                ->middleware('permission:admin.treasury_reports.ledger|admin.reports.treasury.view')
                ->name('currency-accounts.statement');

            // Reports
            Route::get('/reports/ledger', [AdminTreasuryReportsWebController::class, 'treasuryLedger'])
                ->middleware('permission:admin.treasury_reports.ledger|admin.reports.treasury.view')
                ->name('reports.ledger');
            Route::get('/reports/reconciliation', [AdminTreasuryReportsWebController::class, 'treasuryReconciliation'])
                ->middleware('permission:admin.treasury_reports.reconciliation|admin.reports.treasury.view')
                ->name('reports.reconciliation');
        });

        // Driver COD Financial Settlements (Sprint 15)
        Route::prefix('cod-settlements')->name('cod-settlements.')->group(function (): void {
            Route::get('/', [AdminDriverCodSettlementWebController::class, 'index'])->middleware('permission:admin.driver_cod_settlements.view')->name('index');
            Route::get('/create', [AdminDriverCodSettlementWebController::class, 'create'])->middleware('permission:admin.driver_cod_settlements.create')->name('create');
            Route::post('/', [AdminDriverCodSettlementWebController::class, 'store'])->middleware('permission:admin.driver_cod_settlements.create')->name('store');
            Route::get('/eligible-deliveries', [AdminDriverCodSettlementWebController::class, 'eligibleDeliveries'])->middleware('permission:admin.driver_cod_settlements.create')->name('eligible-deliveries');
            Route::get('/statements', [AdminDriverCodSettlementWebController::class, 'statements'])->middleware('permission:admin.driver_cod_statements.view')->name('statements');
            Route::get('/reconciliation', [AdminDriverCodSettlementWebController::class, 'reconciliation'])->middleware('permission:admin.driver_cod_reconciliation.view')->name('reconciliation');
            Route::get('/{driverCodSettlement}', [AdminDriverCodSettlementWebController::class, 'show'])->middleware('permission:admin.driver_cod_settlements.view')->name('show');
            Route::post('/{driverCodSettlement}/post', [AdminDriverCodSettlementWebController::class, 'post'])->middleware('permission:admin.driver_cod_settlements.post')->name('post');
            Route::post('/{driverCodSettlement}/reverse', [AdminDriverCodSettlementWebController::class, 'reverse'])->middleware('permission:admin.driver_cod_settlements.reverse')->name('reverse');
        });
    });

    // Marketing Routes (Sprint 16)
    Route::prefix('marketing')->name('admin.marketing.')->group(function (): void {
        // Promotions
        Route::get('/promotions', [AdminPromotionWebController::class, 'index'])->middleware('permission:admin.promotions.view')->name('promotions.index');
        Route::get('/promotions/create', [AdminPromotionWebController::class, 'create'])->middleware('permission:admin.promotions.create')->name('promotions.create');
        Route::post('/promotions', [AdminPromotionWebController::class, 'store'])->middleware('permission:admin.promotions.create')->name('promotions.store');
        Route::get('/promotions/{promotion}/edit', [AdminPromotionWebController::class, 'edit'])->middleware('permission:admin.promotions.update')->name('promotions.edit');
        Route::put('/promotions/{promotion}', [AdminPromotionWebController::class, 'update'])->middleware('permission:admin.promotions.update')->name('promotions.update');
        Route::patch('/promotions/{promotion}/toggle', [AdminPromotionWebController::class, 'toggle'])->middleware('permission:admin.promotions.activate|admin.promotions.deactivate')->name('promotions.toggle');

        // Coupons
        Route::get('/coupons', [AdminCouponWebController::class, 'index'])->middleware('permission:admin.coupons.view')->name('coupons.index');
        Route::get('/coupons/create', [AdminCouponWebController::class, 'create'])->middleware('permission:admin.coupons.create')->name('coupons.create');
        Route::post('/coupons', [AdminCouponWebController::class, 'store'])->middleware('permission:admin.coupons.create')->name('coupons.store');
        Route::get('/coupons/{coupon}/edit', [AdminCouponWebController::class, 'edit'])->middleware('permission:admin.coupons.update')->name('coupons.edit');
        Route::put('/coupons/{coupon}', [AdminCouponWebController::class, 'update'])->middleware('permission:admin.coupons.update')->name('coupons.update');
        Route::patch('/coupons/{coupon}/toggle', [AdminCouponWebController::class, 'toggle'])->middleware('permission:admin.coupons.activate|admin.coupons.deactivate')->name('coupons.toggle');
        Route::get('/coupons/{coupon}/usages', [AdminCouponWebController::class, 'usages'])->middleware('permission:admin.coupons.view')->name('coupons.usages');

        // Bundles
        Route::get('/bundles', [AdminBundleWebController::class, 'index'])->middleware('permission:admin.bundles.view')->name('bundles.index');
        Route::get('/bundles/create', [AdminBundleWebController::class, 'create'])->middleware('permission:admin.bundles.create')->name('bundles.create');
        Route::post('/bundles', [AdminBundleWebController::class, 'store'])->middleware('permission:admin.bundles.create')->name('bundles.store');
        Route::get('/bundles/{bundle}/edit', [AdminBundleWebController::class, 'edit'])->middleware('permission:admin.bundles.update')->name('bundles.edit');
        Route::put('/bundles/{bundle}', [AdminBundleWebController::class, 'update'])->middleware('permission:admin.bundles.update')->name('bundles.update');
        Route::patch('/bundles/{bundle}/toggle', [AdminBundleWebController::class, 'toggle'])->middleware('permission:admin.bundles.activate|admin.bundles.deactivate')->name('bundles.toggle');

        // Home Banners (Sprint 21.10)
        Route::get('/banners', [AdminHomeBannerWebController::class, 'index'])->middleware('permission:admin.home_banners.view')->name('banners.index');
        Route::get('/banners/create', [AdminHomeBannerWebController::class, 'create'])->middleware('permission:admin.home_banners.create')->name('banners.create');
        Route::post('/banners', [AdminHomeBannerWebController::class, 'store'])->middleware('permission:admin.home_banners.create')->name('banners.store');
        Route::get('/banners/{banner}', [AdminHomeBannerWebController::class, 'show'])->middleware('permission:admin.home_banners.view')->name('banners.show');
        Route::get('/banners/{banner}/edit', [AdminHomeBannerWebController::class, 'edit'])->middleware('permission:admin.home_banners.update')->name('banners.edit');
        Route::put('/banners/{banner}', [AdminHomeBannerWebController::class, 'update'])->middleware('permission:admin.home_banners.update')->name('banners.update');
        Route::delete('/banners/{banner}', [AdminHomeBannerWebController::class, 'destroy'])->middleware('permission:admin.home_banners.delete')->name('banners.destroy');
        Route::patch('/banners/{banner}/toggle', [AdminHomeBannerWebController::class, 'toggleStatus'])->middleware('permission:admin.home_banners.activate')->name('banners.toggle');

        // Server-Driven Home Sections
        Route::get('/home-sections', [AdminHomeSectionWebController::class, 'index'])->middleware('permission:admin.home_sections.view')->name('home-sections.index');
        Route::post('/home-sections', [AdminHomeSectionWebController::class, 'store'])->middleware('permission:admin.home_sections.create')->name('home-sections.store');
        Route::put('/home-sections/{homeSection}', [AdminHomeSectionWebController::class, 'update'])->middleware('permission:admin.home_sections.update')->name('home-sections.update');
        Route::patch('/home-sections/{homeSection}/toggle', [AdminHomeSectionWebController::class, 'toggle'])->middleware('permission:admin.home_sections.activate')->name('home-sections.toggle');
        Route::delete('/home-sections/{homeSection}', [AdminHomeSectionWebController::class, 'destroy'])->middleware('permission:admin.home_sections.delete')->name('home-sections.destroy');

        // Product Collections
        Route::get('/collections', [AdminProductCollectionWebController::class, 'index'])->middleware('permission:admin.product_collections.view')->name('collections.index');
        Route::get('/collections/create', [AdminProductCollectionWebController::class, 'create'])->middleware('permission:admin.product_collections.create')->name('collections.create');
        Route::post('/collections', [AdminProductCollectionWebController::class, 'store'])->middleware('permission:admin.product_collections.create')->name('collections.store');
        Route::get('/collections/{collection}/edit', [AdminProductCollectionWebController::class, 'edit'])->middleware('permission:admin.product_collections.update')->name('collections.edit');
        Route::put('/collections/{collection}', [AdminProductCollectionWebController::class, 'update'])->middleware('permission:admin.product_collections.update')->name('collections.update');
        Route::delete('/collections/{collection}', [AdminProductCollectionWebController::class, 'destroy'])->middleware('permission:admin.product_collections.delete')->name('collections.destroy');
        Route::get('/collections/{collection}/products', [AdminProductCollectionWebController::class, 'products'])->middleware('permission:admin.product_collections.view')->name('collections.products');
        Route::post('/collections/{collection}/products', [AdminProductCollectionWebController::class, 'addProduct'])->middleware('permission:admin.product_collections.update')->name('collections.products.add');
        Route::delete('/collections/{collection}/products/{product}', [AdminProductCollectionWebController::class, 'removeProduct'])->middleware('permission:admin.product_collections.update')->name('collections.products.remove');
    });

    // Customer Loyalty Routes (Sprint 17)
    Route::prefix('loyalty')->name('admin.loyalty.')->group(function (): void {
        Route::get('/', [AdminLoyaltyWebController::class, 'index'])->middleware('permission:admin.loyalty.view')->name('index');
        Route::get('/{account}', [AdminLoyaltyWebController::class, 'show'])->middleware('permission:admin.loyalty.view|admin.loyalty.statement')->name('show');
        Route::post('/adjust', [AdminLoyaltyWebController::class, 'adjust'])->middleware('permission:admin.loyalty.adjust')->name('adjust');
    });

    // Customer Wallets Routes (Sprint 17)
    Route::prefix('customer-wallets')->name('admin.customer-wallets.')->group(function (): void {
        Route::get('/', [AdminCustomerWalletWebController::class, 'index'])->middleware('permission:admin.customer_wallets.view')->name('index');
        Route::get('/{account}', [AdminCustomerWalletWebController::class, 'show'])->middleware('permission:admin.customer_wallets.view|admin.customer_wallets.statement')->name('show');
        Route::post('/adjust', [AdminCustomerWalletWebController::class, 'adjust'])->middleware('permission:admin.customer_wallets.adjust')->name('adjust');
        Route::post('/refund', [AdminCustomerWalletWebController::class, 'refund'])->middleware('permission:admin.customer_wallets.refund')->name('refund');
        Route::post('/entries/{entry}/reverse', [AdminCustomerWalletWebController::class, 'reverse'])->middleware('permission:admin.customer_wallets.reverse')->name('reverse');
    });

    // Search Operations Routes (Sprint 21.2)
    Route::prefix('search')->name('admin.search.')->group(function (): void {
        Route::get('/', [AdminSearchOperationsWebController::class, 'index'])->middleware('permission:admin.search.view')->name('index');
        Route::post('/test-visual', [AdminSearchOperationsWebController::class, 'testVisual'])->middleware('permission:admin.search.view')->name('test_visual');
        Route::post('/reindex-text', [AdminSearchOperationsWebController::class, 'reindexText'])->middleware('permission:admin.search.manage')->name('reindex_text');
        Route::post('/reconcile-text', [AdminSearchOperationsWebController::class, 'reconcileText'])->middleware('permission:admin.search.manage')->name('reconcile_text');
        Route::post('/reindex-visual', [AdminSearchOperationsWebController::class, 'reindexVisual'])->middleware('permission:admin.search.manage')->name('reindex_visual');
        Route::post('/reconcile-visual', [AdminSearchOperationsWebController::class, 'reconcileVisual'])->middleware('permission:admin.search.manage')->name('reconcile_visual');
    });

    // Notification Operations Routes (Sprint 21.5)
    Route::prefix('notifications')->name('admin.notifications.')->group(function (): void {
        Route::get('/', [AdminNotificationWebController::class, 'index'])->middleware('permission:admin.notifications.view|admin.notifications.view_failures')->name('index');
        Route::get('/compose', [AdminNotificationWebController::class, 'compose'])->middleware('permission:admin.notifications.send_test|admin.notifications.manage')->name('compose');
        Route::post('/compose', [AdminNotificationWebController::class, 'storeCompose'])->middleware('permission:admin.notifications.send_test|admin.notifications.manage')->name('compose.store');
        Route::post('/test-send', [AdminNotificationWebController::class, 'sendTest'])->middleware('permission:admin.notifications.send_test|admin.notifications.manage')->name('send_test');
        Route::get('/{id}', [AdminNotificationWebController::class, 'show'])->middleware('permission:admin.notifications.view|admin.notifications.view_failures')->name('show')->whereNumber('id');
        Route::post('/{id}/retry', [AdminNotificationWebController::class, 'retry'])->middleware('permission:admin.notifications.retry|admin.notifications.manage')->name('retry')->whereNumber('id');
    });
});
