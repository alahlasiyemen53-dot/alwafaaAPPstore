final class AppReleaseInfo {
  const AppReleaseInfo({
    this.latestVersion,
    this.androidStoreUrl,
    this.iosStoreUrl,
    this.minVersion,
    this.isForce = false,
  });

  final String? latestVersion;
  final String? androidStoreUrl;
  final String? iosStoreUrl;
  final String? minVersion;
  final bool isForce;

  factory AppReleaseInfo.fromJson(Map<String, dynamic> json) {
    return AppReleaseInfo(
      latestVersion: _asNonEmptyString(json['latest_version']),
      androidStoreUrl: _asNonEmptyString(json['android_store_url']),
      iosStoreUrl: _asNonEmptyString(json['ios_store_url']),
      minVersion: _asNonEmptyString(json['min_version']),
      isForce: json['is_force'] == true ||
          json['is_force'] == 1 ||
          json['is_force'] == '1' ||
          json['force_update'] == true ||
          json['force_update'] == 1 ||
          json['force_update'] == '1',
    );
  }

  static String? _asNonEmptyString(dynamic value) {
    if (value == null) return null;
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }
}

