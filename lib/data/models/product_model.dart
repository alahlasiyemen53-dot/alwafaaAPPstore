import 'package:equatable/equatable.dart';
import 'catalog/product_detail_api_model.dart';
import 'catalog/product_list_item_model.dart';
import 'category_model.dart';

class FragranceNotes extends Equatable {
  final String? top;
  final String? heart;
  final String? base;

  const FragranceNotes({this.top, this.heart, this.base});

  factory FragranceNotes.fromJson(Map<String, dynamic> json) {
    return FragranceNotes(
      top: json['top'] as String?,
      heart: json['heart'] as String?,
      base: json['base'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        if (top != null) 'top': top,
        if (heart != null) 'heart': heart,
        if (base != null) 'base': base,
      };

  @override
  List<Object?> get props => [top, heart, base];
}

class ProductShade extends Equatable {
  final String name;
  final String hex;

  const ProductShade({required this.name, required this.hex});

  factory ProductShade.fromJson(Map<String, dynamic> json) {
    return ProductShade(
      name: json['name'] as String,
      hex: json['hex'] as String,
    );
  }

  Map<String, dynamic> toJson() => {'name': name, 'hex': hex};

  @override
  List<Object?> get props => [name, hex];
}

class ProductModel extends Equatable {
  final String id;
  final String name;
  final String brand;
  final ProductCategory category;
  final String? subCategory;
  final double priceSAR;
  final double? originalPriceSAR;
  final double rating;
  final int reviewsCount;
  final String image;
  final List<String> images;
  final String description;
  final FragranceNotes? notes;
  final List<String>? features;
  final List<String>? volumeOrSize;
  final List<ProductShade>? shades;
  final bool inStock;
  final bool isNew;
  final bool isBestSeller;
  final bool isExclusive;
  final int? discountPercentage;
  final int? serverId;
  final String? serverCurrency;
  final double? maxPrice;
  final bool hasPriceRange;
  final ProductListItemModel? listItemModel;
  final ProductDetailApiModel? detailApiModel;
  final int? selectedVariantId;
  final String? selectedVariantSku;

  const ProductModel({
    required this.id,
    required this.name,
    required this.brand,
    required this.category,
    this.subCategory,
    required this.priceSAR,
    this.originalPriceSAR,
    required this.rating,
    required this.reviewsCount,
    required this.image,
    required this.images,
    required this.description,
    this.notes,
    this.features,
    this.volumeOrSize,
    this.shades,
    this.inStock = true,
    this.isNew = false,
    this.isBestSeller = false,
    this.isExclusive = false,
    this.discountPercentage,
    this.serverId,
    this.serverCurrency,
    this.maxPrice,
    this.hasPriceRange = false,
    this.listItemModel,
    this.detailApiModel,
    this.selectedVariantId,
    this.selectedVariantSku,
  });

  factory ProductModel.placeholderForId(int serverId, {String? name}) {
    return ProductModel(
      id: serverId.toString(),
      serverId: serverId,
      name: name ?? '',
      brand: '',
      category: ProductCategory.all,
      priceSAR: 0.0,
      rating: 0.0,
      reviewsCount: 0,
      image: 'assets/images/placeholder.png',
      images: const ['assets/images/placeholder.png'],
      description: '',
      inStock: true,
    );
  }

  factory ProductModel.fromApiListItem(ProductListItemModel item) {
    // Map category name to ProductCategory enum if matching
    ProductCategory cat = ProductCategory.all;
    final catSlug = item.category?.slug.toLowerCase() ?? '';
    for (final val in ProductCategory.values) {
      if (catSlug.contains(val.name.toLowerCase())) {
        cat = val;
        break;
      }
    }

    final imgUrl = item.primaryImage ?? 'assets/images/placeholder.png';

    // Quick-add from cards only when a single purchasable option exists.
    // Multi-price products keep selectedVariantId null so the user picks on PDP.
    final canQuickAdd =
        item.defaultVariantId != null && !item.priceSummary.hasRange;

    return ProductModel(
      id: item.id.toString(),
      serverId: item.id,
      name: item.displayName,
      brand: item.brand?.displayName ?? '',
      category: cat,
      subCategory: item.category?.displayName,
      priceSAR: item.priceSummary.minPrice,
      maxPrice: item.priceSummary.maxPrice,
      hasPriceRange: item.priceSummary.hasRange,
      serverCurrency: item.priceSummary.currency,
      rating: item.rating.average,
      reviewsCount: item.rating.reviewsCount,
      image: imgUrl,
      images: [imgUrl],
      description: '',
      inStock: item.availability.isInStock || item.availability.isOnDemand,
      isNew: item.badges.isNew,
      isBestSeller: item.badges.isBestSeller,
      isExclusive: item.badges.isExclusive,
      listItemModel: item,
      selectedVariantId: canQuickAdd ? item.defaultVariantId : null,
    );
  }

  factory ProductModel.fromApiDetail(ProductDetailApiModel detail) {
    ProductCategory cat = ProductCategory.all;
    final catSlug = detail.category?.slug.toLowerCase() ?? '';
    for (final val in ProductCategory.values) {
      if (catSlug.contains(val.name.toLowerCase())) {
        cat = val;
        break;
      }
    }

    final galleryUrls = detail.mediaGallery.map((m) => m.optimizedUrl).toList();
    final primaryImg = detail.mediaGallery.isNotEmpty
        ? detail.mediaGallery
            .firstWhere((m) => m.isPrimary,
                orElse: () => detail.mediaGallery.first)
            .optimizedUrl
        : 'assets/images/placeholder.png';

    final defaultVar = detail.defaultVariant ??
        (detail.variants.isNotEmpty ? detail.variants.first : null);
    final minPrice = defaultVar?.price.amount ?? 0.0;
    final compareAt = defaultVar?.price.compareAt;

    // Extract sizes or attributes
    List<String> sizes = [];
    List<ProductShade> shades = [];

    for (final v in detail.variants) {
      for (final attr in v.attributes) {
        if (attr.code.toLowerCase().contains('size') ||
            attr.code.toLowerCase().contains('volume')) {
          if (!sizes.contains(attr.displayValue)) sizes.add(attr.displayValue);
        } else if (attr.code.toLowerCase().contains('shade') ||
            attr.code.toLowerCase().contains('color')) {
          if (!shades.any((s) => s.name == attr.displayValue)) {
            shades.add(ProductShade(name: attr.displayValue, hex: '#C5A880'));
          }
        }
      }
    }

    return ProductModel(
      id: detail.id.toString(),
      serverId: detail.id,
      name: detail.displayName,
      brand: detail.brand?.displayName ?? '',
      category: cat,
      subCategory: detail.category?.displayName,
      priceSAR: minPrice,
      originalPriceSAR: compareAt,
      serverCurrency: defaultVar?.price.currency ?? 'YER',
      rating: detail.ratingSummary.average,
      reviewsCount: detail.ratingSummary.reviewsCount,
      image: primaryImg,
      images: galleryUrls.isNotEmpty ? galleryUrls : [primaryImg],
      description: detail.displayDescription,
      volumeOrSize: sizes.isNotEmpty ? sizes : null,
      shades: shades.isNotEmpty ? shades : null,
      inStock: detail.variants
          .any((v) => v.availability.isInStock || v.availability.isOnDemand),
      isNew: detail.badges.isNew,
      isBestSeller: detail.badges.isBestSeller,
      isExclusive: detail.badges.isExclusive,
      discountPercentage: defaultVar?.price.discountPercentage?.round(),
      detailApiModel: detail,
      selectedVariantId: defaultVar?.id,
      selectedVariantSku: defaultVar?.sku,
    );
  }

  ProductModel copyWith({
    String? id,
    String? name,
    String? brand,
    ProductCategory? category,
    String? subCategory,
    double? priceSAR,
    double? originalPriceSAR,
    double? rating,
    int? reviewsCount,
    String? image,
    List<String>? images,
    String? description,
    FragranceNotes? notes,
    List<String>? features,
    List<String>? volumeOrSize,
    List<ProductShade>? shades,
    bool? inStock,
    bool? isNew,
    bool? isBestSeller,
    bool? isExclusive,
    int? discountPercentage,
    int? serverId,
    String? serverCurrency,
    double? maxPrice,
    bool? hasPriceRange,
    ProductListItemModel? listItemModel,
    ProductDetailApiModel? detailApiModel,
    int? selectedVariantId,
    String? selectedVariantSku,
  }) {
    return ProductModel(
      id: id ?? this.id,
      name: name ?? this.name,
      brand: brand ?? this.brand,
      category: category ?? this.category,
      subCategory: subCategory ?? this.subCategory,
      priceSAR: priceSAR ?? this.priceSAR,
      originalPriceSAR: originalPriceSAR ?? this.originalPriceSAR,
      rating: rating ?? this.rating,
      reviewsCount: reviewsCount ?? this.reviewsCount,
      image: image ?? this.image,
      images: images ?? this.images,
      description: description ?? this.description,
      notes: notes ?? this.notes,
      features: features ?? this.features,
      volumeOrSize: volumeOrSize ?? this.volumeOrSize,
      shades: shades ?? this.shades,
      inStock: inStock ?? this.inStock,
      isNew: isNew ?? this.isNew,
      isBestSeller: isBestSeller ?? this.isBestSeller,
      isExclusive: isExclusive ?? this.isExclusive,
      discountPercentage: discountPercentage ?? this.discountPercentage,
      serverId: serverId ?? this.serverId,
      serverCurrency: serverCurrency ?? this.serverCurrency,
      maxPrice: maxPrice ?? this.maxPrice,
      hasPriceRange: hasPriceRange ?? this.hasPriceRange,
      listItemModel: listItemModel ?? this.listItemModel,
      detailApiModel: detailApiModel ?? this.detailApiModel,
      selectedVariantId: selectedVariantId ?? this.selectedVariantId,
      selectedVariantSku: selectedVariantSku ?? this.selectedVariantSku,
    );
  }

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'] as String,
      name: json['name'] as String,
      brand: json['brand'] as String,
      category: ProductCategory.values.firstWhere(
        (e) => e.name == json['category'],
        orElse: () => ProductCategory.all,
      ),
      subCategory: json['subCategory'] as String?,
      priceSAR: (json['priceSAR'] as num).toDouble(),
      originalPriceSAR: (json['originalPriceSAR'] as num?)?.toDouble(),
      rating: (json['rating'] as num).toDouble(),
      reviewsCount: json['reviewsCount'] as int,
      image: json['image'] as String,
      images: (json['images'] as List<dynamic>?)
              ?.map((e) => e as String)
              .toList() ??
          [],
      description: json['description'] as String,
      notes: json['notes'] != null
          ? FragranceNotes.fromJson(json['notes'] as Map<String, dynamic>)
          : null,
      features: (json['features'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      volumeOrSize: (json['volumeOrSize'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      shades: (json['shades'] as List<dynamic>?)
          ?.map((e) => ProductShade.fromJson(e as Map<String, dynamic>))
          .toList(),
      inStock: json['inStock'] as bool? ?? true,
      isNew: json['isNew'] as bool? ?? false,
      isBestSeller: json['isBestSeller'] as bool? ?? false,
      isExclusive: json['isExclusive'] as bool? ?? false,
      discountPercentage: json['discountPercentage'] as int?,
      selectedVariantId: json['selectedVariantId'] as int?,
      selectedVariantSku: json['selectedVariantSku'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        id,
        name,
        brand,
        category,
        subCategory,
        priceSAR,
        originalPriceSAR,
        rating,
        reviewsCount,
        image,
        images,
        description,
        notes,
        features,
        volumeOrSize,
        shades,
        inStock,
        isNew,
        isBestSeller,
        isExclusive,
        discountPercentage,
        serverId,
        serverCurrency,
        maxPrice,
        hasPriceRange,
        listItemModel,
        detailApiModel,
        selectedVariantId,
        selectedVariantSku,
      ];
}
