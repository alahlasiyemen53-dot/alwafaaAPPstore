import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';

class CartCurrencyModel extends Equatable {
  final int id;
  final String code;
  final String symbol;
  final bool isBase;

  const CartCurrencyModel({
    required this.id,
    required this.code,
    required this.symbol,
    required this.isBase,
  });

  factory CartCurrencyModel.fromJson(Map<String, dynamic> json) {
    return CartCurrencyModel(
      id: _asInt(json['id']) ?? 0,
      code: json['code'] as String? ?? 'YER',
      symbol: CurrencyFormatter.resolveDisplaySymbol(
        symbol: json['symbol'] as String?,
        code: json['code'] as String? ?? 'YER',
      ),
      isBase: json['is_base'] as bool? ?? true,
    );
  }

  @override
  List<Object?> get props => [id, code, symbol, isBase];
}

class CartPricingModel extends Equatable {
  final double itemsSubtotal;
  final double itemDiscountsTotal;
  final double couponDiscount;
  final double estimatedTotal;

  const CartPricingModel({
    required this.itemsSubtotal,
    required this.itemDiscountsTotal,
    required this.couponDiscount,
    required this.estimatedTotal,
  });

  double get totalDiscount => itemDiscountsTotal + couponDiscount;

  factory CartPricingModel.fromJson(Map<String, dynamic> json) {
    return CartPricingModel(
      itemsSubtotal: _asDouble(json['items_subtotal']) ?? 0.0,
      itemDiscountsTotal: _asDouble(json['item_discounts_total']) ?? 0.0,
      couponDiscount: _asDouble(json['coupon_discount']) ?? 0.0,
      estimatedTotal: _asDouble(json['estimated_total']) ?? 0.0,
    );
  }

  @override
  List<Object?> get props => [
        itemsSubtotal,
        itemDiscountsTotal,
        couponDiscount,
        estimatedTotal,
      ];
}

class CartCouponModel extends Equatable {
  final String code;
  final bool isValid;
  final double discountAmount;
  final String? message;

  const CartCouponModel({
    required this.code,
    required this.isValid,
    required this.discountAmount,
    this.message,
  });

  factory CartCouponModel.fromJson(Map<String, dynamic> json) {
    return CartCouponModel(
      code: json['code'] as String? ?? '',
      isValid: json['is_valid'] as bool? ?? false,
      discountAmount: _asDouble(json['discount_amount']) ?? 0.0,
      message: json['message'] as String?,
    );
  }

  @override
  List<Object?> get props => [code, isValid, discountAmount, message];
}

class CartProductSummary extends Equatable {
  final int id;
  final String name;
  final String slug;
  final String? brandName;
  final String? primaryImageUrl;

  const CartProductSummary({
    required this.id,
    required this.name,
    required this.slug,
    this.brandName,
    this.primaryImageUrl,
  });

  factory CartProductSummary.fromJson(Map<String, dynamic> json) {
    return CartProductSummary(
      id: _asInt(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      brandName: json['brand'] as String?,
      primaryImageUrl: json['primary_image_url'] as String?,
    );
  }

  @override
  List<Object?> get props => [id, name, slug, brandName, primaryImageUrl];
}

class CartVariantAttribute extends Equatable {
  final int attributeId;
  final String? code;
  final String? name;
  final String value;

  const CartVariantAttribute({
    required this.attributeId,
    this.code,
    this.name,
    required this.value,
  });

  String get displayName {
    final n = name?.trim() ?? '';
    return n.isNotEmpty ? n : (code ?? '');
  }

  String get displayLabel {
    final label = displayName;
    if (label.isEmpty) return value;
    return '$label: $value';
  }

  factory CartVariantAttribute.fromJson(Map<String, dynamic> json) {
    final nameAr = json['name_ar'] as String?;
    final nameEn = json['name_en'] as String?;
    final valueAr = json['value_ar'] as String?;
    final valueEn = json['value_en'] as String?;

    return CartVariantAttribute(
      attributeId: _asInt(json['attribute_id']) ?? 0,
      code: json['code'] as String?,
      name: (json['name'] as String?)?.trim().isNotEmpty == true
          ? json['name'] as String?
          : (nameAr?.trim().isNotEmpty == true ? nameAr : nameEn),
      value: (json['value'] as String?)?.trim().isNotEmpty == true
          ? (json['value'] as String)
          : (valueAr?.trim().isNotEmpty == true
              ? valueAr!
              : (valueEn ?? '')),
    );
  }

  @override
  List<Object?> get props => [attributeId, code, name, value];
}

class CartVariantSummary extends Equatable {
  final int id;
  final String sku;
  final List<CartVariantAttribute> attributes;

  const CartVariantSummary({
    required this.id,
    required this.sku,
    required this.attributes,
  });

  factory CartVariantSummary.fromJson(Map<String, dynamic> json) {
    final rawAttrs = json['attributes'];
    final List<CartVariantAttribute> attrs = [];
    if (rawAttrs is List) {
      attrs.addAll(
        rawAttrs
            .whereType<Map<String, dynamic>>()
            .map((a) => CartVariantAttribute.fromJson(a)),
      );
    }
    return CartVariantSummary(
      id: _asInt(json['id']) ?? 0,
      sku: json['sku'] as String? ?? '',
      attributes: attrs,
    );
  }

