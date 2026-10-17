import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';

class CustomerOrderModel extends Equatable {
  final int id;
  final String orderNumber;
  final String status;
  final String statusLabel;
  final CustomerOrderDeliveryAddressModel? deliveryAddress;
  final String paymentMethodCode;
  final String paymentMethodLabel;
  final String? paymentStatus;
  final String? paymentProviderReference;
  final String shippingMethodCode;
  final String shippingMethodLabel;
  final bool giftWrappingRequested;
  final String? giftMessage;
  final List<CustomerOrderItemModel> items;
  final CustomerOrderPricingModel pricing;
  final CustomerOrderCurrencyModel currency;
  final String? notes;
  final DateTime? createdAt;
  final DateTime? confirmedAt;

  const CustomerOrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.statusLabel,
    this.deliveryAddress,
    required this.paymentMethodCode,
    required this.paymentMethodLabel,
    this.paymentStatus,
    this.paymentProviderReference,
    required this.shippingMethodCode,
    required this.shippingMethodLabel,
    required this.giftWrappingRequested,
    this.giftMessage,
    required this.items,
    required this.pricing,
    required this.currency,
    this.notes,
    this.createdAt,
    this.confirmedAt,
  });

  factory CustomerOrderModel.fromJson(Map<String, dynamic> json) {
    final payment = json['payment_method'] as Map<String, dynamic>?;
    final shipping = json['shipping_method'] as Map<String, dynamic>?;
    final gift = json['gift_wrapping'] as Map<String, dynamic>?;

    return CustomerOrderModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      orderNumber: json['order_number'] as String? ?? '',
      status: json['status'] as String? ?? 'confirmed',
      statusLabel: json['status_label'] as String? ?? 'مؤكد',
      deliveryAddress: json['delivery_address'] != null
          ? CustomerOrderDeliveryAddressModel.fromJson(
              json['delivery_address'] as Map<String, dynamic>)
          : null,
      paymentMethodCode: payment?['code'] as String? ?? 'cod',
      paymentMethodLabel:
          payment?['name_ar'] as String? ?? 'الدفع عند الاستلام',
      paymentStatus: json['payment_status'] as String?,
      paymentProviderReference: json['payment_provider_reference'] as String?,
      shippingMethodCode: shipping?['code'] as String? ?? 'standard',
      shippingMethodLabel:
          shipping?['name_ar'] as String? ?? 'توصيل قياسي',
      giftWrappingRequested: gift?['requested'] as bool? ?? false,
      giftMessage: gift?['message'] as String?,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) =>
                  CustomerOrderItemModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      pricing: json['pricing'] != null
          ? CustomerOrderPricingModel.fromJson(
              json['pricing'] as Map<String, dynamic>)
          : const CustomerOrderPricingModel.empty(),
      currency: json['currency'] != null
          ? CustomerOrderCurrencyModel.fromJson(
              json['currency'] as Map<String, dynamic>)
          : const CustomerOrderCurrencyModel.defaultCurrency(),
      notes: json['notes'] as String?,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      confirmedAt: json['confirmed_at'] != null
          ? DateTime.tryParse(json['confirmed_at'].toString())
          : null,
    );
  }

  @override
  List<Object?> get props => [
        id,
        orderNumber,
        status,
        statusLabel,
        deliveryAddress,
        paymentMethodCode,
        paymentMethodLabel,
        paymentStatus,
        paymentProviderReference,
        shippingMethodCode,
        shippingMethodLabel,
        giftWrappingRequested,
        giftMessage,
        items,
        pricing,
        currency,
        notes,
        createdAt,
        confirmedAt,
      ];
}

class CustomerOrderDeliveryAddressModel extends Equatable {
  final String? contactName;
  final String? contactPhone;
  final String? governorate;
  final String? city;
  final String? district;
  final String? neighborhood;
  final String? street;
  final String? building;
  final String? floor;
  final String? apartment;
  final String? landmark;
  final String? notes;
  final String? formattedAddress;

  const CustomerOrderDeliveryAddressModel({
    this.contactName,
    this.contactPhone,
    this.governorate,
    this.city,
    this.district,
    this.neighborhood,
    this.street,
    this.building,
    this.floor,
    this.apartment,
    this.landmark,
    this.notes,
    this.formattedAddress,
  });

