@php($user = auth()->user())
@php($user->getRoles())
@php($user->isAbleTo('admin.dashboard.view'))
@php($isUsersRoles = request()->routeIs('admin.users.web.*', 'admin.roles.web.*', 'admin.permissions.web.*'))
@php($isCatalog = request()->routeIs('admin.products.*', 'admin.attributes.*', 'admin.search.*', 'admin.reviews.*'))
@php($isCustomers = request()->routeIs('admin.customers.*', 'admin.customer-tiers.*', 'admin.loyalty.*', 'admin.customer-wallets.*', 'admin.wishlists.*', 'admin.otp-challenges.*'))
@php($isSales = request()->routeIs('admin.orders.*', 'admin.sales-returns.*'))
@php($isMarketing = request()->routeIs('admin.marketing.promotions.*', 'admin.marketing.coupons.*', 'admin.marketing.bundles.*', 'admin.marketing.banners.*', 'admin.marketing.home-sections.*', 'admin.marketing.collections.*'))
@php($isDelivery = request()->routeIs('admin.deliveries.*', 'admin.drivers.*', 'admin.sourcing.*', 'admin.purchase-orders.*'))
@php($isPurchasing = request()->routeIs('admin.suppliers.*', 'admin.purchases.*'))
@php($isInventory = request()->routeIs('admin.inventory.*', 'admin.purchase-receipts.*', 'admin.stock-transfers.*', 'admin.stock-adjustments.*', 'admin.stock-movements.*'))
@php($isTreasury = request()->routeIs('admin.finance.treasury.*'))
@php($isCod = request()->routeIs('admin.finance.cod-settlements.*'))
@php($isFinance = request()->routeIs('admin.finance.accounts.*', 'admin.finance.journal-entries.*', 'admin.finance.periods.*', 'admin.finance.reports.trial-balance', 'admin.finance.reports.general-ledger', 'admin.finance.reports.supplier-reconciliation'))
@php($isReports = request()->routeIs('admin.reports.*', 'admin.finance.reports.integrity'))
@php($isMasterLocations = request()->routeIs('admin.master-data.countries.*', 'admin.master-data.governorates.*', 'admin.master-data.delivery-methods.*', 'admin.master-data.governorate-delivery-methods.*', 'admin.master-data.customer-payment-methods.*'))
@php($isMasterData = request()->routeIs('admin.master-data.*'))
@php($isNotifications = request()->routeIs('admin.notifications.*'))
<aside class="app-menu navbar-menu" aria-label="التنقل الرئيسي">
    <div class="navbar-brand-box">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light" aria-label="الوفاء استور - الرئيسية">
            <span class="logo-sm"><span class="wafaa-mark">و</span></span>
            <span class="logo-lg"><span class="wafaa-brand"><b>الوفاء استور</b><small>لوحة الإدارة</small></span></span>
        </a>
    </div>
    <div id="scrollbar" data-simplebar>
        <div class="container-fluid">
            <ul class="navbar-nav" id="navbar-nav">
                @if($user->hasPermission('admin.dashboard.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="ri-dashboard-2-line"></i><span>الرئيسية</span>
                        </a>
                    </li>
                @endif

                @if($user->hasPermission('admin.users.view') || $user->hasPermission('admin.roles.view') || $user->hasPermission('admin.permissions.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarUsersRoles" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isUsersRoles ? 'true' : 'false' }}" aria-controls="sidebarUsersRoles">
                            <i class="ri-shield-user-line"></i><span>إدارة المستخدمين</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isUsersRoles ? 'show' : '' }}" id="sidebarUsersRoles">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.users.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.users.web.*') ? 'active' : '' }}" href="{{ route('admin.users.web.index') }}"><i class="ri-team-line"></i><span>المستخدمون</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.roles.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.roles.web.*') ? 'active' : '' }}" href="{{ route('admin.roles.web.index') }}"><i class="ri-user-settings-line"></i><span>الأدوار</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.permissions.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.permissions.web.*') ? 'active' : '' }}" href="{{ route('admin.permissions.web.index') }}"><i class="ri-key-2-line"></i><span>الصلاحيات</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.products.view') || $user->hasPermission('admin.attributes.view') || $user->hasPermission('admin.search.view') || $user->hasPermission('admin.reviews.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarCatalog" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isCatalog ? 'true' : 'false' }}" aria-controls="sidebarCatalog">
                            <i class="ri-store-2-line"></i><span>المنتجات</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isCatalog ? 'show' : '' }}" id="sidebarCatalog">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.products.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="ri-shopping-bag-3-line"></i><span>المنتجات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.products.update'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.cost-prices.*') ? 'active' : '' }}" href="{{ route('admin.cost-prices.index') }}"><i class="ri-money-dollar-circle-line"></i><span>أسعار الشراء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reviews.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}"><i class="ri-star-line"></i><span>التقييمات والمراجعات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.attributes.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.attributes.*') ? 'active' : '' }}" href="{{ route('admin.attributes.index') }}"><i class="ri-sound-module-line"></i><span>الخصائص</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.search.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.search.*') ? 'active' : '' }}" href="{{ route('admin.search.index') }}"><i class="ri-search-eye-line"></i><span>عمليات ومحرك البحث</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.customers.view') || $user->hasPermission('admin.customer_tiers.view') || $user->hasPermission('admin.loyalty.view') || $user->hasPermission('admin.customer_wallets.view') || $user->hasPermission('admin.wishlists.view') || $user->hasPermission('admin.otp.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarCustomers" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isCustomers ? 'true' : 'false' }}" aria-controls="sidebarCustomers">
                            <i class="ri-user-heart-line"></i><span>العملاء</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isCustomers ? 'show' : '' }}" id="sidebarCustomers">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.customers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}"><i class="ri-group-line"></i><span>العملاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.customer_tiers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.customer-tiers.*') ? 'active' : '' }}" href="{{ route('admin.customer-tiers.index') }}"><i class="ri-vip-crown-line"></i><span>مستويات وتصنيفات العملاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.wishlists.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.wishlists.*') ? 'active' : '' }}" href="{{ route('admin.wishlists.index') }}"><i class="ri-heart-line"></i><span>المفضلة</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.loyalty.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.loyalty.*') ? 'active' : '' }}" href="{{ route('admin.loyalty.index') }}"><i class="ri-copper-coin-line"></i><span>نقاط الولاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.customer_wallets.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.customer-wallets.*') ? 'active' : '' }}" href="{{ route('admin.customer-wallets.index') }}"><i class="ri-wallet-3-line"></i><span>محافظ العملاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.otp.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.otp-challenges.*') ? 'active' : '' }}" href="{{ route('admin.otp-challenges.index') }}"><i class="ri-shield-keyhole-line"></i><span>سجلات رموز التحقق (OTP)</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.orders.view') || $user->hasPermission('admin.sales_returns.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarSales" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isSales ? 'true' : 'false' }}" aria-controls="sidebarSales">
                            <i class="ri-shopping-cart-2-line"></i><span>الطلبات</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isSales ? 'show' : '' }}" id="sidebarSales">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.orders.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="ri-shopping-cart-line"></i><span>الطلبات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.sales_returns.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.sales-returns.*') ? 'active' : '' }}" href="{{ route('admin.sales-returns.index') }}"><i class="ri-refund-2-line"></i><span>مرتجعات</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @php($marketingVisible = $user->hasPermission('admin.promotions.view') || $user->hasPermission('admin.coupons.view') || $user->hasPermission('admin.bundles.view') || $user->hasPermission('admin.home_banners.view') || $user->hasPermission('admin.home_sections.view') || $user->hasPermission('admin.product_collections.view'))
                @if($marketingVisible)
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarMarketing" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isMarketing ? 'true' : 'false' }}" aria-controls="sidebarMarketing">
                            <i class="ri-megaphone-line"></i><span>التسويق والعروض</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isMarketing ? 'show' : '' }}" id="sidebarMarketing">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.home_banners.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.banners.*') ? 'active' : '' }}" href="{{ route('admin.marketing.banners.index') }}"><i class="ri-image-line"></i><span>بنرات الصفحة الرئيسية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.home_sections.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.home-sections.*') ? 'active' : '' }}" href="{{ route('admin.marketing.home-sections.index') }}"><i class="ri-layout-grid-line"></i><span>أقسام الصفحة الرئيسية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.product_collections.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.collections.*') ? 'active' : '' }}" href="{{ route('admin.marketing.collections.index') }}"><i class="ri-stack-line"></i><span>مجموعات المنتجات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.promotions.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.promotions.*') ? 'active' : '' }}" href="{{ route('admin.marketing.promotions.index') }}"><i class="ri-price-tag-3-line"></i><span>العروض الترويجية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.coupons.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.coupons.*') ? 'active' : '' }}" href="{{ route('admin.marketing.coupons.index') }}"><i class="ri-coupon-3-line"></i><span>كوبونات الخصم</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.bundles.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.marketing.bundles.*') ? 'active' : '' }}" href="{{ route('admin.marketing.bundles.index') }}"><i class="ri-gift-line"></i><span>باقات المنتجات</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.deliveries.view') || $user->hasPermission('admin.drivers.view') || $user->hasPermission('admin.sourcing.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarDelivery" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isDelivery ? 'true' : 'false' }}" aria-controls="sidebarDelivery">
                            <i class="ri-e-bike-2-line"></i><span>التوصيل والعمليات</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isDelivery ? 'show' : '' }}" id="sidebarDelivery">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.sourcing.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index') }}"><i class="ri-file-list-3-line"></i><span>طلبات الشراء (حسب المورد)</span></a></li>
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.sourcing.*') ? 'active' : '' }}" href="{{ route('admin.sourcing.index') }}"><i class="ri-shopping-basket-line"></i><span>طابور التوريد (بنود)</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.deliveries.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.deliveries.*') ? 'active' : '' }}" href="{{ route('admin.deliveries.index') }}"><i class="ri-road-map-line"></i><span>عمليات التوصيل</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.drivers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.drivers.*') ? 'active' : '' }}" href="{{ route('admin.drivers.index') }}"><i class="ri-user-star-line"></i><span>مندوبو التوصيل</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.notifications.view') || $user->hasPermission('admin.notifications.view_failures'))
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ $isNotifications ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">
                            <i class="ri-notification-3-line"></i><span>عمليات الإشعارات (FCM)</span>
                        </a>
                    </li>
                @endif

                @if($user->hasPermission('admin.support_chat.view'))
                    @php($supportChatUnreadCount = $supportChatUnreadCount ?? app(\App\Services\SupportChat\SupportChatService::class)->unreadCountForAdmin($user))
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ request()->routeIs('admin.support-chat.*') ? 'active' : '' }}" href="{{ route('admin.support-chat.index') }}">
                            <i class="ri-customer-service-2-line"></i>
                            <span>محادثات الدعم المباشر</span>
                            <span
                                class="badge badge-pill bg-danger ms-auto{{ ($supportChatUnreadCount ?? 0) > 0 ? '' : ' d-none' }}"
                                data-support-unread-badge
                            >{{ $supportChatUnreadCount }}</span>
                        </a>
                    </li>
                @endif

                @if($user->hasPermission('admin.suppliers.view') || $user->hasPermission('admin.purchases.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarPurchasing" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isPurchasing ? 'true' : 'false' }}" aria-controls="sidebarPurchasing">
                            <i class="ri-shopping-basket-2-line"></i><span>المشتريات والموردين</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isPurchasing ? 'show' : '' }}" id="sidebarPurchasing">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.suppliers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.suppliers.index') }}"><i class="ri-truck-line"></i><span>الموردون</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.purchases.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.purchases.*') ? 'active' : '' }}" href="{{ route('admin.purchases.index') }}"><i class="ri-file-list-3-line"></i><span>فواتير الشراء</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.inventory.view') || $user->hasPermission('admin.purchase_receipts.view') || $user->hasPermission('admin.stock_transfers.view') || $user->hasPermission('admin.stock_adjustments.view') || $user->hasPermission('admin.stock_movements.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarInventory" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isInventory ? 'true' : 'false' }}" aria-controls="sidebarInventory">
                            <i class="ri-archive-2-line"></i><span>المخزون والمستودعات</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isInventory ? 'show' : '' }}" id="sidebarInventory">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.inventory.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}"><i class="ri-archive-line"></i><span>أرصدة المخزون</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.purchase_receipts.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.purchase-receipts.*') ? 'active' : '' }}" href="{{ route('admin.purchase-receipts.index') }}"><i class="ri-inbox-archive-line"></i><span>استلام المشتريات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.stock_transfers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.stock-transfers.*') ? 'active' : '' }}" href="{{ route('admin.stock-transfers.index') }}"><i class="ri-arrow-left-right-line"></i><span>تحويلات المخزون</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.stock_adjustments.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.stock-adjustments.*') ? 'active' : '' }}" href="{{ route('admin.stock-adjustments.index') }}"><i class="ri-equalizer-line"></i><span>التسويات الجردية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.stock_movements.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.stock-movements.*') ? 'active' : '' }}" href="{{ route('admin.stock-movements.index') }}"><i class="ri-history-line"></i><span>حركات المخزون</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @php($treasuryVisible = $user->hasPermission('admin.cashboxes.view') || $user->hasPermission('admin.bank_accounts.view') || $user->hasPermission('admin.electronic_wallets.view') || $user->hasPermission('admin.treasury_transactions.view') || $user->hasPermission('admin.treasury_transfers.create') || $user->hasPermission('admin.treasury_reports.ledger') || $user->hasPermission('admin.treasury_reports.reconciliation') || $user->hasPermission('admin.reports.treasury.view'))
                @if($treasuryVisible)
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarTreasury" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isTreasury ? 'true' : 'false' }}" aria-controls="sidebarTreasury">
                            <i class="ri-bank-line"></i><span>إدارة الخزينة والنقدية</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isTreasury ? 'show' : '' }}" id="sidebarTreasury">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.cashboxes.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.cashboxes.*') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.cashboxes.index') }}"><i class="ri-safe-2-line"></i><span>صناديق الكاش</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.bank_accounts.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.bank-accounts.*') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.bank-accounts.index') }}"><i class="ri-bank-card-line"></i><span>الحسابات البنكية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.electronic_wallets.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.electronic-wallets.*') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.electronic-wallets.index') }}"><i class="ri-wallet-3-line"></i><span>المحافظ الإلكترونية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.treasury_transactions.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.transactions.*') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.transactions.index') }}"><i class="ri-exchange-box-line"></i><span>حركات وسندات الخزينة</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.treasury_transfers.create') || $user->hasPermission('admin.treasury_transactions.create'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.transfers.*') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.transfers.create') }}"><i class="ri-arrow-left-right-line"></i><span>تحويل مالي بين الحسابات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.treasury_reports.ledger') || $user->hasPermission('admin.reports.treasury.view') || $user->hasPermission('admin.treasury_transactions.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.reports.ledger') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.reports.ledger') }}"><i class="ri-file-chart-line"></i><span>كشف حساب الخزينة</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.treasury_reports.reconciliation') || $user->hasPermission('admin.reports.treasury.view') || $user->hasPermission('admin.treasury_transactions.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.treasury.reports.reconciliation') ? 'active' : '' }}" href="{{ route('admin.finance.treasury.reports.reconciliation') }}"><i class="ri-scales-line"></i><span>مطابقة الأرصدة (GL)</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.driver_cod_settlements.view') || $user->hasPermission('admin.driver_cod_statements.view') || $user->hasPermission('admin.driver_cod_reconciliation.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarCod" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isCod ? 'true' : 'false' }}" aria-controls="sidebarCod">
                            <i class="ri-wallet-2-line"></i><span>تسويات عهد المناديب</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isCod ? 'show' : '' }}" id="sidebarCod">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.driver_cod_settlements.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.cod-settlements.index', 'admin.finance.cod-settlements.show') ? 'active' : '' }}" href="{{ route('admin.finance.cod-settlements.index') }}"><i class="ri-hand-coin-line"></i><span>تسويات عهد المناديب</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.driver_cod_settlements.create'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.cod-settlements.create') ? 'active' : '' }}" href="{{ route('admin.finance.cod-settlements.create') }}"><i class="ri-add-circle-line"></i><span>إنشاء تسوية عهدة جديدة</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.driver_cod_statements.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.cod-settlements.statements') ? 'active' : '' }}" href="{{ route('admin.finance.cod-settlements.statements') }}"><i class="ri-file-user-line"></i><span>كشف عهدة المندوب</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.driver_cod_reconciliation.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.cod-settlements.reconciliation') ? 'active' : '' }}" href="{{ route('admin.finance.cod-settlements.reconciliation') }}"><i class="ri-shield-check-line"></i><span>مطابقة عهد وتحصيلات COD</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($user->hasPermission('admin.accounts.view') || $user->hasPermission('admin.journal_entries.view') || $user->hasPermission('admin.accounting_periods.view') || $user->hasPermission('admin.trial_balance.view') || $user->hasPermission('admin.general_ledger.view'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarFinance" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isFinance ? 'true' : 'false' }}" aria-controls="sidebarFinance">
                            <i class="ri-calculator-line"></i><span>المالية والمحاسبة</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isFinance ? 'show' : '' }}" id="sidebarFinance">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.accounts.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.accounts.*') ? 'active' : '' }}" href="{{ route('admin.finance.accounts.index') }}"><i class="ri-book-3-line"></i><span>دليل الحسابات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.journal_entries.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.journal-entries.*') ? 'active' : '' }}" href="{{ route('admin.finance.journal-entries.index') }}"><i class="ri-file-edit-line"></i><span>القيود اليومية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.accounting_periods.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.periods.*') ? 'active' : '' }}" href="{{ route('admin.finance.periods.index') }}"><i class="ri-calendar-event-line"></i><span>الفترات المحاسبية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.trial_balance.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.reports.trial-balance') ? 'active' : '' }}" href="{{ route('admin.finance.reports.trial-balance') }}"><i class="ri-scales-3-line"></i><span>ميزان المراجعة</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.general_ledger.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.reports.general-ledger', 'admin.finance.reports.supplier-reconciliation') ? 'active' : '' }}" href="{{ route('admin.finance.reports.general-ledger') }}"><i class="ri-file-list-line"></i><span>الأستاذ العام</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @php($managementReportsVisible = collect(['sales','purchasing','inventory','returns','delivery','driver_cod','finance','treasury','customers','marketing','loyalty','customer_wallets'])->contains(fn($permission) => $user->hasPermission("admin.reports.$permission.view")))
                @if($managementReportsVisible)
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarReports" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isReports ? 'true' : 'false' }}" aria-controls="sidebarReports">
                            <i class="ri-bar-chart-box-line"></i><span>التقارير</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isReports ? 'show' : '' }}" id="sidebarReports">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.reports.sales.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.sales') ? 'active' : '' }}" href="{{ route('admin.reports.sales') }}"><i class="ri-line-chart-line"></i><span>المبيعات والطلبات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.purchasing.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.purchasing') ? 'active' : '' }}" href="{{ route('admin.reports.purchasing') }}"><i class="ri-truck-line"></i><span>المشتريات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.inventory.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}" href="{{ route('admin.reports.inventory') }}"><i class="ri-stack-line"></i><span>المخزون</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.returns.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.returns') ? 'active' : '' }}" href="{{ route('admin.reports.returns') }}"><i class="ri-arrow-go-back-line"></i><span>مرتجعات المبيعات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.delivery.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.delivery') ? 'active' : '' }}" href="{{ route('admin.reports.delivery') }}"><i class="ri-e-bike-2-line"></i><span>التوصيل</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.driver_cod.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.driver-cod') ? 'active' : '' }}" href="{{ route('admin.reports.driver-cod') }}"><i class="ri-wallet-3-line"></i><span>عهدة مندوبي التوصيل</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.finance.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.finance.reports.integrity') ? 'active' : '' }}" href="{{ route('admin.finance.reports.integrity') }}"><i class="ri-shield-check-line"></i><span>السلامة المالية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.customers.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.customers') ? 'active' : '' }}" href="{{ route('admin.reports.customers') }}"><i class="ri-group-line"></i><span>العملاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.marketing.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.marketing') ? 'active' : '' }}" href="{{ route('admin.reports.marketing') }}"><i class="ri-price-tag-2-line"></i><span>التسويق</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.loyalty.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.loyalty') ? 'active' : '' }}" href="{{ route('admin.reports.loyalty') }}"><i class="ri-medal-line"></i><span>نقاط الولاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.reports.customer_wallets.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.reports.customer-wallets') ? 'active' : '' }}" href="{{ route('admin.reports.customer-wallets') }}"><i class="ri-bank-card-line"></i><span>محافظ العملاء</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @php($masterVisible = collect(['currencies','exchange_rates','units','locations','brands','categories','warehouses','customer_payment_methods','settings'])->contains(fn($permission) => $user->hasPermission("admin.$permission.view")))
                @if($masterVisible)
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarMasterData" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isMasterData ? 'true' : 'false' }}" aria-controls="sidebarMasterData">
                            <i class="ri-database-2-line"></i><span>البيانات الأساسية</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isMasterData ? 'show' : '' }}" id="sidebarMasterData">
                            <ul class="nav nav-sm flex-column">
                                @if($user->hasPermission('admin.currencies.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.currencies.*') ? 'active' : '' }}" href="{{ route('admin.master-data.currencies.index') }}"><i class="ri-money-dollar-circle-line"></i><span>العملات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.exchange_rates.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.exchange-rates*') ? 'active' : '' }}" href="{{ route('admin.master-data.exchange-rates') }}"><i class="ri-exchange-dollar-line"></i><span>أسعار الصرف</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.units.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.units.*') ? 'active' : '' }}" href="{{ route('admin.master-data.units.index') }}"><i class="ri-ruler-line"></i><span>الوحدات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.locations.view'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="#sidebarLocations" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isMasterLocations ? 'true' : 'false' }}" aria-controls="sidebarLocations">
                                            <i class="ri-map-pin-2-line"></i><span>المواقع</span>
                                        </a>
                                        <div class="collapse menu-dropdown {{ $isMasterLocations ? 'show' : '' }}" id="sidebarLocations">
                                            <ul class="nav nav-sm flex-column">
                                                <li class="nav-item"><a class="nav-link" href="{{ route('admin.master-data.countries.index') }}">الدول</a></li>
                                                <li class="nav-item"><a class="nav-link" href="{{ route('admin.master-data.governorates.index') }}">المحافظات</a></li>
                                                {{-- Cities/districts retired from active admin nav (legacy tables remain) --}}
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.delivery-methods.*') ? 'active' : '' }}" href="{{ route('admin.master-data.delivery-methods.index') }}"><i class="ri-truck-line"></i><span>طرق التوصيل</span></a></li>
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.governorate-delivery-methods.*') ? 'active' : '' }}" href="{{ route('admin.master-data.governorate-delivery-methods.index') }}"><i class="ri-links-line"></i><span>ربط طرق التوصيل بالمحافظات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.customer_payment_methods.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.customer-payment-methods.*') ? 'active' : '' }}" href="{{ route('admin.master-data.customer-payment-methods.index') }}"><i class="ri-bank-card-line"></i><span>طرق دفع العملاء</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.brands.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.brands.*') ? 'active' : '' }}" href="{{ route('admin.master-data.brands.index') }}"><i class="ri-price-tag-3-line"></i><span>العلامات التجارية</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.categories.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.categories.*') ? 'active' : '' }}" href="{{ route('admin.master-data.categories.index') }}"><i class="ri-node-tree"></i><span>التصنيفات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.warehouses.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.warehouses.*') ? 'active' : '' }}" href="{{ route('admin.master-data.warehouses.index') }}"><i class="ri-building-4-line"></i><span>المستودعات</span></a></li>
                                @endif
                                @if($user->hasPermission('admin.settings.view'))
                                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master-data.settings*') ? 'active' : '' }}" href="{{ route('admin.master-data.settings') }}"><i class="ri-settings-3-line"></i><span>الإعدادات</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif
            </ul>
        </div>
    </div>
    <div class="sidebar-background"></div>
</aside>
<div class="vertical-overlay" tabindex="-1" aria-hidden="true"></div>
