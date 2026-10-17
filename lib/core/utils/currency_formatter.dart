import 'package:intl/intl.dart';

/// CurrencyFormatter formats prices dynamically based on currency symbol and decimal places.
/// Backend remains sole truth for money amounts; Flutter does not apply competing local FX engines.
class CurrencyFormatter {
  CurrencyFormatter._();

  /// Prefer plain Arabic labels that render with Cairo (avoids ﷼ glyph font fallback).
  static String resolveDisplaySymbol({
    String? symbol,
    String? code,
  }) {
    final upper = (code ?? '').trim().toUpperCase();
    if (upper == 'YER' || upper == 'YR') return 'ر.ي';
    if (upper == 'SAR') return 'ر.س';

    final normalized = normalizeSymbol(symbol ?? '');
    if (normalized.isEmpty) return upper == 'USD' ? '\$' : 'ر.ي';
    return normalized;
  }

  /// Maps exotic Unicode currency glyphs (e.g. ﷼) to plain Arabic that
  /// renders with the app font (Cairo) instead of a system fallback face.
  static String normalizeSymbol(String symbol) {
    final trimmed = symbol.trim();
    if (trimmed.isEmpty) return trimmed;

    var cleaned = trimmed
        // Arabic Rial Sign U+FDFC — often falls back to a mismatched font
        .replaceAll('\uFDFC', 'ر.ي')
        .replaceAll('﷼', 'ر.ي')
        // Newer Saudi Riyal symbol (if present in data)
        .replaceAll('\u20C1', 'ر.س')
        .trim();

    // Single exotic glyph still present → force Yemeni plain label
    if (cleaned.length == 1) {
      final code = cleaned.codeUnitAt(0);
      if (code == 0xFDFC || code > 0x2000) {
        return 'ر.ي';
      }
    }

    return cleaned;
  }

  static String format(
    num price, {
    required String symbol,
    String? code,
    int decimalDigits = 0,
  }) {
    final digits = price < 10 && price % 1 != 0 ? 2 : decimalDigits;
    final pattern = digits > 0 ? '#,##0.${'0' * digits}' : '#,##0';
    final number = NumberFormat(pattern).format(price);
    final cleanSymbol = resolveDisplaySymbol(symbol: symbol, code: code);
    if (cleanSymbol.isEmpty) return number;
    return '$number $cleanSymbol';
  }
}
