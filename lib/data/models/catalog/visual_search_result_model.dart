import 'package:equatable/equatable.dart';
import 'product_list_item_model.dart';

class VisualSearchResultModel extends Equatable {
  final int totalMatches;
  final List<ProductListItemModel> items;

  const VisualSearchResultModel({
    required this.totalMatches,
    required this.items,
  });

  factory VisualSearchResultModel.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : json;
    final itemsJson = data['items'] as List<dynamic>? ?? [];
    return VisualSearchResultModel(
      totalMatches: data['total_matches'] as int? ?? itemsJson.length,
      items: itemsJson
          .map((item) => ProductListItemModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }

  @override
  List<Object?> get props => [totalMatches, items];
}
