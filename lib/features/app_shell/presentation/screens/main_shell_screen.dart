import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/navigation/full_screen_swipe_back.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/app_bottom_nav_bar.dart';
import 'package:store/core/widgets/custom_app_bar.dart';
import 'package:store/core/widgets/offline_banner.dart';
import 'package:store/core/services/deep_link_service.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/account/presentation/screens/account_screen.dart';
import 'package:store/features/account/presentation/widgets/auth_modal_sheet.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/support_chat/cubit/support_chat_badge_cubit.dart';
import 'package:store/features/support_chat/presentation/screens/support_chat_inbox_screen.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/cart/presentation/screens/cart_screen.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/screens/catalog_screen.dart';
import 'package:store/features/checkout/presentation/screens/checkout_screen.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/currency/presentation/widgets/currency_selector_sheet.dart';
import 'package:store/features/home/presentation/screens/home_screen.dart';
import 'package:store/features/onboarding/presentation/guest_onboarding_sheet.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';
import 'package:store/features/orders/presentation/screens/order_tracking_screen.dart';
import 'package:store/features/product_details/presentation/screens/product_details_screen.dart';
import 'package:store/features/visual_search/presentation/screens/visual_search_dialog.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';
import 'package:store/features/wishlist/presentation/screens/wishlist_screen.dart';

class MainShellScreen extends StatefulWidget {
  const MainShellScreen({super.key});

  @override
  State<MainShellScreen> createState() => _MainShellScreenState();
}

class _MainShellScreenState extends State<MainShellScreen> {
  DateTime? _lastBackPressTime;