  factory CustomerOrderDeliveryAddressModel.fromJson(Map<String, dynamic> json) {
    return CustomerOrderDeliveryAddressModel(
      contactName: json['contact_name'] as String?,
      contactPhone: json['contact_phone'] as String?,
      governorate: json['governorate'] as String?,
      city: json['city'] as String?,
      district: json['district'] as String?,
      neighborhood: json['neighborhood'] as String?,
      street: json['street'] as String?,
      building: json['building'] as String?,
      floor: json['floor'] as String?,
      apartment: json['apartment'] as String?,
      landmark: json['landmark'] as String?,
      notes: json['notes'] as String?,
      formattedAddress: json['formatted_address'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        contactName,
        contactPhone,
        governorate,
        city,
        district,
        neighborhood,
        street,
        building,
        floor,
        apartment,
        landmark,
        notes,
        formattedAddress,
      ];
}

class CustomerOrderItemModel extends Equatable {
  final int id;
  final int productVariantId;
  final String productName;
  final String variantName;
  final String sku;
  final String fulfillmentMode;
  final double quantity;
  final double unitPrice;
  final double originalUnitPrice;
  final double lineSubtotal;
  final double discountAmount;
  final double lineTotal;

  const CustomerOrderItemModel({
    required this.id,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.sku,
    required this.fulfillmentMode,
    required this.quantity,
    required this.unitPrice,
    required this.originalUnitPrice,
    required this.lineSubtotal,
    required this.discountAmount,
    required this.lineTotal,
  });

  factory CustomerOrderItemModel.fromJson(Map<String, dynamic> json) {
    return CustomerOrderItemModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      productVariantId: (json['product_variant_id'] as num?)?.toInt() ?? 0,
      productName: json['product_name'] as String? ?? '',
      variantName: json['variant_name'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      fulfillmentMode: json['fulfillment_mode'] as String? ?? 'stocked',
      quantity: (json['quantity'] as num?)?.toDouble() ?? 0.0,
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0.0,
      originalUnitPrice:
          (json['original_unit_price'] as num?)?.toDouble() ??
              (json['unit_price'] as num?)?.toDouble() ??
              0.0,
      lineSubtotal: (json['line_subtotal'] as num?)?.toDouble() ?? 0.0,
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0.0,
      lineTotal: (json['line_total'] as num?)?.toDouble() ?? 0.0,
    );
  }

  @override
  List<Object?> get props => [
        id,
        productVariantId,
        productName,
        variantName,
        sku,
        fulfillmentMode,
        quantity,
        unitPrice,
        originalUnitPrice,
        lineSubtotal,
        discountAmount,
        lineTotal,
      ];
}

class CustomerOrderPricingModel extends Equatable {
  final double subtotal;
  final double discountAmount;
  final double promotionDiscountTotal;
  final double couponDiscountTotal;
  final double bundleDiscountTotal;
  final double deliveryFee;
  final double giftWrappingFee;
  final double total;
  final String? couponCode;

  const CustomerOrderPricingModel({
    required this.subtotal,
    required this.discountAmount,
    required this.promotionDiscountTotal,
    required this.couponDiscountTotal,
    required this.bundleDiscountTotal,
    required this.deliveryFee,
    required this.giftWrappingFee,
    required this.total,
    this.couponCode,
  });

  const CustomerOrderPricingModel.empty()
      : subtotal = 0.0,
        discountAmount = 0.0,
        promotionDiscountTotal = 0.0,
        couponDiscountTotal = 0.0,
        bundleDiscountTotal = 0.0,
        deliveryFee = 0.0,
        giftWrappingFee = 0.0,
        total = 0.0,
        couponCode = null;

  factory CustomerOrderPricingModel.fromJson(Map<String, dynamic> json) {
    return CustomerOrderPricingModel(
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0.0,
      promotionDiscountTotal:
          (json['promotion_discount_total'] as num?)?.toDouble() ?? 0.0,
      couponDiscountTotal:
          (json['coupon_discount_total'] as num?)?.toDouble() ?? 0.0,
      bundleDiscountTotal:
          (json['bundle_discount_total'] as num?)?.toDouble() ?? 0.0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
      giftWrappingFee: (json['gift_wrapping_fee'] as num?)?.toDouble() ?? 0.0,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      couponCode: json['coupon_code'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        subtotal,
        discountAmount,
        promotionDiscountTotal,
        couponDiscountTotal,
        bundleDiscountTotal,
        deliveryFee,
        giftWrappingFee,
        total,
        couponCode,
      ];
}

class CustomerOrderCurrencyModel extends Equatable {
  final int id;
  final String code;
  final String symbol;

  const CustomerOrderCurrencyModel({
    required this.id,
    required this.code,
    required this.symbol,
  });

  const CustomerOrderCurrencyModel.defaultCurrency()
      : id = 1,
        code = 'YER',
        symbol = 'ر.ي';

  factory CustomerOrderCurrencyModel.fromJson(Map<String, dynamic> json) {
    return CustomerOrderCurrencyModel(
      id: (json['id'] as num?)?.toInt() ?? 1,
      code: json['code'] as String? ?? 'YER',
      symbol: CurrencyFormatter.resolveDisplaySymbol(
        symbol: json['symbol'] as String?,
        code: json['code'] as String? ?? 'YER',
      ),
    );
  }

  @override
  List<Object?> get props => [id, code, symbol];
}
