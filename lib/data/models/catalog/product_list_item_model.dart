import 'package:equatable/equatable.dart';

class ProductBrandSummary extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final String? logoUrl;

  const ProductBrandSummary({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.logoUrl,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory ProductBrandSummary.fromJson(Map<String, dynamic> json) {
    final logo = json['logo'];
    return ProductBrandSummary(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      logoUrl: logo is Map<String, dynamic> ? logo['url'] as String? : null,
    );
  }

  @override
  List<Object?> get props => [id, nameAr, nameEn, slug, logoUrl];
}

class ProductCategorySummary extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;

  const ProductCategorySummary({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory ProductCategorySummary.fromJson(Map<String, dynamic> json) {
    return ProductCategorySummary(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
    );
  }

  @override
  List<Object?> get props => [id, nameAr, nameEn, slug];
}

class ProductPriceSummary extends Equatable {
  final double minPrice;
  final double maxPrice;
  final String currency;
  final bool hasRange;

  const ProductPriceSummary({
    required this.minPrice,
    required this.maxPrice,
    required this.currency,
    required this.hasRange,
  });

  factory ProductPriceSummary.fromJson(Map<String, dynamic> json) {
    return ProductPriceSummary(
      minPrice: double.tryParse(json['min_price']?.toString() ?? '0') ?? 0.0,
      maxPrice: double.tryParse(json['max_price']?.toString() ?? '0') ?? 0.0,
      currency: json['currency'] as String? ?? 'YER',
      hasRange: json['has_range'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [minPrice, maxPrice, currency, hasRange];
}

class ProductAvailabilitySummary extends Equatable {
  final bool isAvailable;
  final String status; // in_stock, on_demand, out_of_stock

  const ProductAvailabilitySummary({
    required this.isAvailable,
    required this.status,
  });

  bool get isInStock => status == 'in_stock';
  bool get isOnDemand => status == 'on_demand';
  bool get isOutOfStock => status == 'out_of_stock' || !isAvailable;

  factory ProductAvailabilitySummary.fromJson(Map<String, dynamic> json) {
    return ProductAvailabilitySummary(
      isAvailable: json['is_available'] as bool? ?? false,
      status: json['status'] as String? ?? 'out_of_stock',
    );
  }

  @override
  List<Object?> get props => [isAvailable, status];
}

class ProductRatingSummary extends Equatable {
  final double average;
  final int reviewsCount;

  const ProductRatingSummary({
    required this.average,
    required this.reviewsCount,
  });

  factory ProductRatingSummary.fromJson(Map<String, dynamic> json) {
    return ProductRatingSummary(
      average: (json['average'] as num?)?.toDouble() ?? 0.0,
      reviewsCount: json['reviews_count'] as int? ?? 0,
    );
  }

  @override
  List<Object?> get props => [average, reviewsCount];
}

class ProductBadges extends Equatable {
  final bool isBestSeller;
  final bool isNew;
  final bool isExclusive;

  const ProductBadges({
    required this.isBestSeller,
    required this.isNew,
    required this.isExclusive,
  });

  factory ProductBadges.fromJson(Map<String, dynamic> json) {
    return ProductBadges(
      isBestSeller: json['is_best_seller'] as bool? ?? false,
      isNew: json['is_new'] as bool? ?? false,
      isExclusive: json['is_exclusive'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [isBestSeller, isNew, isExclusive];
}

class ProductListItemModel extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final ProductBrandSummary? brand;
  final ProductCategorySummary? category;
  final String? primaryImage;
  final int? defaultVariantId;
  final ProductPriceSummary priceSummary;
  final ProductAvailabilitySummary availability;
  final ProductRatingSummary rating;
  final ProductBadges badges;

  const ProductListItemModel({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.brand,
    this.category,
    this.primaryImage,
    this.defaultVariantId,
    required this.priceSummary,
    required this.availability,
    required this.rating,
    required this.badges,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory ProductListItemModel.fromJson(Map<String, dynamic> json) {
    final rawVariant = json['default_variant_id'];
    final parsedVariant = rawVariant is int
        ? rawVariant
        : int.tryParse(rawVariant?.toString() ?? '');

    return ProductListItemModel(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      brand: json['brand'] != null ? ProductBrandSummary.fromJson(json['brand'] as Map<String, dynamic>) : null,
      category: json['category'] != null ? ProductCategorySummary.fromJson(json['category'] as Map<String, dynamic>) : null,
      primaryImage: json['primary_image'] as String?,
      defaultVariantId: parsedVariant != null && parsedVariant > 0
          ? parsedVariant
          : null,
      priceSummary: ProductPriceSummary.fromJson(json['price_summary'] as Map<String, dynamic>? ?? {}),
      availability: ProductAvailabilitySummary.fromJson(json['availability'] as Map<String, dynamic>? ?? {}),
      rating: ProductRatingSummary.fromJson(json['rating'] as Map<String, dynamic>? ?? {}),
      badges: ProductBadges.fromJson(json['badges'] as Map<String, dynamic>? ?? {}),
    );
  }

  @override
  List<Object?> get props => [
        id,
        nameAr,
        nameEn,
        slug,
        brand,
        category,
        primaryImage,
        defaultVariantId,
        priceSummary,
        availability,
        rating,
        badges,
      ];
}

class PaginatedProductsResponse extends Equatable {
  final List<ProductListItemModel> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const PaginatedProductsResponse({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  factory PaginatedProductsResponse.fromJson(Map<String, dynamic> json) {
    var rawItems = json['items'];
    List<ProductListItemModel> parsedItems = [];
    if (rawItems is List) {
      parsedItems = rawItems
          .whereType<Map<String, dynamic>>()
          .map((item) => ProductListItemModel.fromJson(item))
          .toList();
    }

    final pagination = json['pagination'] as Map<String, dynamic>? ?? {};

    return PaginatedProductsResponse(
      items: parsedItems,
      currentPage: pagination['current_page'] as int? ?? 1,
      perPage: pagination['per_page'] as int? ?? 15,
      total: pagination['total'] as int? ?? parsedItems.length,
      lastPage: pagination['last_page'] as int? ?? 1,
      hasMore: pagination['has_more'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [items, currentPage, perPage, total, lastPage, hasMore];
}
