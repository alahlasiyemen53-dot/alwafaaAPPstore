import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'core/auth/customer_session_cleanup.dart';
import 'core/constants/app_strings.dart';
import 'core/network/connectivity_cubit.dart';
import 'core/network/dio_client.dart';
import 'core/notifications/firebase_push_notification_service.dart';
import 'core/notifications/push_notification_router.dart';
import 'core/services/deep_link_service.dart';
import 'core/support_chat/firebase_chat_session.dart';
import 'core/theme/app_theme.dart';
import 'data/datasources/cart_remote_datasource.dart';
import 'data/datasources/checkout_remote_datasource.dart';
import 'data/datasources/wishlist_remote_datasource.dart';
import 'data/datasources/device_remote_datasource.dart';
import 'data/repositories/cart_repository.dart';
import 'data/repositories/checkout_repository.dart';
import 'data/repositories/device_repository.dart';
import 'data/repositories/home_repository.dart';
import 'data/repositories/reference_repository.dart';
import 'data/repositories/wishlist_repository.dart';
import 'features/account/cubit/account_cubit.dart';
import 'features/account/cubit/address_cubit.dart';
import 'features/account/cubit/loyalty_cubit.dart';
import 'features/account/cubit/push_device_cubit.dart';
import 'features/account/cubit/support_cubit.dart';
import 'features/app_shell/cubit/app_shell_cubit.dart';
import 'features/brands/cubit/brands_cubit.dart';
import 'features/categories/cubit/categories_cubit.dart';
import 'features/cart/cubit/cart_cubit.dart';
import 'features/catalog/cubit/catalog_cubit.dart';
import 'features/checkout/cubit/checkout_cubit.dart';
import 'features/currency/cubit/currency_cubit.dart';
import 'features/discovery/cubit/collections_cubit.dart';
import 'features/discovery/cubit/notifications_cubit.dart';
import 'features/discovery/cubit/recently_viewed_cubit.dart';
import 'features/discovery/cubit/search_history_cubit.dart';
import 'features/home/cubit/home_cubit.dart';
import 'features/orders/cubit/orders_cubit.dart';
import 'features/splash/presentation/screens/splash_screen.dart';
import 'features/support_chat/cubit/support_chat_badge_cubit.dart';
import 'features/wishlist/cubit/wishlist_cubit.dart';

/// Absorbs OS-level [Navigator.pushNamed] calls (deep links / app links) so
/// Flutter does not null-assert on a missing [MaterialApp.onUnknownRoute].
Route<dynamic> _discardOsNamedRoute(RouteSettings settings) {
  return PageRouteBuilder<void>(
    settings: settings,
    opaque: false,
    barrierDismissible: false,
    pageBuilder: (context, animation, secondaryAnimation) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final navigator = Navigator.maybeOf(context);
        if (navigator != null && navigator.canPop()) {
          navigator.pop();
        }
      });
      return const SizedBox.shrink();
    },
    transitionDuration: Duration.zero,
    reverseTransitionDuration: Duration.zero,
  );
}

class StoreApp extends StatefulWidget {
  const StoreApp({
    super.key,
    required this.referenceRepository,
  });

  final ReferenceRepository referenceRepository;

  @override
  State<StoreApp> createState() => _StoreAppState();
}

class _StoreAppState extends State<StoreApp> {
  late final CustomerSessionCleanup _sessionCleanup;
  late final AccountCubit _accountCubit;
  late final DioClient _authorizedDio;
  late final CartCubit _cartCubit;
  late final WishlistCubit _wishlistCubit;
  late final OrdersCubit _ordersCubit;
  late final AddressCubit _addressCubit;
  late final LoyaltyCubit _loyaltyCubit;
  late final SupportCubit _supportCubit;
  late final PushDeviceCubit _pushDeviceCubit;
  late final CurrencyCubit _currencyCubit;
  late final CheckoutCubit _checkoutCubit;
  late final RecentlyViewedCubit _recentlyViewedCubit;
  late final SearchHistoryCubit _searchHistoryCubit;
  late final NotificationsCubit _notificationsCubit;
  late final CollectionsCubit _collectionsCubit;
  late final SupportChatBadgeCubit _supportChatBadgeCubit;
  StreamSubscription<AccountState>? _pushRegistrationSubscription;
  bool _suppressLoginSideEffects = false;

