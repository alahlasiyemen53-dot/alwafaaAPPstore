import 'package:flutter/foundation.dart';

/// Normalizes Arabic text so search tolerates alef/hamza and similar variants.
@immutable
class ArabicTextNormalizer {
  const ArabicTextNormalizer._();

  static final RegExp _diacritics = RegExp(r'[\u064B-\u065F\u0670]');
  static final RegExp _whitespace = RegExp(r'\s+');

  static String normalize(String? value) {
    if (value == null || value.isEmpty) return '';

    var text = value.trim().toLowerCase();
    text = text.replaceAll('ـ', '');
    text = text.replaceAll(_diacritics, '');
    text = text
        .replaceAll('أ', 'ا')
        .replaceAll('إ', 'ا')
        .replaceAll('آ', 'ا')
        .replaceAll('ٱ', 'ا')
        .replaceAll('ة', 'ه')
        .replaceAll('ى', 'ي');
    text = text.replaceAll(_whitespace, ' ').trim();
    return text;
  }

  static bool containsNormalized(String haystack, String needle) {
    final n = normalize(needle);
    if (n.isEmpty) return true;
    return normalize(haystack).contains(n);
  }
}
