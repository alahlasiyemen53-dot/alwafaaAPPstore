import 'package:equatable/equatable.dart';

class PushDeviceModel extends Equatable {
  final int id;
  final String platform;
  final String? deviceIdentifier;
  final String? locale;
  final String? appVersion;
  final bool isActive;

  const PushDeviceModel({
    required this.id,
    required this.platform,
    this.deviceIdentifier,
    this.locale,
    this.appVersion,
    this.isActive = true,
  });

  factory PushDeviceModel.fromJson(Map<String, dynamic> json) {
    return PushDeviceModel(
      id: (json['id'] ?? 0) as int,
      platform: (json['platform'] ?? 'android') as String,
      deviceIdentifier: (json['device_identifier'] ?? json['deviceIdentifier']) as String?,
      locale: json['locale'] as String?,
      appVersion: (json['app_version'] ?? json['appVersion']) as String?,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
    );
  }

  @override
  List<Object?> get props => [id, platform, deviceIdentifier, locale, appVersion, isActive];
}

class RegisterPushDevicePayload {
  final String platform;
  final String fcmToken;
  final String? deviceIdentifier;
  final String? locale;
  final String? appVersion;

  const RegisterPushDevicePayload({
    required this.platform,
    required this.fcmToken,
    this.deviceIdentifier,
    this.locale,
    this.appVersion,
  });

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{
      'platform': platform,
      'fcm_token': fcmToken,
    };
    if (deviceIdentifier != null) map['device_identifier'] = deviceIdentifier;
    if (locale != null) map['locale'] = locale;
    if (appVersion != null) map['app_version'] = appVersion;
    return map;
  }
}
