import 'package:equatable/equatable.dart';

class CustomerTierModel extends Equatable {
  final int id;
  final String code;
  final String nameAr;
  final String? nameEn;

  const CustomerTierModel({
    required this.id,
    required this.code,
    required this.nameAr,
    this.nameEn,
  });

  factory CustomerTierModel.fromJson(Map<String, dynamic> json) {
    return CustomerTierModel(
      id: (json['id'] ?? 0) as int,
      code: (json['code'] ?? '') as String,
      nameAr: (json['name_ar'] ?? json['nameAr'] ?? '') as String,
      nameEn: (json['name_en'] ?? json['nameEn']) as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'code': code,
        'name_ar': nameAr,
        'name_en': nameEn,
      };

  @override
  List<Object?> get props => [id, code, nameAr, nameEn];
}

class LoyaltyEntryModel extends Equatable {
  final int id;
  final String entryNumber;
  final String type;
  final int points;
  final String? description;
  final DateTime? occurredAt;

  const LoyaltyEntryModel({
    required this.id,
    required this.entryNumber,
    required this.type,
    required this.points,
    this.description,
    this.occurredAt,
  });

  factory LoyaltyEntryModel.fromJson(Map<String, dynamic> json) {
    return LoyaltyEntryModel(
      id: (json['id'] ?? 0) as int,
      entryNumber: (json['entry_number'] ?? json['entryNumber'] ?? '') as String,
      type: (json['type'] ?? '') as String,
      points: (json['points'] ?? 0) as int,
      description: json['description'] as String?,
      occurredAt: json['occurred_at'] != null
          ? DateTime.tryParse(json['occurred_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'entry_number': entryNumber,
        'type': type,
        'points': points,
        'description': description,
        'occurred_at': occurredAt?.toIso8601String(),
      };

  @override
  List<Object?> get props => [id, entryNumber, type, points, description, occurredAt];
}

class CustomerLoyaltyModel extends Equatable {
  final int availablePoints;
  final int lifetimeEarned;
  final int lifetimeRedeemed;
  final CustomerTierModel? currentTier;
  final List<LoyaltyEntryModel> recentEntries;

  const CustomerLoyaltyModel({
    this.availablePoints = 0,
    this.lifetimeEarned = 0,
    this.lifetimeRedeemed = 0,
    this.currentTier,
    this.recentEntries = const [],
  });

  factory CustomerLoyaltyModel.fromJson(Map<String, dynamic> json) {
    final tierJson = json['current_tier'] as Map<String, dynamic>?;
    final entriesRaw = json['recent_entries'] as List<dynamic>? ?? [];

    return CustomerLoyaltyModel(
      availablePoints: (json['available_points'] ?? 0) as int,
      lifetimeEarned: (json['lifetime_earned'] ?? 0) as int,
      lifetimeRedeemed: (json['lifetime_redeemed'] ?? 0) as int,
      currentTier: tierJson != null ? CustomerTierModel.fromJson(tierJson) : null,
      recentEntries: entriesRaw
          .whereType<Map<String, dynamic>>()
          .map(LoyaltyEntryModel.fromJson)
          .toList(),
    );
  }

  @override
  List<Object?> get props => [
        availablePoints,
        lifetimeEarned,
        lifetimeRedeemed,
        currentTier,
        recentEntries,
      ];
}
