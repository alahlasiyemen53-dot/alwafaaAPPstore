import 'package:equatable/equatable.dart';
import '../catalog/product_list_item_model.dart';

class WishlistResponseModel extends Equatable {
  final List<ProductListItemModel> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const WishlistResponseModel({
    required this.items,
    this.currentPage = 1,
    this.perPage = 15,
    this.total = 0,
    this.lastPage = 1,
    this.hasMore = false,
  });

  factory WishlistResponseModel.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final List<ProductListItemModel> parsedItems = [];
    if (rawItems is List) {
      parsedItems.addAll(
        rawItems
            .whereType<Map<String, dynamic>>()
            .map((item) => ProductListItemModel.fromJson(item)),
      );
    }

    final pagination = json['pagination'] as Map<String, dynamic>? ?? {};

    return WishlistResponseModel(
      items: parsedItems,
      currentPage: pagination['current_page'] as int? ?? 1,
      perPage: pagination['per_page'] as int? ?? 15,
      total: pagination['total'] as int? ?? parsedItems.length,
      lastPage: pagination['last_page'] as int? ?? 1,
      hasMore: pagination['has_more'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [items, currentPage, perPage, total, lastPage, hasMore];
}
