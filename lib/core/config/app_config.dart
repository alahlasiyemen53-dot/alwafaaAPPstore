enum AppEnvironment { development, staging, production }

final class AppConfig {
  final AppEnvironment environment;
  final String apiBaseUrl;

  const AppConfig._({required this.environment, required this.apiBaseUrl});

  String get deepLinkBaseUrl {
    const envBase = String.fromEnvironment('DEEP_LINK_BASE_URL');
    if (envBase.trim().isNotEmpty) {
      return envBase.trim().replaceFirst(RegExp(r'/+$'), '');
    }

    try {
      final uri = Uri.parse(apiBaseUrl);
      final host = uri.host.toLowerCase();
      final isLocalOrPrivate = host == 'localhost' ||
          host == '127.0.0.1' ||
          host.startsWith('192.168.') ||
          host.startsWith('10.') ||
          host.startsWith('172.');
      if (!isLocalOrPrivate && uri.scheme == 'https') {
        final portPart = (uri.hasPort && uri.port != 80 && uri.port != 443)
            ? ':${uri.port}'
            : '';
        return '${uri.scheme}://${uri.host}$portPart';
      }
    } catch (_) {}

    return 'https://wafapp.alwafaamed.com';
  }

  static String resolveProductShareUrl(int productId) {
    try {
      return '${AppConfig.current().deepLinkBaseUrl}/p/$productId';
    } catch (_) {
      return 'https://wafapp.alwafaamed.com/p/$productId';
    }
  }

  factory AppConfig.current() => AppConfig.fromValues(
        environmentName: const String.fromEnvironment(
          'APP_ENV',
          defaultValue: 'development',
        ),
        apiBaseUrl: const String.fromEnvironment('API_BASE_URL'),
      );

  factory AppConfig.fromValues({
    required String environmentName,
    String apiBaseUrl = '',
  }) {
    final environment = _parseEnvironment(environmentName);
    final resolvedUrl = _resolveBaseUrl(environment, apiBaseUrl);
    _validateBaseUri(environment, resolvedUrl);

    return AppConfig._(
      environment: environment,
      apiBaseUrl: resolvedUrl.replaceFirst(RegExp(r'/+$'), ''),
    );
  }

  static AppEnvironment _parseEnvironment(String name) =>
      switch (name.trim().toLowerCase()) {
        'development' => AppEnvironment.development,
        'staging' => AppEnvironment.staging,
        'production' => AppEnvironment.production,
        _ => throw ArgumentError.value(name, 'environmentName'),
      };

  static String _resolveBaseUrl(
    AppEnvironment environment,
    String configuredUrl,
  ) {
    final trimmed = configuredUrl.trim().replaceFirst(RegExp(r'/+$'), '');
    if (trimmed.isNotEmpty) {
      if (!trimmed.endsWith('/api/v1')) {
        return '$trimmed/api/v1';
      }
      return trimmed;
    }
    if (environment == AppEnvironment.development) {
      return 'https://wafapp.alwafaamed.com/api/v1';
      // return 'http://10.0.0.48:8090/api/v1';
    }
    throw StateError('API_BASE_URL is required outside development.');
  }

  static void _validateBaseUri(
    AppEnvironment environment,
    String baseUrl,
  ) {
    final uri = Uri.tryParse(baseUrl);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw FormatException('API_BASE_URL must be an absolute URL.');
    }
    if (environment != AppEnvironment.development && uri.scheme != 'https') {
      throw StateError('Staging and production API URLs must use HTTPS.');
    }
  }
}
