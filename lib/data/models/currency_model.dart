import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';

// ignore_for_file: constant_identifier_names

enum CurrencyCode { SAR, AED, KWD, QAR, OMR, USD, YER }

class CurrencyModel extends Equatable {
  final int id;
  final String code;
  final String symbol;
  final String nameAr;
  final String? nameEn;
  final int decimalPlaces;
  final bool isDefault;
  final bool isActive;
  final bool isBase;
  final String? rate;

  const CurrencyModel({
    required this.id,
    required this.code,
    required this.symbol,
    required this.nameAr,
    this.nameEn,
    this.decimalPlaces = 0,
    this.isDefault = false,
    this.isActive = true,
    this.isBase = false,
    this.rate,
  });

  factory CurrencyModel.fromJson(Map<String, dynamic> json) {
    final code = ((json['code'] ?? 'YER') as String).toUpperCase();
    final rawSymbol =
        (json['symbol'] ?? json['symbol_ar'] ?? 'ر.ي') as String;
    return CurrencyModel(
      id: (json['id'] ?? 0) as int,
      code: code,
      symbol: CurrencyFormatter.resolveDisplaySymbol(
        symbol: rawSymbol,
        code: code,
      ),
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? json['name'] ?? 'ريال يمني')
          as String,
      nameEn: (json['name_en'] ?? json['nameEn']) as String?,
      decimalPlaces:
          (json['decimal_places'] ?? json['decimalPlaces'] ?? 0) as int,
      isDefault: (json['is_default'] ?? json['isDefault'] ?? false) as bool,
      isActive: (json['is_active'] ?? json['isActive'] ?? true) as bool,
      isBase: (json['is_base'] ?? json['isBase'] ?? false) as bool,
      rate: json['rate']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'symbol': symbol,
      'name_ar': nameAr,
      'name_en': nameEn,
      'decimal_places': decimalPlaces,
      'is_default': isDefault,
      'is_active': isActive,
      'is_base': isBase,
      'rate': rate,
    };
  }

  @override
  List<Object?> get props => [
        id,
        code,
        symbol,
        nameAr,
        nameEn,
        decimalPlaces,
        isDefault,
        isActive,
        isBase,
        rate,
      ];
}
