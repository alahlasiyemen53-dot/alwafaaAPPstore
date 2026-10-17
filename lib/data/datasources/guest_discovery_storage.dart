import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

final class GuestRecentlyViewedEntry {
  final int productId;
  final String name;
  final String imageUrl;
  final double displayPrice;
  final DateTime viewedAt;

  const GuestRecentlyViewedEntry({
    required this.productId,
    required this.name,
    required this.imageUrl,
    required this.displayPrice,
    required this.viewedAt,
  });

  Map<String, dynamic> toJson() => {
        'product_id': productId,
        'name': name,
        'image_url': imageUrl,
        'display_price': displayPrice,
        'viewed_at': viewedAt.toIso8601String(),
      };

  factory GuestRecentlyViewedEntry.fromJson(Map<String, dynamic> json) =>
      GuestRecentlyViewedEntry(
        productId: json['product_id'] as int? ?? 0,
        name: json['name'] as String? ?? '',
        imageUrl:
            json['image_url'] as String? ?? 'assets/images/placeholder.png',
        displayPrice: (json['display_price'] as num?)?.toDouble() ?? 0,
        viewedAt: DateTime.tryParse(json['viewed_at'] as String? ?? '') ??
            DateTime.now(),
      );
}

final class GuestSearchHistoryEntry {
  final String query;
  final DateTime? lastSearchedAt;

  const GuestSearchHistoryEntry({
    required this.query,
    this.lastSearchedAt,
  });

  Map<String, dynamic> toJson() => {
        'query': query,
        'last_searched_at': lastSearchedAt?.toIso8601String(),
      };

  factory GuestSearchHistoryEntry.fromJson(Map<String, dynamic> json) =>
      GuestSearchHistoryEntry(
        query: json['query'] as String? ?? '',
        lastSearchedAt:
            DateTime.tryParse(json['last_searched_at'] as String? ?? ''),
      );
}

/// Local bounded history for guest recently-viewed + search history.
final class SharedPreferencesGuestDiscoveryStorage {
  static const _recentKey = 'guest_recently_viewed_entries_v1';
  static const _searchKey = 'guest_search_history_entries_v1';
  static const recentLimit = 20;
  static const searchLimit = 15;

  Future<List<GuestRecentlyViewedEntry>> recentlyViewedEntries() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(_recentKey) ?? const [];
    return raw
        .map((item) {
          try {
            final decoded = jsonDecode(item);
            if (decoded is Map<String, dynamic>) {
              return GuestRecentlyViewedEntry.fromJson(decoded);
            }
          } catch (_) {}
          return null;
        })
        .whereType<GuestRecentlyViewedEntry>()
        .where((e) => e.productId > 0)
        .toList();
  }

  Future<void> saveRecentlyViewedEntries(
    List<GuestRecentlyViewedEntry> entries,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    final bounded =
        entries.take(recentLimit).map((e) => jsonEncode(e.toJson())).toList();
    await prefs.setStringList(_recentKey, bounded);
  }

  Future<void> recordRecentlyViewed(GuestRecentlyViewedEntry entry) async {
    if (entry.productId <= 0) return;
    final entries = await recentlyViewedEntries();
    entries.removeWhere((e) => e.productId == entry.productId);
    entries.insert(0, entry);
    await saveRecentlyViewedEntries(entries);
  }

  Future<List<GuestSearchHistoryEntry>> searchHistoryEntries() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(_searchKey) ?? const [];
    return raw
        .map((item) {
          try {
            final decoded = jsonDecode(item);
            if (decoded is Map<String, dynamic>) {
              return GuestSearchHistoryEntry.fromJson(decoded);
            }
            if (item.trim().isNotEmpty) {
              return GuestSearchHistoryEntry(query: item.trim());
            }
          } catch (_) {
            if (item.trim().isNotEmpty) {
              return GuestSearchHistoryEntry(query: item.trim());
            }
          }
          return null;
        })
        .whereType<GuestSearchHistoryEntry>()
        .where((e) => e.query.trim().isNotEmpty)
        .toList();
  }

  Future<void> saveSearchHistoryEntries(
    List<GuestSearchHistoryEntry> entries,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    final bounded =
        entries.take(searchLimit).map((e) => jsonEncode(e.toJson())).toList();
    await prefs.setStringList(_searchKey, bounded);
  }

  Future<void> recordSearchQuery(String query) async {
    final normalized = query.trim().replaceAll(RegExp(r'\s+'), ' ');
    if (normalized.length < 2) return;
    final entries = await searchHistoryEntries();
    entries.removeWhere(
      (e) => e.query.toLowerCase() == normalized.toLowerCase(),
    );
    entries.insert(
      0,
      GuestSearchHistoryEntry(
        query: normalized,
        lastSearchedAt: DateTime.now(),
      ),
    );
    await saveSearchHistoryEntries(entries);
  }
}
