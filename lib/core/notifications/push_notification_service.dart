import 'dart:async';
import 'dart:io';

abstract interface class PushNotificationService {
  Future<String?> getDeviceToken();
  Stream<String> get onTokenRefresh;
  Future<bool> requestPermission();
  String get platformName;
}

class DefaultPushNotificationService implements PushNotificationService {
  final StreamController<String> _tokenController = StreamController<String>.broadcast();
  String? _cachedToken;

  DefaultPushNotificationService({String? initialToken}) : _cachedToken = initialToken;

  @override
  Future<String?> getDeviceToken() async {
    _cachedToken ??= 'fcm_token_device_${DateTime.now().millisecondsSinceEpoch}';
    return _cachedToken;
  }

  @override
  Stream<String> get onTokenRefresh => _tokenController.stream;

  @override
  Future<bool> requestPermission() async {
    return true;
  }

  @override
  String get platformName {
    try {
      if (Platform.isIOS) return 'ios';
      return 'android';
    } catch (_) {
      return 'android';
    }
  }

  void simulateTokenRefresh(String newToken) {
    _cachedToken = newToken;
    _tokenController.add(newToken);
  }

  void dispose() {
    _tokenController.close();
  }
}
