import '../datasources/collections_remote_datasource.dart';
import '../models/catalog/collection_model.dart';

abstract interface class CollectionsRepository {
  Future<CollectionsListResponse> getCollections();
  Future<CollectionDetailModel> getCollectionDetail(
    dynamic idOrSlug, {
    int page = 1,
    int perPage = 15,
  });
}

final class CollectionsRepositoryImpl implements CollectionsRepository {
  final CollectionsRemoteDataSource _remote;

  CollectionsRepositoryImpl({CollectionsRemoteDataSource? remote})
      : _remote = remote ?? CollectionsRemoteDataSourceImpl();

  @override
  Future<CollectionsListResponse> getCollections() => _remote.getCollections();

  @override
  Future<CollectionDetailModel> getCollectionDetail(
    dynamic idOrSlug, {
    int page = 1,
    int perPage = 15,
  }) =>
      _remote.getCollectionDetail(idOrSlug, page: page, perPage: perPage);
}
