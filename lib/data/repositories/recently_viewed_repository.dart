import '../datasources/recently_viewed_remote_datasource.dart';
import '../models/catalog/product_list_item_model.dart';

abstract interface class RecentlyViewedRepository {
  Future<List<ProductListItemModel>> getRecentlyViewed();
  Future<void> recordView(dynamic productIdOrSlug);
  Future<List<ProductListItemModel>> mergeGuestViews(List<int> productIds);
}

final class RecentlyViewedRepositoryImpl implements RecentlyViewedRepository {
  final RecentlyViewedRemoteDataSource _remote;

  RecentlyViewedRepositoryImpl({RecentlyViewedRemoteDataSource? remote})
      : _remote = remote ?? RecentlyViewedRemoteDataSourceImpl();

  @override
  Future<List<ProductListItemModel>> getRecentlyViewed() =>
      _remote.getRecentlyViewed();

  @override
  Future<void> recordView(dynamic productIdOrSlug) =>
      _remote.recordView(productIdOrSlug);

  @override
  Future<List<ProductListItemModel>> mergeGuestViews(List<int> productIds) =>
      _remote.mergeGuestViews(productIds);
}
