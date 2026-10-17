import 'dart:convert';

import 'package:hive/hive.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/reference/yemen_reference_models.dart';

final class GeoCachePayload<T> {
  final List<T> items;
  final DateTime? maxUpdatedAt;

  const GeoCachePayload({
    required this.items,
    this.maxUpdatedAt,
  });
}

abstract interface class ReferenceLocalDataSource {
  Future<GeoCachePayload<CountryModel>?> readCountries();
  Future<void> saveCountries(List<CountryModel> countries, DateTime? maxUpdatedAt);

  Future<GeoCachePayload<GovernorateModel>?> readGovernoratesByCountry(
    int countryId,
  );
  Future<void> saveGovernoratesByCountry(
    int countryId,
    List<GovernorateModel> governorates,
    DateTime? maxUpdatedAt,
  );

  Future<GeoCachePayload<GovernorateModel>?> readAllGovernorates();
  Future<void> saveAllGovernorates(
    List<GovernorateModel> governorates,
    DateTime? maxUpdatedAt,
  );
}

/// Hive-backed geo cache. Call [ReferenceLocalDataSourceImpl.create] after
/// `Hive.initFlutter()` (or `Hive.init` in tests).
final class ReferenceLocalDataSourceImpl implements ReferenceLocalDataSource {
  static const boxName = 'geo_reference';
  static const _countriesKey = 'geo.countries.v1';
  static const _allGovernoratesKey = 'geo.governorates.all.v1';
  static const _prefsMigratedKey = 'geo.migrated_from_prefs.v1';

  static String _governoratesByCountryKey(int countryId) =>
      'geo.governorates.country.$countryId.v1';

  final Box<String> _box;

  ReferenceLocalDataSourceImpl(this._box);

  /// Opens the Hive box and migrates legacy SharedPreferences payloads once.
  static Future<ReferenceLocalDataSourceImpl> create({
    Box<String>? box,
  }) async {
    final opened = box ??
        (Hive.isBoxOpen(boxName)
            ? Hive.box<String>(boxName)
            : await Hive.openBox<String>(boxName));
    final impl = ReferenceLocalDataSourceImpl(opened);
    await impl._migrateFromSharedPreferencesIfNeeded();
    return impl;
  }

  Future<void> _migrateFromSharedPreferencesIfNeeded() async {
    if (_box.get(_prefsMigratedKey) == '1') return;

    final prefs = await SharedPreferences.getInstance();
    final keys = <String>[
      _countriesKey,
      _allGovernoratesKey,
      ...prefs.getKeys().where(
            (k) => k.startsWith('geo.governorates.country.') && k.endsWith('.v1'),
          ),
    ];

    for (final key in keys.toSet()) {
      final raw = prefs.getString(key);
      if (raw == null || raw.isEmpty) continue;
      if (!_box.containsKey(key)) {
        await _box.put(key, raw);
      }
      await prefs.remove(key);
    }

    await _box.put(_prefsMigratedKey, '1');
  }

  @override
  Future<GeoCachePayload<CountryModel>?> readCountries() async {
    return _read(_countriesKey, CountryModel.fromJson);
  }

  @override
  Future<void> saveCountries(
    List<CountryModel> countries,
    DateTime? maxUpdatedAt,
  ) async {
    await _write(
      _countriesKey,
      countries.map((c) => c.toJson()).toList(),
      maxUpdatedAt,
    );
  }

  @override
  Future<GeoCachePayload<GovernorateModel>?> readGovernoratesByCountry(
    int countryId,
  ) async {
    return _read(
      _governoratesByCountryKey(countryId),
      GovernorateModel.fromJson,
    );
  }

  @override
  Future<void> saveGovernoratesByCountry(
    int countryId,
    List<GovernorateModel> governorates,
    DateTime? maxUpdatedAt,
  ) async {
    await _write(
      _governoratesByCountryKey(countryId),
      governorates.map((g) => g.toJson()).toList(),
      maxUpdatedAt,
    );
  }

  @override
  Future<GeoCachePayload<GovernorateModel>?> readAllGovernorates() async {
    return _read(_allGovernoratesKey, GovernorateModel.fromJson);
  }

  @override
  Future<void> saveAllGovernorates(
    List<GovernorateModel> governorates,
    DateTime? maxUpdatedAt,
  ) async {
    await _write(
      _allGovernoratesKey,
      governorates.map((g) => g.toJson()).toList(),
      maxUpdatedAt,
    );
  }

  Future<GeoCachePayload<T>?> _read<T>(
    String key,
    T Function(Map<String, dynamic>) fromJson,
  ) async {
    final raw = _box.get(key);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final map = Map<String, dynamic>.from(decoded);
      final itemsRaw = map['items'];
      if (itemsRaw is! List) return null;
      final items = itemsRaw
          .whereType<Map>()
          .map((e) => fromJson(Map<String, dynamic>.from(e)))
          .toList();
      final maxUpdatedAt = DateTime.tryParse(
        map['max_updated_at'] as String? ?? '',
      );
      return GeoCachePayload<T>(items: items, maxUpdatedAt: maxUpdatedAt);
    } catch (_) {
      return null;
    }
  }

  Future<void> _write(
    String key,
    List<Map<String, dynamic>> items,
    DateTime? maxUpdatedAt,
  ) async {
    await _box.put(
      key,
      jsonEncode({
        'items': items,
        'max_updated_at': maxUpdatedAt?.toUtc().toIso8601String(),
      }),
    );
  }
}
