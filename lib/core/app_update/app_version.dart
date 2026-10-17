/// Parsed `MAJOR.MINOR.PATCH[+BUILD]` app version.
final class AppVersion implements Comparable<AppVersion> {
  const AppVersion({
    required this.major,
    required this.minor,
    required this.patch,
    this.build = 0,
  });

  final int major;
  final int minor;
  final int patch;
  final int build;

  /// Parses `1.0.1+5` or `1.0.1`. Returns null when the string is invalid.
  static AppVersion? tryParse(String? raw) {
    if (raw == null) return null;
    final trimmed = raw.trim();
    if (trimmed.isEmpty) return null;

    final plus = trimmed.indexOf('+');
    final core = plus >= 0 ? trimmed.substring(0, plus) : trimmed;
    final buildPart = plus >= 0 ? trimmed.substring(plus + 1) : null;

    final parts = core.split('.');
    if (parts.length != 3) return null;

    final major = int.tryParse(parts[0]);
    final minor = int.tryParse(parts[1]);
    final patch = int.tryParse(parts[2]);
    if (major == null || minor == null || patch == null) return null;

    var build = 0;
    if (buildPart != null && buildPart.isNotEmpty) {
      final parsedBuild = int.tryParse(buildPart);
      if (parsedBuild == null) return null;
      build = parsedBuild;
    }

    return AppVersion(
      major: major,
      minor: minor,
      patch: patch,
      build: build,
    );
  }

  String get display {
    if (build > 0) return '$major.$minor.$patch+$build';
    return '$major.$minor.$patch';
  }

  @override
  int compareTo(AppVersion other) {
    if (major != other.major) return major.compareTo(other.major);
    if (minor != other.minor) return minor.compareTo(other.minor);
    if (patch != other.patch) return patch.compareTo(other.patch);
    return build.compareTo(other.build);
  }

  bool get isZero => major == 0 && minor == 0 && patch == 0 && build == 0;

  @override
  bool operator ==(Object other) =>
      other is AppVersion &&
      major == other.major &&
      minor == other.minor &&
      patch == other.patch &&
      build == other.build;

  @override
  int get hashCode => Object.hash(major, minor, patch, build);

  @override
  String toString() => display;
}
