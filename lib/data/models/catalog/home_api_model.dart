import 'package:equatable/equatable.dart';
import 'brand_api_model.dart';
import 'collection_model.dart';
import 'product_list_item_model.dart';

/// Allowlisted server-driven home section types.
abstract final class HomeSectionTypes {
  static const banners = 'banners';
  static const categories = 'categories';
  static const brands = 'brands';
  static const bestSellers = 'best_sellers';
  static const newProducts = 'new_products';
  static const exclusiveProducts = 'exclusive_products';
  static const collection = 'collection';
  static const recentlyViewed = 'recently_viewed';

  static const allowlist = {
    banners,
    categories,
    brands,
    bestSellers,
    newProducts,
    exclusiveProducts,
    collection,
    recentlyViewed,
  };
}

class HomeSectionModel extends Equatable {
  final String type;
  final String titleAr;
  final String? titleEn;
  final Map<String, dynamic> configuration;
  final dynamic data;

  const HomeSectionModel({
    required this.type,
    required this.titleAr,
    this.titleEn,
    this.configuration = const {},
    this.data,
  });

  String get displayTitle =>
      titleAr.isNotEmpty ? titleAr : (titleEn ?? '');

  bool get isSupported => HomeSectionTypes.allowlist.contains(type);

  List<ProductListItemModel> get productItems {
    if (data is! List) return const [];
    return (data as List)
        .whereType<Map<String, dynamic>>()
        .map(ProductListItemModel.fromJson)
        .toList();
  }

  List<HomeBannerModel> get bannerItems {
    if (data is! List) return const [];
    return (data as List)
        .whereType<Map<String, dynamic>>()
        .map(HomeBannerModel.fromJson)
        .toList();
  }

  List<HomeCategorySummary> get categoryItems {
    if (data is! List) return const [];
    return (data as List)
        .whereType<Map<String, dynamic>>()
        .map(HomeCategorySummary.fromJson)
        .toList();
  }

  List<BrandApiModel> get brandItems {
    if (data is! List) return const [];
    return (data as List)
        .whereType<Map<String, dynamic>>()
        .map(BrandApiModel.fromJson)
        .toList();
  }

  CollectionSummaryModel? get collectionSummary {
    if (data is! Map<String, dynamic>) return null;
    final map = data as Map<String, dynamic>;
    return CollectionSummaryModel(
      id: map['id'] as int? ?? 0,
      nameAr: map['name_ar'] as String? ?? '',
      nameEn: map['name_en'] as String? ?? '',
      slug: map['slug'] as String? ?? '',
      coverUrl: map['cover_url'] as String?,
    );
  }

  List<ProductListItemModel> get collectionProducts {
    if (data is! Map<String, dynamic>) return const [];
    final raw = (data as Map<String, dynamic>)['products'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(ProductListItemModel.fromJson)
        .toList();
  }

  factory HomeSectionModel.fromJson(Map<String, dynamic> json) {
    return HomeSectionModel(
      type: json['type'] as String? ?? '',
      titleAr: json['title_ar'] as String? ?? '',
      titleEn: json['title_en'] as String?,
      configuration: json['configuration'] is Map<String, dynamic>
          ? json['configuration'] as Map<String, dynamic>
          : const {},
      data: json['data'],
    );
  }

  @override
  List<Object?> get props => [type, titleAr, titleEn, configuration, data];
}

class HomeBannerDestination extends Equatable {
  final String type; // product, category, promotion
  final int id;
  final String? nameAr;
  final String? slug;

  const HomeBannerDestination({
    required this.type,
    required this.id,
    this.nameAr,
    this.slug,
  });

