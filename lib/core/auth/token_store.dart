import 'package:shared_preferences/shared_preferences.dart';

abstract interface class TokenStore {
  Future<String?> readAccessToken();
  Future<void> writeAccessToken(String token);
  Future<void> deleteAccessToken();
  Future<String?> readRefreshToken() => Future.value(null);
  Future<void> writeRefreshToken(String token) => Future.value();
  Future<void> clearSession();
}

/// SharedPreferences-backed token store with instant in-memory RAM caching.
/// Eliminates Android KeyStore deadlocks and decryption errors on app restore.
final class PreferencesTokenStore implements TokenStore {
  static const _accessTokenKey = 'customer_access_token';
  static const _refreshTokenKey = 'customer_refresh_token';

  static String? _cachedAccessToken;
  static String? _cachedRefreshToken;
  static bool _initialized = false;

  final SharedPreferences? _prefsInstance;

  PreferencesTokenStore({SharedPreferences? prefs})
      : _prefsInstance = prefs;

  Future<SharedPreferences?> _getPrefs() async {
    if (_prefsInstance != null) return _prefsInstance;
    try {
      return await SharedPreferences.getInstance();
    } catch (_) {
      return null;
    }
  }

  @override
  Future<String?> readAccessToken() async {
    if (_initialized) return _cachedAccessToken;
    try {
      final prefs = await _getPrefs();
      if (prefs != null) {
        _cachedAccessToken = prefs.getString(_accessTokenKey);
      }
      _initialized = true;
      return _cachedAccessToken;
    } catch (_) {
      return _cachedAccessToken;
    }
  }

  @override
  Future<void> writeAccessToken(String token) async {
    _cachedAccessToken = token;
    _initialized = true;
    try {
      final prefs = await _getPrefs();
      await prefs?.setString(_accessTokenKey, token);
    } catch (_) {}
  }

  @override
  Future<void> deleteAccessToken() async {
    _cachedAccessToken = null;
    try {
      final prefs = await _getPrefs();
      await prefs?.remove(_accessTokenKey);
    } catch (_) {}
  }

  @override
  Future<String?> readRefreshToken() async {
    if (_cachedRefreshToken != null) return _cachedRefreshToken;
    try {
      final prefs = await _getPrefs();
      if (prefs != null) {
        _cachedRefreshToken = prefs.getString(_refreshTokenKey);
      }
      return _cachedRefreshToken;
    } catch (_) {
      return _cachedRefreshToken;
    }
  }

  @override
  Future<void> writeRefreshToken(String token) async {
    _cachedRefreshToken = token;
    try {
      final prefs = await _getPrefs();
      await prefs?.setString(_refreshTokenKey, token);
    } catch (_) {}
  }

  @override
  Future<void> clearSession() async {
    _cachedAccessToken = null;
    _cachedRefreshToken = null;
    _initialized = true;
    try {
      final prefs = await _getPrefs();
      await prefs?.remove(_accessTokenKey);
      await prefs?.remove(_refreshTokenKey);
    } catch (_) {}
  }
}

/// Backward compatibility alias so existing callers work seamlessly.
typedef FlutterSecureTokenStore = PreferencesTokenStore;
