import 'package:equatable/equatable.dart';

class CustomerAddressModel extends Equatable {
  final int id;
  final String? label;
  final String? contactName;
  final String? contactPhone;
  final int? countryId;
  final String? countryNameAr;
  final int? governorateId;
  final String? governorateNameAr;
  final String? governorateNameEn;
  final int? cityId;
  final String? cityNameAr;
  final String? cityNameEn;
  final int? districtId;
  final String? districtNameAr;
  final String? districtNameEn;
  final String? neighborhood;
  final String? street;
  final String? building;
  final String? floor;
  final String? apartment;
  final String? landmark;
  final String? notes;
  final double? latitude;
  final double? longitude;
  final String? formattedAddress;
  final bool isDefault;

  const CustomerAddressModel({
    required this.id,
    this.label,
    this.contactName,
    this.contactPhone,
    this.countryId,
    this.countryNameAr,
    this.governorateId,
    this.governorateNameAr,
    this.governorateNameEn,
    this.cityId,
    this.cityNameAr,
    this.cityNameEn,
    this.districtId,
    this.districtNameAr,
    this.districtNameEn,
    this.neighborhood,
    this.street,
    this.building,
    this.floor,
    this.apartment,
    this.landmark,
    this.notes,
    this.latitude,
    this.longitude,
    this.formattedAddress,
    this.isDefault = false,
  });

  factory CustomerAddressModel.fromJson(Map<String, dynamic> json) {
    final country = json['country'] as Map<String, dynamic>?;
    final gov = json['governorate'] as Map<String, dynamic>?;
    final city = json['city'] as Map<String, dynamic>?;
    final dist = json['district'] as Map<String, dynamic>?;
    final coords = json['coordinates'] as Map<String, dynamic>?;

    final rawDefault = json['is_default'] ?? json['isDefault'];
    final isDefaultBool = rawDefault == true ||
        rawDefault == 1 ||
        rawDefault == '1';

    int? parseId(dynamic val) {
      if (val == null) return null;
      if (val is num) return val.toInt();
      return int.tryParse('$val');
    }

    return CustomerAddressModel(
      id: parseId(json['id']) ?? 0,
      label: json['label'] as String?,
      contactName: (json['contact_name'] ?? json['contactName']) as String?,
      contactPhone: (json['contact_phone'] ?? json['contactPhone']) as String?,
      countryId: country != null
          ? parseId(country['id'])
          : parseId(json['country_id']),
      countryNameAr: country != null ? country['name_ar'] as String? : null,
      governorateId: gov != null ? parseId(gov['id']) : parseId(json['governorate_id']),
      governorateNameAr: gov != null ? gov['name_ar'] as String? : null,
      governorateNameEn: gov != null ? gov['name_en'] as String? : null,
      cityId: city != null ? parseId(city['id']) : parseId(json['city_id']),
      cityNameAr: city != null ? city['name_ar'] as String? : null,
      cityNameEn: city != null ? city['name_en'] as String? : null,
      districtId: dist != null ? parseId(dist['id']) : parseId(json['district_id']),
      districtNameAr: dist != null ? dist['name_ar'] as String? : null,
      districtNameEn: dist != null ? dist['name_en'] as String? : null,
      neighborhood: json['neighborhood'] as String?,
      street: json['street'] as String?,
      building: json['building'] as String?,
      floor: json['floor'] as String?,
      apartment: json['apartment'] as String?,
      landmark: json['landmark'] as String?,
      notes: json['notes'] as String?,
      latitude: coords != null && coords['latitude'] != null
          ? (coords['latitude'] as num).toDouble()
          : (json['latitude'] as num?)?.toDouble(),
      longitude: coords != null && coords['longitude'] != null
          ? (coords['longitude'] as num).toDouble()
          : (json['longitude'] as num?)?.toDouble(),
      formattedAddress: (json['formatted_address'] ?? json['formattedAddress']) as String?,
      isDefault: isDefaultBool,
    );
  }

