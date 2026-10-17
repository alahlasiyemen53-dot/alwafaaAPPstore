import 'package:equatable/equatable.dart';
import 'catalog/category_api_model.dart';
import 'catalog/home_api_model.dart';

enum ProductCategory { all, perfumes, makeup, skincare, haircare, salon, gifts }

class CategoryModel extends Equatable {
  final ProductCategory id;
  final int? serverId;
  final String? slug;
  final String nameAr;
  final String nameEn;
  final String icon;
  final String image;
  final int? parentId;
  final List<CategoryModel> children;
  final int count;

  const CategoryModel({
    required this.id,
    this.serverId,
    this.slug,
    this.parentId,
    this.children = const [],
    required this.nameAr,
    required this.nameEn,
    required this.icon,
    required this.image,
    required this.count,
  });

  factory CategoryModel.fromApiModel(CategoryApiModel api) {
    ProductCategory cat = ProductCategory.all;
    final slugLower = api.slug.toLowerCase();
    for (final val in ProductCategory.values) {
      if (slugLower.contains(val.name.toLowerCase())) {
        cat = val;
        break;
      }
    }

    return CategoryModel(
      id: cat,
      serverId: api.id,
      slug: api.slug,
      parentId: api.parentId,
      children: api.children.map((c) => CategoryModel.fromApiModel(c)).toList(),
      nameAr: api.nameAr.isNotEmpty ? api.nameAr : api.nameEn,
      nameEn: api.nameEn.isNotEmpty ? api.nameEn : api.nameAr,
      icon: '✨',
      image: api.imageUrl ?? 'assets/images/category_perfume.png',
      count: 0,
    );
  }

  factory CategoryModel.fromHomeSummary(HomeCategorySummary summary) {
    ProductCategory cat = ProductCategory.all;
    final slugLower = summary.slug.toLowerCase();
    for (final val in ProductCategory.values) {
      if (slugLower.contains(val.name.toLowerCase())) {
        cat = val;
        break;
      }
    }

    return CategoryModel(
      id: cat,
      serverId: summary.id,
      slug: summary.slug,
      parentId: summary.parentId,
      children: const [],
      nameAr: summary.displayName,
      nameEn: summary.nameEn.isNotEmpty ? summary.nameEn : summary.nameAr,
      icon: '✨',
      image: summary.imageUrl ?? 'assets/images/category_perfume.png',
      count: 0,
    );
  }

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    final childrenJson = json['children'] as List<dynamic>? ?? [];
    return CategoryModel(
      id: ProductCategory.values.firstWhere(
        (e) => e.name == json['id'],
        orElse: () => ProductCategory.all,
      ),
      serverId: json['serverId'] as int?,
      slug: json['slug'] as String?,
      parentId: json['parentId'] as int?,
      children: childrenJson.map((c) => CategoryModel.fromJson(c as Map<String, dynamic>)).toList(),
      nameAr: json['nameAr'] as String,
      nameEn: json['nameEn'] as String,
      icon: json['icon'] as String,
      image: json['image'] as String,
      count: json['count'] as int,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id.name,
      'serverId': serverId,
      'slug': slug,
      'parentId': parentId,
      'children': children.map((c) => c.toJson()).toList(),
      'nameAr': nameAr,
      'nameEn': nameEn,
      'icon': icon,
      'image': image,
      'count': count,
    };
  }

  @override
  List<Object?> get props => [id, serverId, slug, parentId, children, nameAr, nameEn, icon, image, count];
}
