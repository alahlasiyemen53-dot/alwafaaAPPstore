import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/catalog/home_api_model.dart';

abstract interface class HomeRemoteDataSource {
  Future<HomeApiModel> getHomeData();
}

final class HomeRemoteDataSourceImpl implements HomeRemoteDataSource {
  final DioClient _dioClient;

  HomeRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<HomeApiModel> getHomeData() async {
    final response = await _dioClient.get(ApiEndpoints.home);
    return ApiEnvelope<HomeApiModel>.parse(
      response.data,
      (data) => HomeApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }
}
