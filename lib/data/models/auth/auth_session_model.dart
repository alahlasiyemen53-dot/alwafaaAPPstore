import '../user_model.dart';

final class AuthSessionModel {
  final String accessToken;
  final String? refreshToken;
  final int expiresIn;
  final UserModel customer;

  const AuthSessionModel({
    required this.accessToken,
    this.refreshToken,
    required this.expiresIn,
    required this.customer,
  });

  factory AuthSessionModel.fromJson(Map<String, dynamic> json) {
    return AuthSessionModel(
      accessToken: json['access_token'] as String,
      refreshToken: json['refresh_token'] as String?,
      expiresIn: json['expires_in'] as int,
      customer: UserModel.fromJson(
        json['customer'] as Map<String, dynamic>,
      ),
    );
  }
}
