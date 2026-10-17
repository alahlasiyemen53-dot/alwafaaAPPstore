import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';

/// Service responsible for managing Firebase Crashlytics logging and error tracking.
/// Handles Flutter errors, uncaught async errors, and provides safe APIs that respect user privacy.
class CrashlyticsService {
  CrashlyticsService._();

  static final CrashlyticsService instance = CrashlyticsService._();

  /// Keys or patterns that must never be recorded to Crashlytics for user privacy and security.
  static final RegExp _sensitivePattern = RegExp(
    r'(password|token|secret|authorization|bearer|auth|credit_card|cvv|pin|otp)',
    caseSensitive: false,
  );

  /// Initializes Crashlytics error handlers and hooks.
  static Future<void> initialize() async {
    // Enable crash collection in both debug and release, or configure as needed.
    // By keeping it explicitly enabled, developers can test reports directly from debug builds.
    await FirebaseCrashlytics.instance.setCrashlyticsCollectionEnabled(true);

    // Pass all uncaught fatal errors from the Flutter framework to Crashlytics,
    // while gracefully catching font network loading errors without crashing.
    FlutterError.onError = (FlutterErrorDetails details) {
      final message = details.exceptionAsString();
      final isFontError = message.contains('Failed to load font') ||
          message.contains('fonts.gstatic.com');
      if (isFontError) {
        if (kDebugMode) {
          debugPrint('CrashlyticsService: Handled non-fatal font loading error: $message');
        }
        FirebaseCrashlytics.instance.recordError(
          details.exception,
          details.stack,
          reason: 'Non-fatal font download failure',
          fatal: false,
        );
        return;
      }
      FirebaseCrashlytics.instance.recordFlutterFatalError(details);
    };

    // Pass all uncaught asynchronous errors to Crashlytics,
    // preventing async font network exceptions from terminating the isolate.
    PlatformDispatcher.instance.onError = (Object error, StackTrace stack) {
      final message = error.toString();
      final isFontError = message.contains('Failed to load font') ||
          message.contains('fonts.gstatic.com');
      if (isFontError) {
        if (kDebugMode) {
          debugPrint('CrashlyticsService: Handled non-fatal async font error: $message');
        }
        FirebaseCrashlytics.instance.recordError(
          error,
          stack,
          reason: 'Non-fatal async font download failure',
          fatal: false,
        );
        return true; // Mark as handled so the isolate does not terminate
      }
      FirebaseCrashlytics.instance.recordError(error, stack, fatal: true);
      return true;
    };
  }

  /// Records a caught exception or error safely.
  /// Suitable for use within `try ... catch` blocks.
  Future<void> recordError(
    dynamic exception,
    StackTrace? stack, {
    String? reason,
    Iterable<Object> information = const [],
    bool fatal = false,
  }) async {
    try {
      await FirebaseCrashlytics.instance.recordError(
        exception,
        stack,
        reason: reason,
        information: information,
        fatal: fatal,
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService failed to record error: $e');
      }
    }
  }

  /// Appends a log message to the Crashlytics session buffer.
  /// Automatically filters out obvious sensitive substrings.
  Future<void> log(String message) async {
    if (_sensitivePattern.hasMatch(message)) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService: Blocked sensitive log entry');
      }
      return;
    }

    try {
      await FirebaseCrashlytics.instance.log(message);
    } catch (e) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService log failed: $e');
      }
    }
  }

  /// Sets a custom key-value pair for additional debugging context.
  /// Automatically rejects sensitive keys (e.g., token, password, otp).
  Future<void> setCustomKey(String key, Object value) async {
    if (_sensitivePattern.hasMatch(key)) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService: Blocked sensitive key: $key');
      }
      return;
    }

    try {
      await FirebaseCrashlytics.instance.setCustomKey(key, value);
    } catch (e) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService setCustomKey failed: $e');
      }
    }
  }

  /// Associates the current session with a non-sensitive user ID (e.g. numeric ID).
  /// Pass null or empty string upon logout to clear.
  Future<void> setUserId(String? userId) async {
    try {
      if (userId == null || userId.isEmpty) {
        await FirebaseCrashlytics.instance.setUserIdentifier('');
      } else {
        await FirebaseCrashlytics.instance.setUserIdentifier(userId);
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('CrashlyticsService setUserId failed: $e');
      }
    }
  }

  /// Safe test report: sends a non-fatal test exception to Firebase Crashlytics.
  /// This allows verifying Firebase Console setup WITHOUT crashing the application.
  Future<void> sendTestNonFatalReport() async {
    await log('Crashlytics test verification started');
    await setCustomKey('test_verification', true);
    await setCustomKey('timestamp', DateTime.now().toIso8601String());

    await recordError(
      Exception('Store App Crashlytics Verification (Non-Fatal Test)'),
      StackTrace.current,
      reason: 'Manual verification of Firebase Crashlytics reporting in Store App',
      fatal: false,
    );
  }

  /// Triggers a test crash.
  /// NOTE: This will terminate the app process if invoked! Use only for controlled tests.
  void testFatalCrash() {
    FirebaseCrashlytics.instance.crash();
  }
}
