import 'package:equatable/equatable.dart';

import 'product_list_item_model.dart';

class CollectionSummaryModel extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final String? descriptionAr;
  final String? coverUrl;
  final int sortOrder;

  const CollectionSummaryModel({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.descriptionAr,
    this.coverUrl,
    this.sortOrder = 0,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory CollectionSummaryModel.fromJson(Map<String, dynamic> json) {
    return CollectionSummaryModel(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      descriptionAr: json['description_ar'] as String?,
      coverUrl: json['cover_url'] as String?,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }

  @override
  List<Object?> get props =>
      [id, nameAr, nameEn, slug, descriptionAr, coverUrl, sortOrder];
}

class CollectionDetailModel extends Equatable {
  final CollectionSummaryModel collection;
  final List<ProductListItemModel> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const CollectionDetailModel({
    required this.collection,
    this.items = const [],
    this.currentPage = 1,
    this.perPage = 15,
    this.total = 0,
    this.lastPage = 1,
    this.hasMore = false,
  });

  factory CollectionDetailModel.fromJson(Map<String, dynamic> json) {
    final collectionJson = json['collection'] is Map<String, dynamic>
        ? json['collection'] as Map<String, dynamic>
        : json;
    final rawItems = json['items'];
    final items = rawItems is List
        ? rawItems
            .whereType<Map<String, dynamic>>()
            .map(ProductListItemModel.fromJson)
            .toList()
        : <ProductListItemModel>[];
    final pagination = json['pagination'] as Map<String, dynamic>? ?? {};

    return CollectionDetailModel(
      collection: CollectionSummaryModel.fromJson(collectionJson),
      items: items,
      currentPage: pagination['current_page'] as int? ?? 1,
      perPage: pagination['per_page'] as int? ?? 15,
      total: pagination['total'] as int? ?? items.length,
      lastPage: pagination['last_page'] as int? ?? 1,
      hasMore: pagination['has_more'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props =>
      [collection, items, currentPage, perPage, total, lastPage, hasMore];
}

class CollectionsListResponse extends Equatable {
  final List<CollectionSummaryModel> items;

  const CollectionsListResponse({this.items = const []});

  factory CollectionsListResponse.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    final items = raw is List
        ? raw
            .whereType<Map>()
            .map((e) =>
                CollectionSummaryModel.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <CollectionSummaryModel>[];
    return CollectionsListResponse(items: items);
  }

  @override
  List<Object?> get props => [items];
}