  @override
  void initState() {
    super.initState();
    // Cold start can miss the first currencies request; ensure on first frame.
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      context.read<CurrencyCubit>().ensureLoaded();
      DeepLinkService.instance.flushPendingTarget();
      final loggedIn = context.read<AccountCubit>().state.isLoggedIn;
      if (!loggedIn) {
        await GuestOnboardingSheet.showIfNeeded(context);
      }
    });
  }

  /// Switching to home clears catalog filters so chips/drawer state
  /// does not stick when the user returns to the products tab.
  /// Skip reload here — category/brand taps from home apply their own load
  /// without flashing the unfiltered "all products" list first.
  void _selectTab(BuildContext context, int index) {
    if (index == 0) {
      context.read<CatalogCubit>().resetFilters(reload: false);
    } else if (index == 1) {
      final catalog = context.read<CatalogCubit>();
      // Home clears filters without loading; open catalog empty → fetch all.
      if (catalog.state.allProducts.isEmpty && !catalog.state.isLoading) {
        catalog.loadProducts(refresh: true);
      }
    } else if (index == 4) {
      final account = context.read<AccountCubit>();
      if (account.state.isLoggedIn) {
        context.read<OrdersCubit>().ensureOrdersLoaded();
      }
    }
    context.read<AppShellCubit>().setTab(index);
  }

  void _handleBackPress(BuildContext context, AppShellState state) {
    // 1. If product details screen is open, close it first
    if (state.isProductDetailsOpen) {
      context.read<AppShellCubit>().closeProductDetails();
      return;
    }

    // 2. If checkout is open, close it first
    if (state.isCheckoutOpen) {
      context.read<AppShellCubit>().closeCheckout();
      return;
    }

    // 3. If order tracking is open, close it first
    if (state.isOrderTrackingOpen) {
      context.read<AppShellCubit>().closeOrderTracking();
      return;
    }

    // 4. If not on Home tab (tab 0), return to Home tab
    if (state.activeTabIndex != 0) {
      _selectTab(context, 0);
      return;
    }

    // 5. On Home tab: double press to exit
    final now = DateTime.now();
    if (_lastBackPressTime == null ||
        now.difference(_lastBackPressTime!) > const Duration(seconds: 2)) {
      _lastBackPressTime = now;
      context.showAppSnackBar(
        'اضغط مرة أخرى للخروج من التطبيق',
        type: AppSnackBarType.info,
      );
    } else {
      // Exit app cleanly
      SystemNavigator.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AccountCubit, AccountState>(
      listenWhen: (previous, current) =>
          previous.status != current.status && !current.isLoggedIn,
      listener: (context, authState) {
        final shell = context.read<AppShellCubit>();
        if (shell.state.isCheckoutOpen) shell.closeCheckout();
        if (shell.state.isOrderTrackingOpen) shell.closeOrderTracking();
      },
      child: BlocBuilder<AppShellCubit, AppShellState>(
        builder: (context, state) {
          final currencyState = context.watch<CurrencyCubit>().state;
          final cartCount = context.watch<CartCubit>().state.totalItemsCount;
          final wishlistCount =
              context.watch<WishlistCubit>().state.productIds.length;
          final supportUnread =
              context.watch<SupportChatBadgeCubit>().state.unreadCount;

          const screens = [
            HomeScreen(),
            CatalogScreen(),
            WishlistScreen(),
            CartScreen(),
            AccountScreen(),
          ];

          final overlayOpen = state.isProductDetailsOpen ||
              state.isCheckoutOpen ||
              state.isOrderTrackingOpen;

          // Always keep tabs mounted so catalog search text/results survive
          // when opening product details / checkout / order tracking.
          final isCatalogTab = state.activeTabIndex == 1;
          final isAccountTab = state.activeTabIndex == 4;

          final shell = Scaffold(
            extendBody: state.bottomNavStyle == BottomNavStyle.floating &&
                !overlayOpen,
            appBar: isAccountTab
                ? null
                : CustomAppBar(
              activeCurrencyCode: currencyState.selectedCurrency.code,
              activeCurrencySymbol: currencyState.selectedCurrency.symbol,
              cartCount: cartCount,
              wishlistCount: wishlistCount,
              supportUnreadCount: supportUnread,
              showSearch: false,
              showBrandBar: !isCatalogTab,
              onCurrencyTap: () => CurrencySelectorSheet.show(context),
              onCameraTap: () => VisualSearchDialog.show(context),
              onSupportTap: () async {
                final account = context.read<AccountCubit>();
                if (!account.state.isLoggedIn) {
                  final ok = await AuthModalSheet.show(context);
                  if (!context.mounted || ok != true) return;
                }
                if (!context.mounted) return;
                await SupportChatInboxScreen.open(
                  context,
                  preferDirectThread: true,
                );
              },
              onSearchTap: () {
                final catalog = context.read<CatalogCubit>();
                if (catalog.state.allProducts.isEmpty &&
                    !catalog.state.isLoading) {
                  catalog.loadProducts(refresh: true);
                }
                context.read<AppShellCubit>().setTab(1);
              },
              onWishlistTap: () => _selectTab(context, 2),
              onCartTap: () => _selectTab(context, 3),
              onLogoTap: () => _selectTab(context, 0),
            ),
            body: Column(
              children: [
                const OfflineBanner(),
                Expanded(
                  child: IndexedStack(
                    index: state.activeTabIndex,
                    children: screens,
                  ),
                ),
              ],
            ),
            bottomNavigationBar: overlayOpen
                ? null
                : AppBottomNavBar(
                    currentIndex: state.activeTabIndex,
                    onTap: (index) => _selectTab(context, index),
                    cartCount: cartCount,
                    wishlistCount: wishlistCount,
                    style: state.bottomNavStyle,
                  ),
          );

          return PopScope(
            canPop: false,
            onPopInvokedWithResult: (didPop, result) {
              if (didPop) return;
              _handleBackPress(context, state);
            },
            child: Stack(
              children: [
                shell,
                if (state.isProductDetailsOpen && state.selectedProduct != null)
                  Positioned.fill(
                    child: FullScreenSwipeBackContainer(
                      onDismiss: () =>
                          context.read<AppShellCubit>().closeProductDetails(),
                      child: ProductDetailsScreen(
                        product: state.selectedProduct!,
                      ),
                    ),
                  ),
                if (state.isCheckoutOpen)
                  Positioned.fill(
                    child: FullScreenSwipeBackContainer(
                      onDismiss: () =>
                          context.read<AppShellCubit>().closeCheckout(),
                      child: const CheckoutScreen(),
                    ),
                  ),
                if (state.isOrderTrackingOpen)
                  Positioned.fill(
                    child: FullScreenSwipeBackContainer(
                      onDismiss: () =>
                          context.read<AppShellCubit>().closeOrderTracking(),
                      child: const OrderTrackingScreen(),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}
