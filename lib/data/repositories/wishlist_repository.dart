import '../datasources/wishlist_remote_datasource.dart';
import '../models/wishlist/wishlist_api_model.dart';

abstract interface class WishlistRepository {
  Future<WishlistResponseModel> getWishlist({int page = 1, int perPage = 15});
  Future<int> addToWishlist(int productId);
  Future<int> removeFromWishlist(dynamic idOrSlug);
}

final class WishlistRepositoryImpl implements WishlistRepository {
  final WishlistRemoteDataSource _remoteDataSource;

  WishlistRepositoryImpl({WishlistRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? WishlistRemoteDataSourceImpl();

  @override
  Future<WishlistResponseModel> getWishlist({int page = 1, int perPage = 15}) {
    return _remoteDataSource.getWishlist(page: page, perPage: perPage);
  }

  @override
  Future<int> addToWishlist(int productId) {
    return _remoteDataSource.addToWishlist(productId);
  }

  @override
  Future<int> removeFromWishlist(dynamic idOrSlug) {
    return _remoteDataSource.removeFromWishlist(idOrSlug);
  }
}
