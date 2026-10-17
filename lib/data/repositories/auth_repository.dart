import 'dart:async';

import '../../core/auth/token_store.dart';
import '../../core/network/dio_client.dart';
import '../datasources/auth_remote_datasource.dart';
import '../models/auth/otp_challenge_model.dart';
import '../models/user_model.dart';

abstract interface class AuthRepository {
  Future<OtpChallengeModel> requestOtp(String phone, {int? countryId});

  Future<UserModel> verifyOtp({
    required String phone,
    required String challengeId,
    required String code,
    String? name,
    int? countryId,
    int? governorateId,
  });

  Future<UserModel?> restoreSession();
  Future<void> logout();
}

final class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource _remoteDataSource;
  final TokenStore _tokenStore;

  factory AuthRepositoryImpl({
    AuthRemoteDataSource? remoteDataSource,
    TokenStore? tokenStore,
    void Function()? onUnauthorized,
  }) {
    final resolvedTokenStore = tokenStore ?? FlutterSecureTokenStore();
    return AuthRepositoryImpl._(
      remoteDataSource ??
          AuthRemoteDataSourceImpl(
            dioClient: DioClient(
              tokenStore: resolvedTokenStore,
              onUnauthorized: onUnauthorized,
            ),
          ),
      resolvedTokenStore,
    );
  }

  AuthRepositoryImpl._(this._remoteDataSource, this._tokenStore);

  @override
  Future<OtpChallengeModel> requestOtp(String phone, {int? countryId}) =>
      _remoteDataSource.requestOtp(phone, countryId: countryId);

  @override
  Future<UserModel> verifyOtp({
    required String phone,
    required String challengeId,
    required String code,
    String? name,
    int? countryId,
    int? governorateId,
  }) async {
    final session = await _remoteDataSource.verifyOtp(
      phone: phone,
      challengeId: challengeId,
      code: code,
      name: name,
      countryId: countryId,
      governorateId: governorateId,
    );
    await _tokenStore.writeAccessToken(session.accessToken);
    if (session.refreshToken != null) {
      await _tokenStore.writeRefreshToken(session.refreshToken!);
    }

    return _remoteDataSource.getMe();
  }

  @override
  Future<UserModel?> restoreSession() async {
    final token = await _tokenStore.readAccessToken();
    if (token == null || token.isEmpty) return null;

    // Cap restore latency so the account screen never sits on
    // "جارٍ التحقق من الجلسة الآمنة..." for the full Dio 15s timeout.
    return _remoteDataSource.getMe().timeout(
      const Duration(seconds: 6),
      onTimeout: () => throw TimeoutException('restoreSession timed out'),
    );
  }

  @override
  Future<void> logout() async {
    try {
      if ((await _tokenStore.readAccessToken())?.isNotEmpty == true) {
        await _remoteDataSource.logout();
      }
    } finally {
      await _tokenStore.clearSession();
    }
  }
}
