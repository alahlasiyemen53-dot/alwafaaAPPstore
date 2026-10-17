import 'package:equatable/equatable.dart';
import 'product_list_item_model.dart';

class ProductMediaItem extends Equatable {
  final int id;
  final String url;
  final String thumb;
  final String small;
  final String medium;
  final String large;
  final bool isPrimary;

  const ProductMediaItem({
    required this.id,
    required this.url,
    required this.thumb,
    required this.small,
    required this.medium,
    required this.large,
    required this.isPrimary,
  });

  factory ProductMediaItem.fromJson(Map<String, dynamic> json) {
    return ProductMediaItem(
      id: json['id'] as int? ?? 0,
      url: json['url'] as String? ?? '',
      thumb: json['thumb'] as String? ?? json['url'] as String? ?? '',
      small: json['small'] as String? ?? json['url'] as String? ?? '',
      medium: json['medium'] as String? ?? json['url'] as String? ?? '',
      large: json['large'] as String? ?? json['url'] as String? ?? '',
      isPrimary: json['is_primary'] as bool? ?? false,
    );
  }

  String get largeUrl {
    if (large.isNotEmpty) return large;
    if (medium.isNotEmpty) return medium;
    return optimizedUrl;
  }

  String get optimizedUrl {
    // Prefer `small` so detail gallery reuses the same cached URL as catalog
    // list `primary_image` (also served as the small conversion).
    if (small.isNotEmpty) return small;
    if (medium.isNotEmpty) return medium;
    if (large.isNotEmpty) return large;
    return url;
  }

  @override
  List<Object?> get props => [id, url, thumb, small, medium, large, isPrimary];
}

class ProductVariantAttributeValue extends Equatable {
  final int attributeId;
  final String nameAr;
  final String nameEn;
  final String code;
  final String valueAr;
  final String valueEn;

