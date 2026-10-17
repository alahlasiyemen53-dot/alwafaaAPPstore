import 'package:equatable/equatable.dart';

class UserModel extends Equatable {
  final int id;
  final String name;
  final String? email;
  final String phone;
  final String normalizedPhone;
  final bool isActive;
  final bool phoneVerified;
  final String? tierName;
  /// Country chosen at phone registration (hidden on address form).
  final int? countryId;

  const UserModel({
    required this.id,
    required this.name,
    this.email,
    required this.phone,
    required this.normalizedPhone,
    required this.isActive,
    required this.phoneVerified,
    this.tierName,
    this.countryId,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final tier = json['tier'];
    final country = json['country'];
    final countryId = country is Map<String, dynamic>
        ? country['id'] as int?
        : json['country_id'] as int?;

    return UserModel(
      id: json['id'] as int,
      name: (json['name'] as String?)?.trim().isNotEmpty == true
          ? json['name'] as String
          : 'عميل لمّة',
      email: json['email'] as String?,
      phone: json['phone'] as String,
      normalizedPhone: json['normalized_phone'] as String,
      isActive: json['is_active'] as bool,
      phoneVerified: json['phone_verified'] as bool,
      tierName: tier is Map<String, dynamic> ? tier['name'] as String? : null,
      countryId: countryId,
    );
  }

  @override
  List<Object?> get props => [
        id,
        name,
        email,
        phone,
        normalizedPhone,
        isActive,
        phoneVerified,
        tierName,
        countryId,
      ];
}