  @override
  List<Object?> get props => [id, sku, attributes];
}

class CartItemAvailability extends Equatable {
  final String status;
  final int? availableQuantity;

  const CartItemAvailability({
    required this.status,
    this.availableQuantity,
  });

  bool get isInStock => status.toLowerCase() == 'in_stock';

  factory CartItemAvailability.fromJson(Map<String, dynamic> json) {
    return CartItemAvailability(
      status: json['status'] as String? ?? 'in_stock',
      // Backend may send float stock (e.g. 12.0); hard `as int?` throws TypeError.
      availableQuantity: _asInt(json['available_quantity']),
    );
  }

  @override
  List<Object?> get props => [status, availableQuantity];
}

class CartItemApiModel extends Equatable {
  final int id;
  final int productVariantId;
  final int quantity;
  final double unitPrice;
  final double lineSubtotal;
  final double itemDiscount;
  final double lineTotal;
  final CartItemAvailability availability;
  final CartProductSummary? product;
  final CartVariantSummary? variant;

  const CartItemApiModel({
    required this.id,
    required this.productVariantId,
    required this.quantity,
    required this.unitPrice,
    required this.lineSubtotal,
    required this.itemDiscount,
    required this.lineTotal,
    required this.availability,
    this.product,
    this.variant,
  });

  factory CartItemApiModel.fromJson(Map<String, dynamic> json) {
    return CartItemApiModel(
      id: _asInt(json['id']) ?? 0,
      productVariantId: _asInt(json['product_variant_id']) ?? 0,
      quantity: _asInt(json['quantity']) ?? 1,
      unitPrice: _asDouble(json['unit_price']) ?? 0.0,
      lineSubtotal: _asDouble(json['line_subtotal']) ?? 0.0,
      itemDiscount: _asDouble(json['item_discount']) ?? 0.0,
      lineTotal: _asDouble(json['line_total']) ?? 0.0,
      availability: json['availability'] is Map<String, dynamic>
          ? CartItemAvailability.fromJson(
              json['availability'] as Map<String, dynamic>)
          : const CartItemAvailability(status: 'in_stock'),
      product: json['product'] is Map<String, dynamic>
          ? CartProductSummary.fromJson(json['product'] as Map<String, dynamic>)
          : null,
      variant: json['variant'] is Map<String, dynamic>
          ? CartVariantSummary.fromJson(json['variant'] as Map<String, dynamic>)
          : null,
    );
  }

  @override
  List<Object?> get props => [
        id,
        productVariantId,
        quantity,
        unitPrice,
        lineSubtotal,
        itemDiscount,
        lineTotal,
        availability,
        product,
        variant,
      ];
}

class CartApiModel extends Equatable {
  final int cartId;
  final List<CartItemApiModel> items;
  final int itemsCount;
  final int totalQuantity;
  final CartCurrencyModel currency;
  final CartPricingModel pricing;
  final CartCouponModel? coupon;

  const CartApiModel({
    required this.cartId,
    required this.items,
    required this.itemsCount,
    required this.totalQuantity,
    required this.currency,
    required this.pricing,
    this.coupon,
  });

  bool get isEmpty => items.isEmpty;
  bool get isNotEmpty => items.isNotEmpty;

  factory CartApiModel.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final List<CartItemApiModel> parsedItems = [];
    if (rawItems is List) {
      parsedItems.addAll(
        rawItems
            .whereType<Map<String, dynamic>>()
            .map((item) => CartItemApiModel.fromJson(item)),
      );
    }

    return CartApiModel(
      cartId: _asInt(json['cart_id']) ?? 0,
      items: parsedItems,
      itemsCount: _asInt(json['items_count']) ?? parsedItems.length,
      totalQuantity: _asInt(json['total_quantity']) ??
          parsedItems.fold(0, (sum, i) => sum + i.quantity),
      currency: json['currency'] is Map<String, dynamic>
          ? CartCurrencyModel.fromJson(json['currency'] as Map<String, dynamic>)
          : const CartCurrencyModel(
              id: 1, code: 'YER', symbol: 'ر.ي', isBase: true),
      pricing: json['pricing'] is Map<String, dynamic>
          ? CartPricingModel.fromJson(json['pricing'] as Map<String, dynamic>)
          : const CartPricingModel(
              itemsSubtotal: 0.0,
              itemDiscountsTotal: 0.0,
              couponDiscount: 0.0,
              estimatedTotal: 0.0,
            ),
      coupon: json['coupon'] is Map<String, dynamic>
          ? CartCouponModel.fromJson(json['coupon'] as Map<String, dynamic>)
          : null,
    );
  }

  factory CartApiModel.empty() {
    return const CartApiModel(
      cartId: 0,
      items: [],
      itemsCount: 0,
      totalQuantity: 0,
      currency:
          CartCurrencyModel(id: 1, code: 'YER', symbol: 'ر.ي', isBase: true),
      pricing: CartPricingModel(
        itemsSubtotal: 0.0,
        itemDiscountsTotal: 0.0,
        couponDiscount: 0.0,
        estimatedTotal: 0.0,
      ),
      coupon: null,
    );
  }

  @override
  List<Object?> get props => [
        cartId,
        items,
        itemsCount,
        totalQuantity,
        currency,
        pricing,
        coupon,
      ];
}

int? _asInt(Object? value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}

double? _asDouble(Object? value) {
  if (value == null) return null;
  if (value is double) return value;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}