  @override
  void initState() {
    super.initState();
    _sessionCleanup = DefaultCustomerSessionCleanup();
    _accountCubit = AccountCubit(sessionCleanup: _sessionCleanup);
    _authorizedDio = DioClient(
      onUnauthorized: _accountCubit.handleSessionExpired,
    );
    _cartCubit = CartCubit(
      repository: CartRepositoryImpl(
        remoteDataSource: CartRemoteDataSourceImpl(dioClient: _authorizedDio),
      ),
    );
    _wishlistCubit = WishlistCubit(
      repository: WishlistRepositoryImpl(
        remoteDataSource:
            WishlistRemoteDataSourceImpl(dioClient: _authorizedDio),
      ),
    );
    _ordersCubit = OrdersCubit();
    _addressCubit = AddressCubit(
      referenceRepository: widget.referenceRepository,
    );
    _loyaltyCubit = LoyaltyCubit();
    _supportCubit = SupportCubit();
    _recentlyViewedCubit = RecentlyViewedCubit();
    _searchHistoryCubit = SearchHistoryCubit();
    _notificationsCubit = NotificationsCubit();
    _collectionsCubit = CollectionsCubit();
    _supportChatBadgeCubit = SupportChatBadgeCubit();
    final pushService = FirebasePushNotificationService();
    _pushDeviceCubit = PushDeviceCubit(
      notificationService: pushService,
      deviceRepository: DeviceRepositoryImpl(
        remoteDataSource: DeviceRemoteDataSourceImpl(dioClient: _authorizedDio),
      ),
    );
    _currencyCubit = CurrencyCubit(
      referenceRepository: widget.referenceRepository,
    )..ensureLoaded();
    _checkoutCubit = CheckoutCubit(
      checkoutRepository: CheckoutRepositoryImpl(
        remoteDataSource:
            CheckoutRemoteDataSourceImpl(dioClient: _authorizedDio),
      ),
    );
    unawaited(_bootstrapPushNotifications(pushService));
    unawaited(DeepLinkService.instance.initialize());

    // After OTP login (guest → authenticated), push the FCM token to the API and sync data.
    _pushRegistrationSubscription = _accountCubit.stream.listen((state) {
      if (state.isLoggedIn) {
        if (!_wasLoggedInForPush && !_suppressLoginSideEffects) {
          unawaited(_registerPushDeviceAfterLogin());
          unawaited(_synchronizeGuestData());
        }
        unawaited(_supportChatBadgeCubit.start());
      } else {
        if (_wasLoggedInForPush) {
          unawaited(_cartCubit.setAuthenticated(false));
          unawaited(_wishlistCubit.setAuthenticated(false));
          unawaited(_recentlyViewedCubit.setAuthenticated(false));
          unawaited(_searchHistoryCubit.setAuthenticated(false));
        }
        unawaited(_supportChatBadgeCubit.stop());
      }
      _wasLoggedInForPush = state.isLoggedIn;
    });

    _sessionCleanup.registerCleanupCallback(() {
      _cartCubit.resetSession();
      unawaited(_cartCubit.clearGuestData());
    });
    _sessionCleanup.registerCleanupCallback(() {
      _wishlistCubit.clearWishlist();
      unawaited(_wishlistCubit.clearGuestData());
    });
    _sessionCleanup.registerCleanupCallback(_ordersCubit.clearOrders);
    _sessionCleanup.registerCleanupCallback(_addressCubit.clearAddresses);
    _sessionCleanup.registerCleanupCallback(_loyaltyCubit.clearLoyalty);
    _sessionCleanup
        .registerCleanupCallback(_pushDeviceCubit.unregisterCurrentDevice);
    _sessionCleanup.registerCleanupCallback(_checkoutCubit.reset);
    _sessionCleanup.registerCleanupCallback(() {
      unawaited(FirebaseChatSession().signOut());
      unawaited(_supportChatBadgeCubit.stop());
    });
  }

  bool _wasLoggedInForPush = false;

  /// Initialize FCM and restore session in parallel so auth UI is not blocked
  /// behind permission dialogs / Firebase setup (which can take several seconds).
  Future<void> _bootstrapPushNotifications(
    FirebasePushNotificationService pushService,
  ) async {
    final sessionFuture = _initializeGuestState();
    await Future.wait<void>([
      sessionFuture,
      pushService.initialize(),
    ]);

    if (_accountCubit.state.isLoggedIn) {
      _wasLoggedInForPush = true;
      // Token registration can retry in background; sync guest data immediately.
      unawaited(_registerPushDeviceAfterLogin());
      await _synchronizeGuestData();
      unawaited(_supportChatBadgeCubit.start());
    }
  }

  /// Sends (or refreshes) the FCM device token for the logged-in customer.
  Future<void> _registerPushDeviceAfterLogin() async {
    // Brief delay so the auth token is readable by the Dio interceptor.
    await Future<void>.delayed(const Duration(milliseconds: 250));
    final registered = await _pushDeviceCubit.registerCurrentDevice();
    if (registered) return;

    // FCM may still be provisioning the token on cold start / first permission grant.
    await Future<void>.delayed(const Duration(seconds: 2));
    await _pushDeviceCubit.registerCurrentDevice();
  }

