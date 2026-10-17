import 'package:equatable/equatable.dart';

class BrandApiModel extends Equatable {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final String? description;
  final String? logoUrl;

  const BrandApiModel({
    required this.id,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    this.description,
    this.logoUrl,
  });

  String get displayName => nameAr.isNotEmpty ? nameAr : nameEn;

  factory BrandApiModel.fromJson(Map<String, dynamic> json) {
    final logo = json['logo'];

    return BrandApiModel(
      id: json['id'] as int? ?? 0,
      nameAr: json['name_ar'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      description: json['description'] as String?,
      logoUrl: logo is Map<String, dynamic> ? logo['url'] as String? : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name_ar': nameAr,
      'name_en': nameEn,
      'slug': slug,
      'description': description,
      'logo': logoUrl != null ? {'url': logoUrl} : null,
    };
  }

  @override
  List<Object?> get props => [id, nameAr, nameEn, slug, description, logoUrl];
}
