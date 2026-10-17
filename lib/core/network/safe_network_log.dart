final class SafeNetworkLog {
  SafeNetworkLog._();

  static Map<String, Object?> redact(Map<String, Object?> values) => {
        for (final entry in values.entries)
          entry.key: _isSensitive(entry.key) ? '[REDACTED]' : entry.value,
      };

  static bool _isSensitive(String key) {
    final normalized = key.toLowerCase();
    return normalized == 'authorization' ||
        normalized.contains('token') ||
        normalized.contains('jwt') ||
        normalized.contains('otp') ||
        normalized.contains('code') ||
        normalized.contains('challenge') ||
        normalized.contains('password') ||
        normalized.contains('secret') ||
        normalized.contains('phone') ||
        normalized.contains('address');
  }
}
