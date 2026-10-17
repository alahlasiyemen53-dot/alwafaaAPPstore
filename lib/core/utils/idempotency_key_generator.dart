import 'dart:math';

/// Utility to generate RFC 4122 version 4 UUIDs for request idempotency.
class IdempotencyKeyGenerator {
  IdempotencyKeyGenerator._();

  static final Random _secureRandom = Random.secure();

  /// Generates a standard RFC 4122 UUID v4 string (e.g. 550e8400-e29b-41d4-a716-446655440000).
  static String generate() {
    final values = List<int>.generate(16, (_) => _secureRandom.nextInt(256));

    // Set version to 0100 (v4)
    values[6] = (values[6] & 0x0f) | 0x40;
    // Set variant to 10xx (RFC 4122)
    values[8] = (values[8] & 0x3f) | 0x80;

    final hex = values.map((b) => b.toRadixString(16).padLeft(2, '0')).join();

    return '${hex.substring(0, 8)}-'
        '${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-'
        '${hex.substring(16, 20)}-'
        '${hex.substring(20, 32)}';
  }
}
