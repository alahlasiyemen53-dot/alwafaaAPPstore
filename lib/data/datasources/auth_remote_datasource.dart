import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/auth/auth_session_model.dart';
import '../models/auth/otp_challenge_model.dart';
import '../models/user_model.dart';

abstract interface class AuthRemoteDataSource {
  Future<OtpChallengeModel> requestOtp(String phone, {int? countryId});

  Future<AuthSessionModel> verifyOtp({
    required String phone,
    required String challengeId,
    required String code,
    String? name,
    int? countryId,
    int? governorateId,
  });

  Future<UserModel> getMe();
  Future<void> logout();
}

final class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  final DioClient _dioClient;

  AuthRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<OtpChallengeModel> requestOtp(String phone, {int? countryId}) async {
    final response = await _dioClient.post(
      ApiEndpoints.requestOtp,
      data: {
        'phone': phone,
        'country_id': ?countryId,
      },
    );

    return ApiEnvelope<OtpChallengeModel>.parse(
      response.data,
      (data) => OtpChallengeModel.fromJson(
        phone: phone,
        json: data as Map<String, dynamic>,
      ),
    ).data;
  }

  @override
  Future<AuthSessionModel> verifyOtp({
    required String phone,
    required String challengeId,
    required String code,
    String? name,
    int? countryId,
    int? governorateId,
  }) async {
    final response = await _dioClient.post(
      ApiEndpoints.verifyOtp,
      data: {
        'phone': phone,
        'challenge_id': challengeId,
        'code': code,
        'name': ?(name?.trim().isNotEmpty == true ? name!.trim() : null),
        'country_id': ?countryId,
        'governorate_id': ?governorateId,
      },
    );

    return ApiEnvelope<AuthSessionModel>.parse(
      response.data,
      (data) => AuthSessionModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<UserModel> getMe() async {
    final response = await _dioClient.get(ApiEndpoints.profile);
    return ApiEnvelope<UserModel>.parse(
      response.data,
      (data) => UserModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<void> logout() async {
    final response = await _dioClient.post(ApiEndpoints.logout);
    ApiEnvelope<Object?>.parse(response.data, (data) => data);
  }
}
