import 'package:equatable/equatable.dart';

class SupportPhoneModel extends Equatable {
  final bool enabled;
  final String? number;
  final String? telLink;

  const SupportPhoneModel({
    required this.enabled,
    this.number,
    this.telLink,
  });

  factory SupportPhoneModel.fromJson(Map<String, dynamic> json) {
    return SupportPhoneModel(
      enabled: (json['enabled'] ?? false) as bool,
      number: json['number'] as String?,
      telLink: (json['tel_link'] ?? json['telLink']) as String?,
    );
  }

  @override
  List<Object?> get props => [enabled, number, telLink];
}

class SupportWhatsappModel extends Equatable {
  final bool enabled;
  final String? number;
  final String? deepLink;
  final String? defaultMessage;

  const SupportWhatsappModel({
    required this.enabled,
    this.number,
    this.deepLink,
    this.defaultMessage,
  });

  factory SupportWhatsappModel.fromJson(Map<String, dynamic> json) {
    return SupportWhatsappModel(
      enabled: (json['enabled'] ?? false) as bool,
      number: json['number'] as String?,
      deepLink: (json['deep_link'] ?? json['deepLink']) as String?,
      defaultMessage: (json['default_message'] ?? json['defaultMessage']) as String?,
    );
  }

  @override
  List<Object?> get props => [enabled, number, deepLink, defaultMessage];
}

class CustomerSupportModel extends Equatable {
  final SupportPhoneModel phone;
  final SupportWhatsappModel whatsapp;
  final String? hoursTextAr;

  const CustomerSupportModel({
    required this.phone,
    required this.whatsapp,
    this.hoursTextAr,
  });

  factory CustomerSupportModel.fromJson(Map<String, dynamic> json) {
    final phoneJson = json['phone'] as Map<String, dynamic>? ?? {};
    final waJson = json['whatsapp'] as Map<String, dynamic>? ?? {};

    return CustomerSupportModel(
      phone: SupportPhoneModel.fromJson(phoneJson),
      whatsapp: SupportWhatsappModel.fromJson(waJson),
      hoursTextAr: (json['hours_text_ar'] ?? json['hoursTextAr']) as String?,
    );
  }

  @override
  List<Object?> get props => [phone, whatsapp, hoursTextAr];
}
