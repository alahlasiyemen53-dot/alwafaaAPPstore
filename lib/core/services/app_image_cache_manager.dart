import 'package:flutter_cache_manager/flutter_cache_manager.dart';

/// Cache for public catalog media only; customer data never enters this store.
final class AppImageCacheManager {
  static const cacheKey = 'storeapp-public-media-v1';
  static const stalePeriod = Duration(days: 21);
  static const maxCacheObjects = 250;

  static final CacheManager instance = CacheManager(
    Config(
      cacheKey,
      stalePeriod: stalePeriod,
      maxNrOfCacheObjects: maxCacheObjects,
    ),
  );

  static Future<void> clear() => instance.emptyCache();
}
