import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/catalog/product_list_item_model.dart';
import '../models/product_model.dart';
import 'guest_discovery_storage.dart';

abstract interface class DiscoveryRemoteDataSource {
  Future<List<ProductModel>> getRecentlyViewed();
  Future<void> recordProductView(int productId);
  Future<void> mergeRecentlyViewed(List<int> productIds);
  Future<List<SearchHistoryItem>> getSearchHistory();
  Future<void> recordSearch(String query);
  Future<void> deleteSearch(int id);
  Future<void> clearSearchHistory();
  Future<ReorderReport> reorderOrder(int orderId);
  Future<NotificationsPage> getNotifications({int page = 1});
  Future<void> markNotificationRead(int id);
  Future<void> markAllNotificationsRead();
  Future<List<CollectionSummary>> getCollections();
  Future<CollectionDetailPage> getCollection(dynamic idOrSlug, {int page = 1});
}

final class SearchHistoryItem {
  final int? id;
  final String query;
  final String? lastSearchedAt;

  const SearchHistoryItem({
    this.id,
    required this.query,
    this.lastSearchedAt,
  });

  factory SearchHistoryItem.fromJson(Map<String, dynamic> json) =>
      SearchHistoryItem(
        id: json['id'] as int?,
        query: json['query'] as String? ?? '',
        lastSearchedAt: json['last_searched_at'] as String?,
      );
}

final class ReorderReport {
  final int addedCount;
  final int skippedCount;
  final List<Map<String, dynamic>> added;
  final List<Map<String, dynamic>> skipped;

  const ReorderReport({
    required this.addedCount,
    required this.skippedCount,
    this.added = const [],
    this.skipped = const [],
  });

