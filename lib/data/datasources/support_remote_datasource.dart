import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/support/customer_support_model.dart';

abstract interface class SupportRemoteDataSource {
  Future<CustomerSupportModel> getSupportInfo();
}

final class SupportRemoteDataSourceImpl implements SupportRemoteDataSource {
  final DioClient _dioClient;

  SupportRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<CustomerSupportModel> getSupportInfo() async {
    final response = await _dioClient.get(ApiEndpoints.support);
    return ApiEnvelope<CustomerSupportModel>.parse(
      response.data,
      (data) => CustomerSupportModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }
}