  factory HomeBannerDestination.fromJson(Map<String, dynamic> json) {
    final parsedId = json['id'] as int? ??
        int.tryParse(json['id']?.toString() ?? '') ??
        int.tryParse(json['target']?.toString() ?? '') ??
        0;
    return HomeBannerDestination(
      type: json['type'] as String? ?? 'none',
      id: parsedId,
      nameAr: (json['name_ar'] ?? json['name']) as String?,
      slug: json['slug'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'type': type,
        'id': id,
        if (nameAr != null) 'name_ar': nameAr,
        if (slug != null) 'slug': slug,
      };

  @override
  List<Object?> get props => [type, id, nameAr, slug];
}

class HomeBannerModel extends Equatable {
  final int id;
  final String titleAr;
  final String titleEn;
  final String subtitleAr;
  final String subtitleEn;
  final String imageUrl;
  final int sortOrder;
  final String actionType;
  final String? actionReference;
  final HomeBannerDestination? destination;

  const HomeBannerModel({
    required this.id,
    required this.titleAr,
    required this.titleEn,
    required this.subtitleAr,
    required this.subtitleEn,
    required this.imageUrl,
    required this.sortOrder,
    required this.actionType,
    this.actionReference,
    this.destination,
  });

  String get displayTitle => titleAr.isNotEmpty ? titleAr : titleEn;
  String get displaySubtitle => subtitleAr.isNotEmpty ? subtitleAr : subtitleEn;
  String get badgeText => 'مجموعة مميزة';
  String get displayCta => 'تسوق الآن';
  bool get hasNavigableDestination => destination != null;

  factory HomeBannerModel.fromJson(Map<String, dynamic> json) {
    return HomeBannerModel(
      id: _asInt(json['id']),
      titleAr: _asTrimmedString(json['title_ar']),
      titleEn: _asTrimmedString(json['title_en']),
      subtitleAr: _asTrimmedString(json['subtitle_ar']),
      subtitleEn: _asTrimmedString(json['subtitle_en']),
      imageUrl: _asTrimmedString(json['image_url']),
      sortOrder: _asInt(json['sort_order']),
      actionType: _asTrimmedString(json['action_type'], fallback: 'none'),
      actionReference: json['action_reference']?.toString(),
      destination: json['destination'] is Map<String, dynamic>
          ? HomeBannerDestination.fromJson(
              json['destination'] as Map<String, dynamic>)
          : null,
    );
  }

  static String _asTrimmedString(Object? value, {String fallback = ''}) {
    if (value == null) return fallback;
    final text = value.toString().trim();
    return text.isEmpty ? fallback : text;
  }

  static int _asInt(Object? value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  @override
  List<Object?> get props => [
        id,
        titleAr,
        titleEn,
        subtitleAr,
        subtitleEn,
        imageUrl,
        sortOrder,
        actionType,
        actionReference,
        destination,
      ];
}

class HomeCategorySummary extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final int? parentId;
  final String? imageUrl;

  const HomeCategorySummary({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.parentId,
    this.imageUrl,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;
  bool get isRoot => parentId == null;

  factory HomeCategorySummary.fromJson(Map<String, dynamic> json) {
    return HomeCategorySummary(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      parentId: json['parent_id'] as int?,
      imageUrl: (json['image_url'] ?? json['image']) as String?,
    );
  }

  @override
  List<Object?> get props => [id, nameAr, nameEn, slug, parentId, imageUrl];
}

class HomeSectionsModel extends Equatable {
  final List<ProductListItemModel> bestSellers;
  final List<ProductListItemModel> newArrivals;
  final List<ProductListItemModel> exclusive;

  const HomeSectionsModel({
    this.bestSellers = const [],
    this.newArrivals = const [],
    this.exclusive = const [],
  });

  factory HomeSectionsModel.fromJson(Map<String, dynamic> json) {
    List<ProductListItemModel> parseItems(dynamic raw) {
      if (raw is List) {
        return raw
            .whereType<Map<String, dynamic>>()
            .map((item) => ProductListItemModel.fromJson(item))
            .toList();
      }
      return [];
    }

    return HomeSectionsModel(
      bestSellers: parseItems(json['best_sellers']),
      newArrivals: parseItems(json['new_arrivals']),
      exclusive: parseItems(json['exclusive']),
    );
  }

  @override
  List<Object?> get props => [bestSellers, newArrivals, exclusive];
}

class HomeApiModel extends Equatable {
  final List<HomeBannerModel> banners;
  final List<HomeCategorySummary> featuredCategories;
  final HomeSectionsModel sections;
  final List<HomeSectionModel> homeSections;

  const HomeApiModel({
    this.banners = const [],
    this.featuredCategories = const [],
    this.sections = const HomeSectionsModel(),
    this.homeSections = const [],
  });

  bool get hasServerDrivenSections => homeSections.isNotEmpty;

  factory HomeApiModel.fromJson(Map<String, dynamic> json) {
    var rawBanners = json['banners'];
    List<HomeBannerModel> parsedBanners = [];
    if (rawBanners is List) {
      parsedBanners = rawBanners
          .whereType<Map<String, dynamic>>()
          .map((b) => HomeBannerModel.fromJson(b))
          .toList();
    }

    var rawCats = json['featured_categories'];
    List<HomeCategorySummary> parsedCats = [];
    if (rawCats is List) {
      parsedCats = rawCats
          .whereType<Map<String, dynamic>>()
          .map((c) => HomeCategorySummary.fromJson(c))
          .toList();
    }

    final rawHomeSections = json['home_sections'];
    List<HomeSectionModel> parsedHomeSections = [];
    if (rawHomeSections is List) {
      parsedHomeSections = rawHomeSections
          .whereType<Map<String, dynamic>>()
          .map(HomeSectionModel.fromJson)
          .where((s) => s.isSupported)
          .toList();
    }

    return HomeApiModel(
      banners: parsedBanners,
      featuredCategories: parsedCats,
      sections: json['sections'] is Map<String, dynamic>
          ? HomeSectionsModel.fromJson(json['sections'] as Map<String, dynamic>)
          : const HomeSectionsModel(),
      homeSections: parsedHomeSections,
    );
  }

  @override
  List<Object?> get props =>
      [banners, featuredCategories, sections, homeSections];
}