  Map<String, dynamic> toCreatePayload() {
    final map = <String, dynamic>{};
    if (label != null && label!.isNotEmpty) map['label'] = label;
    if (contactName != null && contactName!.isNotEmpty) map['contact_name'] = contactName;
    if (contactPhone != null && contactPhone!.isNotEmpty) map['contact_phone'] = contactPhone;
    if (countryId != null) map['country_id'] = countryId;
    if (governorateId != null) map['governorate_id'] = governorateId;
    if (neighborhood != null && neighborhood!.isNotEmpty) map['neighborhood'] = neighborhood;
    if (street != null && street!.isNotEmpty) map['street'] = street;
    if (formattedAddress != null && formattedAddress!.isNotEmpty) {
      map['formatted_address'] = formattedAddress;
    }
    if (building != null && building!.isNotEmpty) map['building'] = building;
    if (floor != null && floor!.isNotEmpty) map['floor'] = floor;
    if (apartment != null && apartment!.isNotEmpty) map['apartment'] = apartment;
    if (landmark != null && landmark!.isNotEmpty) map['landmark'] = landmark;
    if (notes != null && notes!.isNotEmpty) map['notes'] = notes;
    if (latitude != null) map['latitude'] = latitude;
    if (longitude != null) map['longitude'] = longitude;
    map['is_default'] = isDefault;
    return map;
  }

  CustomerAddressModel copyWith({
    int? id,
    String? label,
    String? contactName,
    String? contactPhone,
    int? governorateId,
    String? governorateNameAr,
    String? governorateNameEn,
    int? cityId,
    String? cityNameAr,
    String? cityNameEn,
    int? districtId,
    String? districtNameAr,
    String? districtNameEn,
    String? neighborhood,
    String? street,
    String? building,
    String? floor,
    String? apartment,
    String? landmark,
    String? notes,
    double? latitude,
    double? longitude,
    String? formattedAddress,
    bool? isDefault,
  }) {
    return CustomerAddressModel(
      id: id ?? this.id,
      label: label ?? this.label,
      contactName: contactName ?? this.contactName,
      contactPhone: contactPhone ?? this.contactPhone,
      governorateId: governorateId ?? this.governorateId,
      governorateNameAr: governorateNameAr ?? this.governorateNameAr,
      governorateNameEn: governorateNameEn ?? this.governorateNameEn,
      cityId: cityId ?? this.cityId,
      cityNameAr: cityNameAr ?? this.cityNameAr,
      cityNameEn: cityNameEn ?? this.cityNameEn,
      districtId: districtId ?? this.districtId,
      districtNameAr: districtNameAr ?? this.districtNameAr,
      districtNameEn: districtNameEn ?? this.districtNameEn,
      neighborhood: neighborhood ?? this.neighborhood,
      street: street ?? this.street,
      building: building ?? this.building,
      floor: floor ?? this.floor,
      apartment: apartment ?? this.apartment,
      landmark: landmark ?? this.landmark,
      notes: notes ?? this.notes,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      formattedAddress: formattedAddress ?? this.formattedAddress,
      isDefault: isDefault ?? this.isDefault,
    );
  }

  /// Display text representation for address summary in UI
  String get displayFullAddress {
    if (formattedAddress != null && formattedAddress!.trim().isNotEmpty) {
      return formattedAddress!;
    }
    final parts = <String>[
      if (countryNameAr != null && countryNameAr!.trim().isNotEmpty)
        countryNameAr!,
      if (governorateNameAr != null && governorateNameAr!.trim().isNotEmpty)
        governorateNameAr!,
      if (street != null && street!.trim().isNotEmpty) street!,
    ];
    if (parts.isNotEmpty) {
      return parts.join('، ');
    }
    if (latitude != null && longitude != null) {
      return 'موقع محدد على الخريطة';
    }
    return 'عنوان غير محدد';
  }

  @override
  List<Object?> get props => [
        id,
        label,
        contactName,
        contactPhone,
        governorateId,
        cityId,
        districtId,
        neighborhood,
        street,
        building,
        floor,
        apartment,
        landmark,
        notes,
        latitude,
        longitude,
        formattedAddress,
        isDefault,
      ];
}
