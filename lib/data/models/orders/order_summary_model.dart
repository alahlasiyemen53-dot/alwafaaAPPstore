import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';
import 'order_status_mapper.dart';

class OrderSummaryModel extends Equatable {
  final int id;
  final String orderNumber;
  final String status;
  final DateTime? createdAt;
  final int itemsCount;
  final List<OrderSummaryItemModel> items;
  final double total;
  final String currencyCode;
  final String currencySymbol;

  String get statusAr => OrderStatusMapper.getInfo(status).label;

  const OrderSummaryModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    this.createdAt,
    required this.itemsCount,
    required this.items,
    required this.total,
    required this.currencyCode,
    required this.currencySymbol,
  });

  factory OrderSummaryModel.fromJson(Map<String, dynamic> json) {
    final currency = json['currency'] as Map<String, dynamic>?;
    final statusRaw = json['status'];
    final status = statusRaw is Map
        ? (statusRaw['status'] as String? ?? 'preparing')
        : (statusRaw as String? ?? 'preparing');

    return OrderSummaryModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      orderNumber: json['order_number'] as String? ?? '',
      status: status,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      itemsCount: (json['items_count'] as num?)?.toInt() ?? 0,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) =>
                  OrderSummaryItemModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      total: (json['total'] as num?)?.toDouble() ??
          (json['pricing']?['total'] as num?)?.toDouble() ??
          0.0,
      currencyCode: currency?['code'] as String? ?? 'YER',
      currencySymbol: CurrencyFormatter.resolveDisplaySymbol(
        symbol: currency?['symbol'] as String?,
        code: currency?['code'] as String? ?? 'YER',
      ),
    );
  }

  @override
  List<Object?> get props => [
        id,
        orderNumber,
        status,
        createdAt,
        itemsCount,
        items,
        total,
        currencyCode,
        currencySymbol,
      ];
}

class OrderSummaryItemModel extends Equatable {
  final String productName;
  final String variantName;
  final double quantity;

  const OrderSummaryItemModel({
    required this.productName,
    required this.variantName,
    required this.quantity,
  });

  factory OrderSummaryItemModel.fromJson(Map<String, dynamic> json) {
    return OrderSummaryItemModel(
      productName: json['product_name'] as String? ?? '',
      variantName: json['variant_name'] as String? ?? '',
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1.0,
    );
  }

  @override
  List<Object?> get props => [productName, variantName, quantity];
}

class OrderPaginationModel extends Equatable {
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const OrderPaginationModel({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  const OrderPaginationModel.empty()
      : currentPage = 1,
        perPage = 15,
        total = 0,
        lastPage = 1,
        hasMore = false;

  factory OrderPaginationModel.fromJson(Map<String, dynamic> json) {
    return OrderPaginationModel(
      currentPage: (json['current_page'] as num?)?.toInt() ?? 1,
      perPage: (json['per_page'] as num?)?.toInt() ?? 15,
      total: (json['total'] as num?)?.toInt() ?? 0,
      lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      hasMore: json['has_more'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [currentPage, perPage, total, lastPage, hasMore];
}

class OrdersResponseModel extends Equatable {
  final List<OrderSummaryModel> items;
  final OrderPaginationModel pagination;

  const OrdersResponseModel({
    required this.items,
    required this.pagination,
  });

  factory OrdersResponseModel.fromJson(Map<String, dynamic> json) {
    return OrdersResponseModel(
      items: (json['items'] as List<dynamic>?)
              ?.map((e) =>
                  OrderSummaryModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      pagination: json['pagination'] != null
          ? OrderPaginationModel.fromJson(
              json['pagination'] as Map<String, dynamic>)
          : const OrderPaginationModel.empty(),
    );
  }

  @override
  List<Object?> get props => [items, pagination];
}
