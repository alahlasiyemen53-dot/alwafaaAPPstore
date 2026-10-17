/// ApiEndpoints defines all backend REST API route paths.
class ApiEndpoints {
  ApiEndpoints._();

  // Home & Catalog (Sprint 24, 25, 30)
  static const String home = '/home';
  static const String categories = '/categories';
  static String categoryDetails(dynamic idOrSlug) => '/categories/$idOrSlug';
  static const String brands = '/brands';
  static String brandDetails(dynamic id) => '/brands/$id';
  static const String products = '/products';
  static const String productSearch = '/products/search';
  static String productDetails(dynamic idOrSlug) => '/products/$idOrSlug';
  static String relatedProducts(dynamic idOrSlug, [int? limit]) =>
      '/products/$idOrSlug/related${limit != null ? "?limit=$limit" : ""}';
  static String productReviews(dynamic idOrSlug) =>
      '/products/$idOrSlug/reviews';
  static String reviewComments(int reviewId) =>
      '/reviews/$reviewId/comments';
  static const String visualSearch = '/search/visual';
  static String productView(dynamic idOrSlug) => '/products/$idOrSlug/view';

  // Discovery & Engagement
  static const String recentlyViewed = '/me/recently-viewed';
  static const String recentlyViewedMerge = '/me/recently-viewed/merge';
  static const String searchHistory = '/me/search-history';
  static const String searchHistoryMerge = '/me/search-history/merge';
  static String searchHistoryItem(int id) => '/me/search-history/$id';
  static const String notifications = '/me/notifications';
  static String notificationRead(int id) => '/me/notifications/$id/read';
  static const String notificationsReadAll = '/me/notifications/read-all';
  static const String collections = '/collections';
  static String collectionDetails(dynamic idOrSlug) => '/collections/$idOrSlug';
  static String orderReorder(dynamic id) => '/orders/$id/reorder';

  // Wishlist (Sprint 27 & 36)
  static const String wishlist = '/wishlist';
  static String wishlistProduct(dynamic idOrSlug) => '/wishlist/$idOrSlug';

  // Cart & Coupon (Sprint 28 & 36)
  static const String cart = '/cart';
  static const String cartItems = '/cart/items';
  static String cartItem(int itemId) => '/cart/items/$itemId';
  static const String cartCoupon = '/cart/coupon';
  static const String checkout = '/orders/checkout';
  static const String checkoutPreview = '/checkout/preview';

  // Orders
  static const String orders = '/orders';
  static String orderDetails(dynamic id) => '/orders/$id';
  static String trackOrder(dynamic id) => '/orders/$id/tracking';
  static String orderConfirmDelivery(dynamic id) =>
      '/orders/$id/confirm-delivery';
  static String orderRatings(dynamic id) => '/orders/$id/ratings';
  static const String paymentsInitiate = '/payments/initiate';
  static String paymentConfirm(String uuid) => '/payments/$uuid/confirm';

  // User & Auth & Profile (Sprint 23 & 37)
  static const String requestOtp = '/auth/otp/request';
  static const String verifyOtp = '/auth/otp/verify';
  static const String logout = '/auth/logout';
  static const String profile = '/me';
  static const String updateProfile = '/me';

  // Addresses (Sprint 29 & 37)
  static const String addresses = '/addresses';
  static String addressItem(int id) => '/addresses/$id';

  // Geography & Reference (Sprint 29 & Country migration)
  static const String countries = '/countries';
  static String countryGovernorates(int countryId) =>
      '/countries/$countryId/governorates';
  static const String governorates = '/governorates';
  static String governorateCities(int id) => '/governorates/$id/cities';
  static String cityDistricts(int id) => '/cities/$id/districts';
  static const String currencies = '/currencies';
  static const String sanaaDeliveryZone = '/delivery/sanaa-zone';

  // Loyalty & Support & Devices (Sprint 30 & 37)
  static const String loyalty = '/loyalty';
  static const String support = '/support';
  static const String devices = '/devices';
  static String deviceItem(dynamic id) => '/devices/$id';

  // App update gate
  static const String appVersion = '/app/version';
  static const String appVersionCheck = '/app/version/check';

  // Support live chat
  static const String supportFirebaseToken = '/support/firebase-token';
  static const String supportConversations = '/support/conversations';
  static String supportConversation(String id) => '/support/conversations/$id';
  static String supportConversationMessages(String id) =>
      '/support/conversations/$id/messages';
  static String supportConversationRead(String id) =>
      '/support/conversations/$id/read';
  static String supportConversationMessageAttachment(
    String conversationId,
    String messageId,
  ) =>
      '/support/conversations/$conversationId/messages/$messageId/attachment';
}
