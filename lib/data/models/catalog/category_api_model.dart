import 'package:equatable/equatable.dart';

class CategoryApiModel extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final int? parentId;
  final int sortOrder;
  final String? imageUrl;
  final List<CategoryApiModel> children;

  const CategoryApiModel({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.parentId,
    this.sortOrder = 0,
    this.imageUrl,
    this.children = const [],
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory CategoryApiModel.fromJson(Map<String, dynamic> json) {
    var rawChildren = json['children'];
    List<CategoryApiModel> parsedChildren = [];
    if (rawChildren is List) {
      parsedChildren = rawChildren
          .whereType<Map<String, dynamic>>()
          .map((item) => CategoryApiModel.fromJson(item))
          .toList();
    }

    final image = json['image'];

    return CategoryApiModel(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      parentId: json['parent_id'] as int?,
      sortOrder: json['sort_order'] as int? ?? 0,
      imageUrl: image is Map<String, dynamic> ? image['url'] as String? : null,
      children: parsedChildren,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name_ar': nameAr,
      'name_en': nameEn,
      'slug': slug,
      'parent_id': parentId,
      'sort_order': sortOrder,
      'image': imageUrl != null ? {'url': imageUrl} : null,
      'children': children.map((c) => c.toJson()).toList(),
    };
  }

  @override
  List<Object?> get props => [id, nameAr, nameEn, slug, parentId, sortOrder, imageUrl, children];
}
