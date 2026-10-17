import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'push_notification_router.dart';
import 'push_notification_service.dart';

/// Topic used for first-party “general” broadcasts to all installed apps.
const String kStoreappGeneralTopic = 'storeapp_general';

/// Android channel id — must match
/// `com.google.firebase.messaging.default_notification_channel_id` in the manifest.
const String kStoreappDefaultChannelId = 'storeapp_default_channel';

/// Real FCM-backed implementation of [PushNotificationService], used once
/// Firebase has been initialized (see `main.dart`).
class FirebasePushNotificationService implements PushNotificationService {
  final FirebaseMessaging _messaging;
  final FlutterLocalNotificationsPlugin _localNotifications;

  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  bool _initialized = false;

  FirebasePushNotificationService({
    FirebaseMessaging? messaging,
    FlutterLocalNotificationsPlugin? localNotifications,
  })  : _messaging = messaging ?? FirebaseMessaging.instance,
        _localNotifications =
            localNotifications ?? FlutterLocalNotificationsPlugin();

  @override
  Future<String?> getDeviceToken() async {
    try {
      if (!kIsWeb && Platform.isIOS) {
        // On iOS, FCM cannot provide a token until APNs token is granted by Apple.
        // Poll for getAPNSToken() with progressive retries.
        String? apnsToken = await _messaging.getAPNSToken();
        if (apnsToken == null) {
          debugPrint('FCM: Waiting for Apple APNs token on iOS...');
          for (int attempt = 1; attempt <= 6; attempt++) {
            await Future<void>.delayed(const Duration(milliseconds: 800));
            apnsToken = await _messaging.getAPNSToken();
            if (apnsToken != null) {
              debugPrint('FCM: APNs token acquired on attempt $attempt');
              break;
            }
          }
        }
        if (apnsToken == null) {
          debugPrint(
            'FCM WARNING: APNs device token is null on iOS. '
            'Verify Push Notifications capability, entitlements, and bundle ID.',
          );
        }
      }

      final token = await _messaging.getToken();
      if (token != null && token.isNotEmpty) {
        debugPrint('FCM: Device token retrieved successfully.');
      }
      return token;
    } catch (e, stack) {
      debugPrint('FCM getDeviceToken error: $e');
      debugPrintStack(stackTrace: stack);
      return null;
    }
  }

  @override
  Stream<String> get onTokenRefresh => _messaging.onTokenRefresh;

  @override
  Future<bool> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    return settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;
  }

  Future<void> initialize() async {
    if (_initialized) return;

    try {
      await requestPermission().timeout(
        const Duration(seconds: 4),
        onTimeout: () => false,
      );
      await _setupLocalNotifications();
      await _messaging
          .setForegroundNotificationPresentationOptions(
            alert: true,
            badge: true,
            sound: true,
          )
          .timeout(const Duration(seconds: 2), onTimeout: () {});

      // On iOS, waiting for APNs before topic subscription prevents crash/failures
      if (!kIsWeb && Platform.isIOS) {
        try {
          await _messaging.getAPNSToken().timeout(
            const Duration(seconds: 2),
            onTimeout: () => null,
          );
        } catch (_) {}
      }

      // Topic subscription must never hang startup (e.g. if Google Play SHA-1 mismatch or offline)
      try {
        await _messaging.subscribeToTopic(kStoreappGeneralTopic).timeout(
          const Duration(seconds: 4),
          onTimeout: () {},
        );
      } catch (e) {
        debugPrint('FCM subscribeToTopic non-fatal: $e');
      }

      _foregroundSubscription?.cancel();
      _foregroundSubscription =
          FirebaseMessaging.onMessage.listen(_showForegroundNotification);

      _openedSubscription?.cancel();
      _openedSubscription =
          FirebaseMessaging.onMessageOpenedApp.listen(_handleRemoteMessage);

      final initial = await _messaging.getInitialMessage().timeout(
        const Duration(seconds: 2),
        onTimeout: () => null,
      );
      if (initial != null) {
        // Delay until MaterialApp has a navigator context.
        Future<void>.delayed(const Duration(milliseconds: 800), () {
          _handleRemoteMessage(initial);
        });
      }

      _initialized = true;
    } catch (error, stack) {
      debugPrint('FCM initialize failed: $error');
      debugPrintStack(stackTrace: stack);
    }
  }

  Future<void> _setupLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings();
    const initSettings = InitializationSettings(
      android: androidInit,
      iOS: iosInit,
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (response) {
        PushNotificationRouter.handlePayloadString(response.payload);
      },
    );

    final androidPlugin = _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();

    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        kStoreappDefaultChannelId,
        'إشعارات لمّة',
        description: 'الإشعارات العامة والتنبيهات التشغيلية لتطبيق لمّة',
        importance: Importance.high,
      ),
    );

    await androidPlugin?.requestNotificationsPermission();

    final launchDetails =
        await _localNotifications.getNotificationAppLaunchDetails();
    if (launchDetails?.didNotificationLaunchApp ?? false) {
      final payload = launchDetails?.notificationResponse?.payload;
      if (payload != null && payload.isNotEmpty) {
        debugPrint('FCM: App launched via local notification payload: $payload');
        PushNotificationRouter.handlePayloadString(payload);
      }
    }
  }

  void _handleRemoteMessage(RemoteMessage message) {
    debugPrint('FCM: handleRemoteMessage tapped with data: ${message.data}');
    if (message.data.isEmpty) return;
    PushNotificationRouter.handleData(
      message.data.map((key, value) => MapEntry(key, value)),
    );
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final payload = message.data.isEmpty ? null : jsonEncode(message.data);

    await _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          kStoreappDefaultChannelId,
          'إشعارات لمّة',
          channelDescription: 'الإشعارات العامة والتنبيهات التشغيلية لتطبيق لمّة',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
      payload: payload,
    );
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
}