  Future<void> _synchronizeGuestData() async {
    // Flip authenticated mode before any network merge so add-to-cart
    // after login never silently writes to guest storage.
    await _cartCubit.setAuthenticated(true);
    await _wishlistCubit.setAuthenticated(true);
    await _recentlyViewedCubit.setAuthenticated(true);
    await _searchHistoryCubit.setAuthenticated(true);
    // Cart first to shrink the post-login race window.
    await _cartCubit.synchronizeGuestCart();
    await _wishlistCubit.synchronizeGuestWishlist();
    await _recentlyViewedCubit.synchronizeGuestRecentlyViewed();
    await _searchHistoryCubit.synchronizeGuestSearchHistory();
    await _ordersCubit.ensureOrdersLoaded(forceRefresh: true);
    await _notificationsCubit.load();
    unawaited(_supportChatBadgeCubit.start());
  }

  Future<void> _initializeGuestState() async {
    await _cartCubit.setAuthenticated(false);
    await _wishlistCubit.setAuthenticated(false);
    await _recentlyViewedCubit.setAuthenticated(false);
    await _searchHistoryCubit.setAuthenticated(false);
    _suppressLoginSideEffects = true;
    try {
      await _accountCubit.restoreSession();
    } finally {
      _suppressLoginSideEffects = false;
    }
    // If session restored, flip cart/wishlist to authenticated immediately
    // so the first post-splash add hits the API, not guest storage.
    if (_accountCubit.state.isLoggedIn) {
      await _cartCubit.setAuthenticated(true);
      await _wishlistCubit.setAuthenticated(true);
      await _recentlyViewedCubit.setAuthenticated(true);
      await _searchHistoryCubit.setAuthenticated(true);
    }
  }

  @override
  void dispose() {
    _pushRegistrationSubscription?.cancel();
    _cartCubit.close();
    _wishlistCubit.close();
    _ordersCubit.close();
    _accountCubit.close();
    _addressCubit.close();
    _loyaltyCubit.close();
    _supportCubit.close();
    _pushDeviceCubit.close();
    _currencyCubit.close();
    _checkoutCubit.close();
    _recentlyViewedCubit.close();
    _searchHistoryCubit.close();
    _notificationsCubit.close();
    _collectionsCubit.close();
    _supportChatBadgeCubit.close();
    DeepLinkService.instance.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RepositoryProvider<ReferenceRepository>.value(
      value: widget.referenceRepository,
      child: MultiBlocProvider(
        providers: [
          BlocProvider.value(value: _currencyCubit),
          BlocProvider(
              create: (_) => HomeCubit(homeRepository: HomeRepositoryImpl())
                ..loadHomeData()),
          BlocProvider(create: (_) => CatalogCubit()),
          BlocProvider(create: (_) => BrandsCubit()),
          BlocProvider(create: (_) => CategoriesCubit()..loadCategories()),
          BlocProvider(create: (_) => ConnectivityCubit()),
          BlocProvider.value(value: _cartCubit),
          BlocProvider.value(value: _wishlistCubit),
          BlocProvider.value(value: _checkoutCubit),
          BlocProvider.value(value: _ordersCubit),
          BlocProvider.value(value: _accountCubit),
          BlocProvider.value(value: _addressCubit),
          BlocProvider.value(value: _loyaltyCubit),
          BlocProvider.value(value: _supportCubit),
          BlocProvider.value(value: _pushDeviceCubit),
          BlocProvider.value(value: _recentlyViewedCubit),
          BlocProvider.value(value: _searchHistoryCubit),
          BlocProvider.value(value: _notificationsCubit),
          BlocProvider.value(value: _collectionsCubit),
          BlocProvider.value(value: _supportChatBadgeCubit),
          BlocProvider(create: (_) => AppShellCubit()),
        ],
        child: ScreenUtilInit(
          designSize: const Size(412, 860),
          minTextAdapt: true,
          splitScreenMode: true,
          builder: (context, child) {
            return MaterialApp(
              navigatorKey: PushNotificationRouter.navigatorKey,
              title: AppStrings.appNameArabic,
              debugShowCheckedModeBanner: false,
              theme: AppTheme.lightTheme,
              locale: const Locale('ar', 'YE'),
              supportedLocales: const [
                Locale('ar', 'YE'),
                Locale('ar', 'SA'),
                Locale('en', 'US'),
              ],
              localizationsDelegates: const [
                GlobalMaterialLocalizations.delegate,
                GlobalWidgetsLocalizations.delegate,
                GlobalCupertinoLocalizations.delegate,
              ],
              home: const SplashScreen(),
              // Android App Links also hit WidgetsBinding.didPushRouteInformation →
              // Navigator.pushNamed. We don't use named routes; DeepLinkService
              // owns navigation. Without onUnknownRoute, release builds crash on
              // onUnknownRoute!(settings) when the path isn't "/".
              onUnknownRoute: _discardOsNamedRoute,
            );
          },
        ),
      ),
    );
  }
}
