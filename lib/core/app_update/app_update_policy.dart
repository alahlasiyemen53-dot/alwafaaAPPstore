import 'app_version.dart';

enum AppUpdateKind { none, optional, force }

final class AppUpdateDecision {
  const AppUpdateDecision({
    required this.kind,
    required this.local,
    this.remote,
  });

  final AppUpdateKind kind;
  final AppVersion local;
  final AppVersion? remote;

  bool get isForce => kind == AppUpdateKind.force;
  bool get isOptional => kind == AppUpdateKind.optional;
}

/// Derives force vs optional from semver:
/// - major or minor bump on server → force
/// - only patch/build higher → optional
/// - server ≤ local or unparsable remote → none
final class AppUpdatePolicy {
  const AppUpdatePolicy();

  AppUpdateDecision evaluate({
    required AppVersion local,
    required AppVersion? remote,
    AppVersion? minVersion,
    bool forceOverride = false,
  }) {
    if (remote == null) {
      return AppUpdateDecision(kind: AppUpdateKind.none, local: local);
    }

    if (remote.compareTo(local) <= 0) {
      return AppUpdateDecision(
        kind: AppUpdateKind.none,
        local: local,
        remote: remote,
      );
    }

    final force = forceOverride ||
        (minVersion != null && local.compareTo(minVersion) < 0) ||
        remote.major > local.major ||
        (remote.major == local.major && remote.minor > local.minor);

    return AppUpdateDecision(
      kind: force ? AppUpdateKind.force : AppUpdateKind.optional,
      local: local,
      remote: remote,
    );
  }
}
