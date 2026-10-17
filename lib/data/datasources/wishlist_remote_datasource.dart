import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/wishlist/wishlist_api_model.dart';

abstract interface class WishlistRemoteDataSource {
  Future<WishlistResponseModel> getWishlist({int page = 1, int perPage = 15});
  Future<int> addToWishlist(int productId);
  Future<int> removeFromWishlist(dynamic idOrSlug);
}

final class WishlistRemoteDataSourceImpl implements WishlistRemoteDataSource {
  final DioClient _dioClient;

  WishlistRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<WishlistResponseModel> getWishlist({int page = 1, int perPage = 15}) async {
    final response = await _dioClient.get(
      ApiEndpoints.wishlist,
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return ApiEnvelope<WishlistResponseModel>.parse(
      response.data,
      (data) => WishlistResponseModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<int> addToWishlist(int productId) async {
    final response = await _dioClient.post(
      ApiEndpoints.wishlist,
      data: {'product_id': productId},
    );
    return ApiEnvelope<int>.parse(
      response.data,
      (data) {
        if (data is Map<String, dynamic>) {
          return data['product_id'] as int? ?? productId;
        }
        return productId;
      },
    ).data;
  }

  @override
  Future<int> removeFromWishlist(dynamic idOrSlug) async {
    final response = await _dioClient.delete(
      ApiEndpoints.wishlistProduct(idOrSlug),
    );
    return ApiEnvelope<int>.parse(
      response.data,
      (data) {
        if (data is Map<String, dynamic>) {
          return data['product_id'] as int? ?? (int.tryParse(idOrSlug.toString()) ?? 0);
        }
        return int.tryParse(idOrSlug.toString()) ?? 0;
      },
    ).data;
  }
}
