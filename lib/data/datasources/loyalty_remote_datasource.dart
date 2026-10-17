import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/loyalty/customer_loyalty_model.dart';

abstract interface class LoyaltyRemoteDataSource {
  Future<CustomerLoyaltyModel> getLoyalty();
}

final class LoyaltyRemoteDataSourceImpl implements LoyaltyRemoteDataSource {
  final DioClient _dioClient;

  LoyaltyRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<CustomerLoyaltyModel> getLoyalty() async {
    final response = await _dioClient.get(ApiEndpoints.loyalty);
    return ApiEnvelope<CustomerLoyaltyModel>.parse(
      response.data,
      (data) => CustomerLoyaltyModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }
}
