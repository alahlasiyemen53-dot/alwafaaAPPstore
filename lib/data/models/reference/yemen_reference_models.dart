import 'package:equatable/equatable.dart';

class CountryModel extends Equatable {
  final int id;
  final String code;
  final String nameAr;
  final String nameEn;
  final String callingCode;
  final int sortOrder;
  final bool isActive;
  final bool isDeliveryActive;
  final DateTime? updatedAt;

  const CountryModel({
    required this.id,
    required this.code,
    required this.nameAr,
    required this.nameEn,
    required this.callingCode,
    this.sortOrder = 0,
    required this.isActive,
    this.isDeliveryActive = false,
    this.updatedAt,
  });

  String get isoCode => code;

  String get displayName =>
      nameAr.trim().isNotEmpty ? nameAr : (nameEn.trim().isNotEmpty ? nameEn : code);

  factory CountryModel.fromJson(Map<String, dynamic> json) {
    final iso = (json['iso_code'] ?? json['isoCode'] ?? json['code'] ?? '') as String;
    return CountryModel(
      id: json['id'] as int,
      code: iso,
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? json['name'] ?? '') as String,
      nameEn: (json['name_en'] ?? json['nameEn'] ?? '') as String,
      callingCode: (json['calling_code'] ?? json['callingCode'] ?? '') as String,
      sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
      isDeliveryActive: (json['is_delivery_active'] ??
              json['isDeliveryActive'] ??
              (iso.toUpperCase() == 'YE')) as bool,
      updatedAt: DateTime.tryParse(
        (json['updated_at'] ?? json['updatedAt'] ?? '') as String,
      ),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'iso_code': code,
        'code': code,
        'name_ar': nameAr,
        'name_en': nameEn,
        'calling_code': callingCode,
        'sort_order': sortOrder,
        'is_active': isActive,
        'is_delivery_active': isDeliveryActive,
        'updated_at': updatedAt?.toUtc().toIso8601String(),
      };

  @override
  List<Object?> get props =>
      [id, code, nameAr, nameEn, callingCode, sortOrder, isActive, isDeliveryActive, updatedAt];
}

class GovernorateModel extends Equatable {
  final int? countryId;
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final bool isActive;
  final bool isDeliveryActive;
  final DateTime? updatedAt;

  const GovernorateModel({
    required this.id,
    this.countryId,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    required this.isActive,
    this.isDeliveryActive = true,
    this.updatedAt,
  });

  factory GovernorateModel.fromJson(Map<String, dynamic> json) {
    return GovernorateModel(
      id: json['id'] as int,
      countryId: json['country_id'] as int? ?? json['countryId'] as int?,
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? '') as String,
      nameEn: (json['name_en'] ?? json['nameEn'] ?? '') as String,
      slug: (json['slug'] ?? '') as String,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
      isDeliveryActive: (json['is_delivery_active'] ??
          json['isDeliveryActive'] ??
          true) as bool,
      updatedAt: DateTime.tryParse(
        (json['updated_at'] ?? json['updatedAt'] ?? '') as String,
      ),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        if (countryId != null) 'country_id': countryId,
        'name_ar': nameAr,
        'name_en': nameEn,
        'slug': slug,
        'is_active': isActive,
        'is_delivery_active': isDeliveryActive,
        'updated_at': updatedAt?.toUtc().toIso8601String(),
      };

  @override
  List<Object?> get props =>
      [id, countryId, nameAr, nameEn, slug, isActive, isDeliveryActive, updatedAt];
}

class CityModel extends Equatable {
  final int id;
  final int governorateId;
  final String nameAr;
  final String nameEn;
  final String slug;
  final bool isActive;

  const CityModel({
    required this.id,
    required this.governorateId,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    required this.isActive,
  });

  factory CityModel.fromJson(Map<String, dynamic> json) {
    return CityModel(
      id: json['id'] as int,
      governorateId: (json['governorate_id'] ?? json['governorateId'] ?? 0) as int,
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? '') as String,
      nameEn: (json['name_en'] ?? json['nameEn'] ?? '') as String,
      slug: (json['slug'] ?? '') as String,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'governorate_id': governorateId,
        'name_ar': nameAr,
        'name_en': nameEn,
        'slug': slug,
        'is_active': isActive,
      };

  @override
  List<Object?> get props => [id, governorateId, nameAr, nameEn, slug, isActive];
}

class DistrictModel extends Equatable {
  final int id;
  final int cityId;
  final String nameAr;
  final String nameEn;
  final String slug;
  final bool isActive;

  const DistrictModel({
    required this.id,
    required this.cityId,
    required this.nameAr,
    required this.nameEn,
    required this.slug,
    required this.isActive,
  });

  factory DistrictModel.fromJson(Map<String, dynamic> json) {
    return DistrictModel(
      id: json['id'] as int,
      cityId: (json['city_id'] ?? json['cityId'] ?? 0) as int,
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? '') as String,
      nameEn: (json['name_en'] ?? json['nameEn'] ?? '') as String,
      slug: (json['slug'] ?? '') as String,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'city_id': cityId,
        'name_ar': nameAr,
        'name_en': nameEn,
        'slug': slug,
        'is_active': isActive,
      };

  @override
  List<Object?> get props => [id, cityId, nameAr, nameEn, slug, isActive];
}