  const ProductVariantAttributeValue({
    required this.attributeId,
    required this.nameAr,
    required this.nameEn,
    required this.code,
    required this.valueAr,
    required this.valueEn,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;
  String get displayValue => valueAr.isNotEmpty ? valueAr : valueEn;

  factory ProductVariantAttributeValue.fromJson(Map<String, dynamic> json) {
    return ProductVariantAttributeValue(
      attributeId: json['attribute_id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      code: json['code'] as String? ?? '',
      valueAr: json['value_ar'] as String? ?? '',
      valueEn: json['value_en'] as String? ?? '',
    );
  }

  @override
  List<Object?> get props =>
      [attributeId, nameAr, nameEn, code, valueAr, valueEn];
}

class VariantPriceModel extends Equatable {
  final double amount;
  final double? compareAt;
  final String currency;
  final bool hasDiscount;
  final double? discountPercentage;

  const VariantPriceModel({
    required this.amount,
    this.compareAt,
    required this.currency,
    required this.hasDiscount,
    this.discountPercentage,
  });

  factory VariantPriceModel.fromJson(Map<String, dynamic> json) {
    return VariantPriceModel(
      amount: double.tryParse(json['amount']?.toString() ?? '0') ?? 0.0,
      compareAt: json['compare_at'] != null
          ? double.tryParse(json['compare_at'].toString())
          : null,
      currency: json['currency'] as String? ?? 'YER',
      hasDiscount: json['has_discount'] as bool? ?? false,
      discountPercentage: (json['discount_percentage'] as num?)?.toDouble(),
    );
  }

  @override
  List<Object?> get props =>
      [amount, compareAt, currency, hasDiscount, discountPercentage];
}

class ProductVariantApiModel extends Equatable {
  final int id;
  final String sku;
  final bool isDefault;
  final String fulfillmentMode;
  final ProductAvailabilitySummary availability;
  final VariantPriceModel price;
  final List<ProductVariantAttributeValue> attributes;
  final List<ProductMediaItem> images;

  const ProductVariantApiModel({
    required this.id,
    required this.sku,
    required this.isDefault,
    required this.fulfillmentMode,
    required this.availability,
    required this.price,
    required this.attributes,
    required this.images,
  });

  factory ProductVariantApiModel.fromJson(Map<String, dynamic> json) {
    var rawAttrs = json['attributes'];
    List<ProductVariantAttributeValue> parsedAttrs = [];
    if (rawAttrs is List) {
      parsedAttrs = rawAttrs
          .whereType<Map<String, dynamic>>()
          .map((a) => ProductVariantAttributeValue.fromJson(a))
          .toList();
    }

    var rawImgs = json['images'];
    List<ProductMediaItem> parsedImgs = [];
    if (rawImgs is List) {
      parsedImgs = rawImgs
          .whereType<Map<String, dynamic>>()
          .map((i) => ProductMediaItem.fromJson(i))
          .toList();
    }

    return ProductVariantApiModel(
      id: json['id'] as int? ?? 0,
      sku: json['sku'] as String? ?? '',
      isDefault: json['is_default'] as bool? ?? false,
      fulfillmentMode: json['fulfillment_mode'] as String? ?? 'stocked',
      availability: ProductAvailabilitySummary.fromJson(
          json['availability'] as Map<String, dynamic>? ?? {}),
      price: VariantPriceModel.fromJson(
          json['price'] as Map<String, dynamic>? ?? {}),
      attributes: parsedAttrs,
      images: parsedImgs,
    );
  }

  @override
  List<Object?> get props => [
        id,
        sku,
        isDefault,
        fulfillmentMode,
        availability,
        price,
        attributes,
        images
      ];
}

class ProductDetailApiModel extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final String? descriptionAr;
  final String? descriptionEn;
  final ProductBrandSummary? brand;
  final ProductCategorySummary? category;
  final List<ProductMediaItem> mediaGallery;
  final ProductRatingSummary ratingSummary;
  final Map<int, int> ratingDistribution;
  final ProductBadges badges;
  final List<ProductVariantAttributeValue> attributes;
  final List<ProductVariantApiModel> variants;

  const ProductDetailApiModel({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.descriptionAr,
    this.descriptionEn,
    this.brand,
    this.category,
    required this.mediaGallery,
    required this.ratingSummary,
    this.ratingDistribution = const {},
    required this.badges,
    this.attributes = const [],
    required this.variants,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;
  String get displayDescription => descriptionAr?.isNotEmpty == true
      ? descriptionAr!
      : (descriptionEn ?? '');

  ProductVariantApiModel? get defaultVariant {
    if (variants.isEmpty) return null;
    return variants.firstWhere(
      (v) => v.isDefault,
      orElse: () => variants.first,
    );
  }

  /// All unique attributes across the product or its variants.
  List<ProductVariantAttributeValue> get allAttributes {
    if (attributes.isNotEmpty) return attributes;
    final seen = <String>{};
    final list = <ProductVariantAttributeValue>[];
    for (final v in variants) {
      for (final a in v.attributes) {
        if (seen.add('${a.attributeId}_${a.displayValue}')) {
          list.add(a);
        }
      }
    }
    return list;
  }

  factory ProductDetailApiModel.fromJson(Map<String, dynamic> json) {
    var rawGallery = json['media_gallery'];
    List<ProductMediaItem> parsedGallery = [];
    if (rawGallery is List) {
      parsedGallery = rawGallery
          .whereType<Map<String, dynamic>>()
          .map((m) => ProductMediaItem.fromJson(m))
          .toList();
    }

    var rawRating = json['rating_summary'] as Map<String, dynamic>? ?? {};
    final ratingSummary = ProductRatingSummary(
      average: (rawRating['average_rating'] as num?)?.toDouble() ?? 0.0,
      reviewsCount: rawRating['reviews_count'] as int? ?? 0,
    );

    Map<int, int> distribution = {};
    if (rawRating['distribution'] is Map) {
      (rawRating['distribution'] as Map).forEach((k, v) {
        int? star = int.tryParse(k.toString());
        int? count = int.tryParse(v.toString());
        if (star != null && count != null) {
          distribution[star] = count;
        }
      });
    }

    var rawVariants = json['variants'];
    List<ProductVariantApiModel> parsedVariants = [];
    if (rawVariants is List) {
      parsedVariants = rawVariants
          .whereType<Map<String, dynamic>>()
          .map((v) => ProductVariantApiModel.fromJson(v))
          .toList();
    }

    var rawAttrs = json['attributes'];
    List<ProductVariantAttributeValue> parsedAttrs = [];
    if (rawAttrs is List) {
      parsedAttrs = rawAttrs
          .whereType<Map<String, dynamic>>()
          .map((a) => ProductVariantAttributeValue.fromJson(a))
          .toList();
    }
    if (parsedAttrs.isEmpty && parsedVariants.isNotEmpty) {
      final seen = <String>{};
      for (final v in parsedVariants) {
        for (final a in v.attributes) {
          if (seen.add('${a.attributeId}_${a.displayValue}')) {
            parsedAttrs.add(a);
          }
        }
      }
    }

    return ProductDetailApiModel(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      descriptionAr: json['description_ar'] as String?,
      descriptionEn: json['description_en'] as String?,
      brand: json['brand'] != null
          ? ProductBrandSummary.fromJson(json['brand'] as Map<String, dynamic>)
          : null,
      category: json['category'] != null
          ? ProductCategorySummary.fromJson(
              json['category'] as Map<String, dynamic>)
          : null,
      mediaGallery: parsedGallery,
      ratingSummary: ratingSummary,
      ratingDistribution: distribution,
      badges:
          ProductBadges.fromJson(json['badges'] as Map<String, dynamic>? ?? {}),
      attributes: parsedAttrs,
      variants: parsedVariants,
    );
  }

  @override
  List<Object?> get props => [
        id,
        nameAr,
        nameEn,
        slug,
        descriptionAr,
        descriptionEn,
        brand,
        category,
        mediaGallery,
        ratingSummary,
        ratingDistribution,
        badges,
        attributes,
        variants,
      ];
}