  factory ReorderReport.fromJson(Map<String, dynamic> json) => ReorderReport(
        addedCount: json['added_count'] as int? ?? 0,
        skippedCount: json['skipped_count'] as int? ?? 0,
        added: (json['added'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .toList() ??
            const [],
        skipped: (json['skipped'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .toList() ??
            const [],
      );
}

final class AppNotificationItem {
  final int id;
  final String type;
  final String title;
  final String body;
  final String destinationType;
  final int? destinationId;
  final String? readAt;
  final String? createdAt;

  const AppNotificationItem({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.destinationType,
    this.destinationId,
    this.readAt,
    this.createdAt,
  });

  bool get isUnread => readAt == null || readAt!.isEmpty;

  factory AppNotificationItem.fromJson(Map<String, dynamic> json) =>
      AppNotificationItem(
        id: json['id'] as int? ?? 0,
        type: json['type'] as String? ?? '',
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
        destinationType: json['destination_type'] as String? ?? 'none',
        destinationId: json['destination_id'] as int?,
        readAt: json['read_at'] as String?,
        createdAt: json['created_at'] as String?,
      );
}

final class NotificationsPage {
  final List<AppNotificationItem> items;
  final int unreadCount;
  final bool hasMore;
  final int currentPage;

  const NotificationsPage({
    required this.items,
    required this.unreadCount,
    required this.hasMore,
    required this.currentPage,
  });
}

final class CollectionSummary {
  final int id;
  final String nameAr;
  final String? nameEn;
  final String slug;
  final String? coverUrl;

  const CollectionSummary({
    required this.id,
    required this.nameAr,
    required this.slug,
    this.nameEn,
    this.coverUrl,
  });

  factory CollectionSummary.fromJson(Map<String, dynamic> json) =>
      CollectionSummary(
        id: json['id'] as int? ?? 0,
        nameAr: json['name_ar'] as String? ?? '',
        nameEn: json['name_en'] as String?,
        slug: json['slug'] as String? ?? '',
        coverUrl: json['cover_url'] as String?,
      );
}

final class CollectionDetailPage {
  final CollectionSummary collection;
  final List<ProductModel> products;
  final bool hasMore;

  const CollectionDetailPage({
    required this.collection,
    required this.products,
    required this.hasMore,
  });
}

final class DiscoveryRemoteDataSourceImpl implements DiscoveryRemoteDataSource {
  final DioClient _dio;
  final SharedPreferencesGuestDiscoveryStorage _guest;

  DiscoveryRemoteDataSourceImpl({
    DioClient? dioClient,
    SharedPreferencesGuestDiscoveryStorage? guestStorage,
  })  : _dio = dioClient ?? DioClient(),
        _guest = guestStorage ?? SharedPreferencesGuestDiscoveryStorage();

  List<ProductModel> _mapProducts(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map((item) => ProductModel.fromApiListItem(
              ProductListItemModel.fromJson(item),
            ))
        .toList();
  }

  @override
  Future<List<ProductModel>> getRecentlyViewed() async {
    final response = await _dio.get(ApiEndpoints.recentlyViewed);
    return ApiEnvelope<List<ProductModel>>.parse(
      response.data,
      (data) {
        if (data is Map<String, dynamic>) {
          return _mapProducts(data['items']);
        }
        return _mapProducts(data);
      },
    ).data;
  }

  @override
  Future<void> recordProductView(int productId) async {
    if (productId > 0) {
      await _guest.recordRecentlyViewed(
        GuestRecentlyViewedEntry(
          productId: productId,
          name: '',
          imageUrl: 'assets/images/placeholder.png',
          displayPrice: 0,
          viewedAt: DateTime.now(),
        ),
      );
    }
    try {
      await _dio.post(ApiEndpoints.productView(productId));
    } catch (_) {
      // Guest path already persisted locally; auth failures are non-fatal.
    }
  }

  @override
  Future<void> mergeRecentlyViewed(List<int> productIds) async {
    await _dio.post(
      ApiEndpoints.recentlyViewedMerge,
      data: {'product_ids': productIds},
    );
  }

  @override
  Future<List<SearchHistoryItem>> getSearchHistory() async {
    try {
      final response = await _dio.get(ApiEndpoints.searchHistory);
      return ApiEnvelope<List<SearchHistoryItem>>.parse(
        response.data,
        (data) {
          final items = data is Map<String, dynamic> ? data['items'] : data;
          if (items is! List) return const <SearchHistoryItem>[];
          return items
              .whereType<Map<String, dynamic>>()
              .map(SearchHistoryItem.fromJson)
              .toList();
        },
      ).data;
    } catch (_) {
      final local = await _guest.searchHistoryEntries();
      return local
          .map((entry) => SearchHistoryItem(
                query: entry.query,
                lastSearchedAt: entry.lastSearchedAt?.toIso8601String(),
              ))
          .toList();
    }
  }

  @override
  Future<void> recordSearch(String query) async {
    await _guest.recordSearchQuery(query);
    try {
      await _dio.post(ApiEndpoints.searchHistory, data: {'query': query});
    } catch (_) {}
  }

  @override
  Future<void> deleteSearch(int id) async {
    await _dio.delete(ApiEndpoints.searchHistoryItem(id));
  }

  @override
  Future<void> clearSearchHistory() async {
    await _guest.saveSearchHistoryEntries(const []);
    try {
      await _dio.delete(ApiEndpoints.searchHistory);
    } catch (_) {}
  }

  @override
  Future<ReorderReport> reorderOrder(int orderId) async {
    final response = await _dio.post(ApiEndpoints.orderReorder(orderId));
    return ApiEnvelope<ReorderReport>.parse(
      response.data,
      (data) => ReorderReport.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<NotificationsPage> getNotifications({int page = 1}) async {
    final response = await _dio.get(
      ApiEndpoints.notifications,
      queryParameters: {'page': page, 'per_page': 20},
    );
    return ApiEnvelope<NotificationsPage>.parse(
      response.data,
      (data) {
        final map = data as Map<String, dynamic>;
        final items = (map['items'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .map(AppNotificationItem.fromJson)
                .toList() ??
            const <AppNotificationItem>[];
        final pagination = map['pagination'] as Map<String, dynamic>? ?? {};
        return NotificationsPage(
          items: items,
          unreadCount: map['unread_count'] as int? ?? 0,
          hasMore: pagination['has_more'] as bool? ?? false,
          currentPage: pagination['current_page'] as int? ?? page,
        );
      },
    ).data;
  }

  @override
  Future<void> markNotificationRead(int id) async {
    await _dio.patch(ApiEndpoints.notificationRead(id));
  }

  @override
  Future<void> markAllNotificationsRead() async {
    await _dio.patch(ApiEndpoints.notificationsReadAll);
  }

  @override
  Future<List<CollectionSummary>> getCollections() async {
    final response = await _dio.get(ApiEndpoints.collections);
    return ApiEnvelope<List<CollectionSummary>>.parse(
      response.data,
      (data) {
        final items = data is Map<String, dynamic> ? data['items'] : data;
        if (items is! List) return const <CollectionSummary>[];
        return items
            .whereType<Map<String, dynamic>>()
            .map(CollectionSummary.fromJson)
            .toList();
      },
    ).data;
  }

  @override
  Future<CollectionDetailPage> getCollection(dynamic idOrSlug,
      {int page = 1}) async {
    final response = await _dio.get(
      ApiEndpoints.collectionDetails(idOrSlug),
      queryParameters: {'page': page, 'per_page': 20},
    );
    return ApiEnvelope<CollectionDetailPage>.parse(
      response.data,
      (data) {
        final map = data as Map<String, dynamic>;
        final collectionJson =
            map['collection'] as Map<String, dynamic>? ?? const {};
        final pagination = map['pagination'] as Map<String, dynamic>? ?? {};
        return CollectionDetailPage(
          collection: CollectionSummary.fromJson(collectionJson),
          products: _mapProducts(map['items']),
          hasMore: pagination['has_more'] as bool? ?? false,
        );
      },
    ).data;
  }
}
