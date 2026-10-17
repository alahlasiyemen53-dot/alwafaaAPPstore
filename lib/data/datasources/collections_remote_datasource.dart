import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/catalog/collection_model.dart';

abstract interface class CollectionsRemoteDataSource {
  Future<CollectionsListResponse> getCollections();
  Future<CollectionDetailModel> getCollectionDetail(
    dynamic idOrSlug, {
    int page = 1,
    int perPage = 15,
  });
}

final class CollectionsRemoteDataSourceImpl
    implements CollectionsRemoteDataSource {
  final DioClient _dioClient;

  CollectionsRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<CollectionsListResponse> getCollections() async {
    final response = await _dioClient.get(ApiEndpoints.collections);
    return ApiEnvelope<CollectionsListResponse>.parse(
      response.data,
      (data) => CollectionsListResponse.fromJson(
        data as Map<String, dynamic>? ?? {},
      ),
    ).data;
  }

  @override
  Future<CollectionDetailModel> getCollectionDetail(
    dynamic idOrSlug, {
    int page = 1,
    int perPage = 15,
  }) async {
    final response = await _dioClient.get(
      ApiEndpoints.collectionDetails(idOrSlug),
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return ApiEnvelope<CollectionDetailModel>.parse(
      response.data,
      (data) => CollectionDetailModel.fromJson(
        data as Map<String, dynamic>? ?? {},
      ),
    ).data;
  }
}
