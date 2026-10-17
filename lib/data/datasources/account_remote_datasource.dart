import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/account/customer_address_model.dart';
import '../models/user_model.dart';

abstract interface class AccountRemoteDataSource {
  Future<UserModel> getProfile();
  Future<UserModel> updateProfile({String? name, String? email});
  Future<List<CustomerAddressModel>> getAddresses();
  Future<CustomerAddressModel> createAddress(Map<String, dynamic> data);
  Future<CustomerAddressModel> updateAddress(int id, Map<String, dynamic> data);
  Future<void> deleteAddress(int id);
}

final class AccountRemoteDataSourceImpl implements AccountRemoteDataSource {
  final DioClient _dioClient;

  AccountRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<UserModel> getProfile() async {
    final response = await _dioClient.get(ApiEndpoints.profile);
    return ApiEnvelope<UserModel>.parse(
      response.data,
      (data) => UserModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<UserModel> updateProfile({String? name, String? email}) async {
    final body = <String, dynamic>{};
    if (name != null) body['name'] = name;
    if (email != null) body['email'] = email;

    final response = await _dioClient.patch(
      ApiEndpoints.updateProfile,
      data: body,
    );
    return ApiEnvelope<UserModel>.parse(
      response.data,
      (data) => UserModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<List<CustomerAddressModel>> getAddresses() async {
    final response = await _dioClient.get(ApiEndpoints.addresses);
    return ApiEnvelope<List<CustomerAddressModel>>.parse(
      response.data,
      (data) {
        if (data is List) {
          return data
              .whereType<Map<String, dynamic>>()
              .map(CustomerAddressModel.fromJson)
              .toList();
        }
        return <CustomerAddressModel>[];
      },
    ).data;
  }

  @override
  Future<CustomerAddressModel> createAddress(Map<String, dynamic> data) async {
    final response = await _dioClient.post(
      ApiEndpoints.addresses,
      data: data,
    );
    return ApiEnvelope<CustomerAddressModel>.parse(
      response.data,
      (item) => CustomerAddressModel.fromJson(item as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CustomerAddressModel> updateAddress(int id, Map<String, dynamic> data) async {
    final response = await _dioClient.patch(
      ApiEndpoints.addressItem(id),
      data: data,
    );
    return ApiEnvelope<CustomerAddressModel>.parse(
      response.data,
      (item) => CustomerAddressModel.fromJson(item as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<void> deleteAddress(int id) async {
    await _dioClient.delete(ApiEndpoints.addressItem(id));
  }
}
