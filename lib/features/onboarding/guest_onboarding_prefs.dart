import 'package:shared_preferences/shared_preferences.dart';

/// Persists first-run guest onboarding dismissal.
abstract final class GuestOnboardingPrefs {
  static const key = 'guest_onboarding_seen_v1';

  static Future<bool> hasSeen() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(key) ?? false;
  }

  static Future<void> markSeen() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(key, true);
  }
}
