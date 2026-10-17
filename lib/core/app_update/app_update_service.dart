import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../network/api_endpoints.dart';
import '../network/dio_client.dart';
import 'app_release_info.dart';
import 'app_update_policy.dart';
import 'app_version.dart';

final class AppUpdateCheckResult {
  const AppUpdateCheckResult({
    required this.decision,
    required this.release,
    required this.storeUrl,
  });

  static const fallback = AppUpdateCheckResult(
    decision: AppUpdateDecision(
      kind: AppUpdateKind.none,
      local: AppVersion(major: 0, minor: 0, patch: 0),
    ),
    release: AppReleaseInfo(),
    storeUrl: null,
  );

  final AppUpdateDecision decision;
  final AppReleaseInfo release;
  final String? storeUrl;
}

/// Fetches remote latest version and compares it to the installed app version.
/// Fail-open: network/parse errors yield [AppUpdateKind.none].
final class AppUpdateService {
  AppUpdateService({
    DioClient? dioClient,
    AppUpdatePolicy? policy,
    Future<PackageInfo> Function()? packageInfoLoader,
  })  : _dioClient = dioClient ?? DioClient(),
        _policy = policy ?? const AppUpdatePolicy(),
        _packageInfoLoader = packageInfoLoader ?? PackageInfo.fromPlatform;

  final DioClient _dioClient;
  final AppUpdatePolicy _policy;
  final Future<PackageInfo> Function() _packageInfoLoader;

  Future<AppUpdateCheckResult> check() async {
    try {
      final packageInfo = await _packageInfoLoader();
      final local = AppVersion.tryParse(
            '${packageInfo.version}+${packageInfo.buildNumber}',
          ) ??
          AppVersion.tryParse(packageInfo.version) ??
          const AppVersion(major: 0, minor: 0, patch: 0);

      final localLabel = local.display;
      AppReleaseInfo release = const AppReleaseInfo();
      try {
        // Reports this install's version so the server can auto-track "latest"
        // when [pubspec] is not on the host; then returns gate config.
        final response = await _dioClient.post(
          ApiEndpoints.appVersionCheck,
          data: {'client_version': localLabel},
        );
        final data = response.data;
        if (data is Map && data['data'] is Map) {
          release = AppReleaseInfo.fromJson(
            Map<String, dynamic>.from(data['data'] as Map),
          );
        }
      } catch (error, stack) {
        if (kDebugMode) {
          debugPrint('AppUpdateService: fail-open ($error)');
          debugPrint('$stack');
        }
        return AppUpdateCheckResult(
          decision: AppUpdateDecision(kind: AppUpdateKind.none, local: local),
          release: release,
          storeUrl: null,
        );
      }

      final remote = AppVersion.tryParse(release.latestVersion);
      final minVersion = AppVersion.tryParse(release.minVersion);
      final decision = _policy.evaluate(
        local: local,
        remote: remote,
        minVersion: minVersion,
        forceOverride: release.isForce,
      );

      return AppUpdateCheckResult(
        decision: decision,
        release: release,
        storeUrl: storeUrlForPlatform(release),
      );
    } catch (outerError, outerStack) {
      if (kDebugMode) {
        debugPrint('AppUpdateService: outer error ($outerError)');
        debugPrint('$outerStack');
      }
      return const AppUpdateCheckResult(
        decision: AppUpdateDecision(
          kind: AppUpdateKind.none,
          local: AppVersion(major: 0, minor: 0, patch: 0),
        ),
        release: AppReleaseInfo(),
        storeUrl: null,
      );
    }
  }

  String? storeUrlForPlatform(AppReleaseInfo release) {
    if (kIsWeb) return release.androidStoreUrl ?? release.iosStoreUrl;
    if (Platform.isIOS) {
      return release.iosStoreUrl ?? release.androidStoreUrl;
    }
    return release.androidStoreUrl ??
        release.iosStoreUrl ??
        'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp';
  }

  Future<bool> openStore(String? url) async {
    if (url == null || url.trim().isEmpty) return false;
    final uri = Uri.tryParse(url.trim());
    if (uri == null) return false;
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
