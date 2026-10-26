import '../datasources/reference_local_datasource.dart';
import '../datasources/reference_remote_datasource.dart';
import '../models/currency_model.dart';
import '../models/delivery/sanaa_delivery_zone_model.dart';
import '../models/reference/yemen_reference_models.dart';

abstract interface class ReferenceRepository {
  Future<List<CountryModel>> peekCachedCountries();
  Future<List<CountryModel>> getCountries();
  Future<List<GovernorateModel>> peekCachedGovernoratesByCountry(int countryId);
  Future<List<GovernorateModel>> getGovernoratesByCountry(int countryId);
  Future<List<GovernorateModel>> getGovernorates();
  Future<List<CurrencyModel>> getCurrencies();
  Future<SanaaDeliveryZoneModel> getSanaaDeliveryZone();

  /// Prefetch countries + default (YE) governorates into local cache.
  /// Bounded so Splash is not blocked by a slow network.
  Future<void> ensureGeoWarmed({
    Duration timeout = const Duration(seconds: 4),
  });
}

final class ReferenceRepositoryImpl implements ReferenceRepository {
  final ReferenceRemoteDataSource _remoteDataSource;
  final ReferenceLocalDataSource _localDataSource;

  ReferenceRepositoryImpl({
    required this._localDataSource,
    ReferenceRemoteDataSource? remoteDataSource,
  }) : _remoteDataSource = remoteDataSource ?? ReferenceRemoteDataSourceImpl();

  @override
  Future<List<CountryModel>> peekCachedCountries() async {
    final cached = await _localDataSource.readCountries();
    return cached?.items ?? const [];
  }

  @override
  Future<List<CountryModel>> getCountries() async {
    final cached = await _localDataSource.readCountries();
    try {
      final remote = await _remoteDataSource.getCountries();
      final remoteMax = _maxUpdatedAt(remote.map((e) => e.updatedAt));
      final shouldUpdate = cached == null ||
          _isRemoteNewer(cached.maxUpdatedAt, remoteMax) ||
          cached.items.length != remote.length;
      if (shouldUpdate) {
        await _localDataSource.saveCountries(remote, remoteMax);
      }
      return remote;
    } catch (_) {
      if (cached != null && cached.items.isNotEmpty) {
        return cached.items;
      }
      rethrow;
    }
  }

  @override
  Future<List<GovernorateModel>> peekCachedGovernoratesByCountry(
    int countryId,
  ) async {
    final cached = await _localDataSource.readGovernoratesByCountry(countryId);
    return cached?.items ?? const [];
  }

  @override
  Future<List<GovernorateModel>> getGovernoratesByCountry(int countryId) async {
    final cached = await _localDataSource.readGovernoratesByCountry(countryId);
    try {
      final remote = await _remoteDataSource.getGovernoratesByCountry(countryId);
      final remoteMax = _maxUpdatedAt(remote.map((e) => e.updatedAt));
      final shouldUpdate = cached == null ||
          _isRemoteNewer(cached.maxUpdatedAt, remoteMax) ||
          cached.items.length != remote.length;
      if (shouldUpdate) {
        await _localDataSource.saveGovernoratesByCountry(
          countryId,
          remote,
          remoteMax,
        );
      }
      return remote;
    } catch (_) {
      if (cached != null && cached.items.isNotEmpty) {
        return cached.items;
      }
      rethrow;
    }
  }

  @override
  Future<List<GovernorateModel>> getGovernorates() async {
    final cached = await _localDataSource.readAllGovernorates();
    try {
      final remote = await _remoteDataSource.getGovernorates();
      final remoteMax = _maxUpdatedAt(remote.map((e) => e.updatedAt));
      final shouldUpdate = cached == null ||
          _isRemoteNewer(cached.maxUpdatedAt, remoteMax) ||
          cached.items.length != remote.length;
      if (shouldUpdate) {
        await _localDataSource.saveAllGovernorates(remote, remoteMax);
      }
      return remote;
    } catch (_) {
      if (cached != null && cached.items.isNotEmpty) {
        return cached.items;
      }
      rethrow;
    }
  }

  @override
  Future<List<CurrencyModel>> getCurrencies() =>
      _remoteDataSource.getCurrencies();

  @override
  Future<SanaaDeliveryZoneModel> getSanaaDeliveryZone() =>
      _remoteDataSource.getSanaaDeliveryZone();

  @override
  Future<void> ensureGeoWarmed({
    Duration timeout = const Duration(seconds: 4),
  }) async {
    try {
      await getCountries().timeout(timeout);
    } catch (_) {
      // Cache / offline fallback is enough for UI.
    }

    final countries = await peekCachedCountries();
    if (countries.isEmpty) return;

    CountryModel? yemen;
    for (final c in countries) {
      if (c.isoCode.toUpperCase() == 'YE') {
        yemen = c;
        break;
      }
    }
    final defaultCountry = yemen ?? countries.first;

    try {
      await getGovernoratesByCountry(defaultCountry.id).timeout(timeout);
    } catch (_) {
      // Governorates will load lazily on address/auth screens.
    }
  }

  DateTime? _maxUpdatedAt(Iterable<DateTime?> values) {
    DateTime? max;
    for (final value in values) {
      if (value == null) continue;
      if (max == null || value.isAfter(max)) {
        max = value;
      }
    }
    return max;
  }

  bool _isRemoteNewer(DateTime? cachedMax, DateTime? remoteMax) {
    if (cachedMax == null) return true;
    if (remoteMax == null) return false;
    return remoteMax.isAfter(cachedMax);
  }
}
