import 'package:equatable/equatable.dart';

final class OtpChallengeModel extends Equatable {
  final String phone;
  final String challengeId;
  final int expiresIn;
  final int resendAfter;
  final bool isNewCustomer;
  final DateTime createdAt;

  const OtpChallengeModel({
    required this.phone,
    required this.challengeId,
    required this.expiresIn,
    required this.resendAfter,
    this.isNewCustomer = false,
    required this.createdAt,
  });

  factory OtpChallengeModel.fromJson({
    required String phone,
    required Map<String, dynamic> json,
    DateTime? createdAt,
  }) {
    return OtpChallengeModel(
      phone: phone,
      challengeId: json['challenge_id'] as String,
      expiresIn: json['expires_in'] as int,
      resendAfter: json['resend_after'] as int,
      isNewCustomer: json['is_new_customer'] as bool? ?? false,
      createdAt: createdAt ?? DateTime.now(),
    );
  }

  DateTime get expiresAt => createdAt.add(Duration(seconds: expiresIn));
  DateTime get canResendAt => createdAt.add(Duration(seconds: resendAfter));

  @override
  List<Object?> get props => [
        phone,
        challengeId,
        expiresIn,
        resendAfter,
        isNewCustomer,
        createdAt,
      ];
}
