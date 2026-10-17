import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/catalog/product_list_item_model.dart';

abstract interface class RecentlyViewedRemoteDataSource {
  Future<List<ProductListItemModel>> getRecentlyViewed();
  Future<void> recordView(dynamic productIdOrSlug);
  Future<List<ProductListItemModel>> mergeGuestViews(List<int> productIds);
}

final class RecentlyViewedRemoteDataSourceImpl
    implements RecentlyViewedRemoteDataSource {
  final DioClient _dioClient;

  RecentlyViewedRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  List<ProductListItemModel> _parseItems(Object? data) {
    if (data is! Map<String, dynamic>) return const [];
    final raw = data['items'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(ProductListItemModel.fromJson)
        .toList();
  }

  @override
  Future<List<ProductListItemModel>> getRecentlyViewed() async {
    final response = await _dioClient.get(ApiEndpoints.recentlyViewed);
    return ApiEnvelope<List<ProductListItemModel>>.parse(
      response.data,
      _parseItems,
    ).data;
  }

  @override
  Future<void> recordView(dynamic productIdOrSlug) async {
    final response =
        await _dioClient.post(ApiEndpoints.productView(productIdOrSlug));
    ApiEnvelope<Object?>.parse(response.data, (data) => data);
  }

  @override
  Future<List<ProductListItemModel>> mergeGuestViews(
    List<int> productIds,
  ) async {
    final response = await _dioClient.post(
      ApiEndpoints.recentlyViewedMerge,
      data: {'product_ids': productIds},
    );
    return ApiEnvelope<List<ProductListItemModel>>.parse(
      response.data,
      _parseItems,
    ).data;
  }
}
