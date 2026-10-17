import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';

class OrderDetailModel extends Equatable {
  final int id;
  final String orderNumber;
  final String statusCode;
  final String statusLabel;
  final DateTime? occurredAt;
  final DateTime? createdAt;
  final DateTime? confirmedAt;
  final DateTime? customerConfirmDeadlineAt;
  final bool canConfirmDelivery;
  final bool canRate;
  final OrderDetailAddressModel? deliveryAddress;
  final OrderDetailGiftWrappingModel giftWrapping;
  final String paymentMethodCode;
  final String paymentMethodLabel;
  final String shippingMethodCode;
  final String shippingMethodLabel;
  final List<OrderDetailItemModel> items;
  final OrderDetailPricingModel pricing;
  final String currencyCode;
  final String currencySymbol;

  const OrderDetailModel({
    required this.id,
    required this.orderNumber,
    required this.statusCode,
    required this.statusLabel,
    this.occurredAt,
    this.createdAt,
    this.confirmedAt,
    this.customerConfirmDeadlineAt,
    this.canConfirmDelivery = false,
    this.canRate = false,
    this.deliveryAddress,
    required this.giftWrapping,
    required this.paymentMethodCode,
    required this.paymentMethodLabel,
    required this.shippingMethodCode,
    required this.shippingMethodLabel,
    required this.items,
    required this.pricing,
    required this.currencyCode,
    required this.currencySymbol,
  });

  factory OrderDetailModel.fromJson(Map<String, dynamic> json) {
    final statusObj = json['status'] as Map<String, dynamic>?;
    final payment = json['payment_method'] as Map<String, dynamic>?;
    final shipping = json['shipping_method'] as Map<String, dynamic>?;
    final gift = json['gift_wrapping'] as Map<String, dynamic>?;
    final currency = json['currency'] as Map<String, dynamic>?;

    return OrderDetailModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      orderNumber: json['order_number'] as String? ?? '',
      statusCode: statusObj?['status'] as String? ??
          json['status'] as String? ??
          'under_review',
      statusLabel: statusObj?['label_ar'] as String? ?? 'قيد المراجعة',
      occurredAt: statusObj?['occurred_at'] != null
          ? DateTime.tryParse(statusObj!['occurred_at'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      confirmedAt: json['confirmed_at'] != null
          ? DateTime.tryParse(json['confirmed_at'].toString())
          : null,
      customerConfirmDeadlineAt: json['customer_confirm_deadline_at'] != null
          ? DateTime.tryParse(json['customer_confirm_deadline_at'].toString())
          : (statusObj?['customer_confirm_deadline_at'] != null
              ? DateTime.tryParse(
                  statusObj!['customer_confirm_deadline_at'].toString())
              : null),
      canConfirmDelivery: json['can_confirm_delivery'] as bool? ??
          statusObj?['can_confirm_delivery'] as bool? ??
          false,
      canRate: json['can_rate'] as bool? ??
          statusObj?['can_rate'] as bool? ??
          false,
      deliveryAddress: json['delivery_address'] != null
          ? OrderDetailAddressModel.fromJson(
              json['delivery_address'] as Map<String, dynamic>)
          : null,
      giftWrapping: gift != null
          ? OrderDetailGiftWrappingModel.fromJson(gift)
          : const OrderDetailGiftWrappingModel.empty(),
      paymentMethodCode: payment?['code'] as String? ?? 'cod',
      paymentMethodLabel:
          payment?['name_ar'] as String? ?? 'الدفع عند الاستلام',
      shippingMethodCode: shipping?['code'] as String? ?? 'standard',
      shippingMethodLabel:
          shipping?['name_ar'] as String? ?? 'توصيل قياسي',
      items: (json['items'] as List<dynamic>?)
              ?.map((e) =>
                  OrderDetailItemModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      pricing: json['pricing'] != null
          ? OrderDetailPricingModel.fromJson(
              json['pricing'] as Map<String, dynamic>)
          : const OrderDetailPricingModel.empty(),
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
        statusCode,
        statusLabel,
        occurredAt,
        createdAt,
        confirmedAt,
        customerConfirmDeadlineAt,
        canConfirmDelivery,
        canRate,
        deliveryAddress,
        giftWrapping,
        paymentMethodCode,
        paymentMethodLabel,
        shippingMethodCode,
        shippingMethodLabel,
        items,
        pricing,
        currencyCode,
        currencySymbol,
      ];
}

class OrderDetailAddressModel extends Equatable {
  final String? contactName;
  final String? contactPhone;
  final String? formattedAddress;

  const OrderDetailAddressModel({
    this.contactName,
    this.contactPhone,
    this.formattedAddress,
  });

  factory OrderDetailAddressModel.fromJson(Map<String, dynamic> json) {
    return OrderDetailAddressModel(
      contactName: json['contact_name'] as String?,
      contactPhone: json['contact_phone'] as String?,
      formattedAddress: json['formatted_address'] as String?,
    );
  }

  @override
  List<Object?> get props => [contactName, contactPhone, formattedAddress];
}

class OrderDetailGiftWrappingModel extends Equatable {
  final bool requested;
  final String? message;
  final bool isFree;

  const OrderDetailGiftWrappingModel({
    required this.requested,
    this.message,
    this.isFree = true,
  });

  const OrderDetailGiftWrappingModel.empty()
      : requested = false,
        message = null,
        isFree = true;

  factory OrderDetailGiftWrappingModel.fromJson(Map<String, dynamic> json) {
    return OrderDetailGiftWrappingModel(
      requested: json['requested'] as bool? ?? false,
      message: json['message'] as String?,
      isFree: json['is_free'] as bool? ?? true,
    );
  }

  @override
  List<Object?> get props => [requested, message, isFree];
}

class OrderDetailItemModel extends Equatable {
  final int productVariantId;
  final int? productId;
  final String productName;
  final String variantName;
  final String sku;
  final String? fulfillmentMode;
  final String? primaryImageUrl;
  final double quantity;
  final double originalUnitPrice;
  final double unitPrice;
  final double lineSubtotal;
  final double discountAmount;
  final double lineTotal;

  const OrderDetailItemModel({
    required this.productVariantId,
    this.productId,
    required this.productName,
    required this.variantName,
    required this.sku,
    this.fulfillmentMode,
    this.primaryImageUrl,
    required this.quantity,
    required this.originalUnitPrice,
    required this.unitPrice,
    required this.lineSubtotal,
    required this.discountAmount,
    required this.lineTotal,
  });

  factory OrderDetailItemModel.fromJson(Map<String, dynamic> json) {
    return OrderDetailItemModel(
      productVariantId: (json['product_variant_id'] as num?)?.toInt() ?? 0,
      productId: (json['product_id'] as num?)?.toInt(),
      productName: json['product_name'] as String? ?? '',
      variantName: json['variant_name'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      fulfillmentMode: json['fulfillment_mode'] as String?,
      primaryImageUrl: json['primary_image_url'] as String?,
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1.0,
      originalUnitPrice:
          (json['original_unit_price'] as num?)?.toDouble() ??
              (json['unit_price'] as num?)?.toDouble() ??
              0.0,
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0.0,
      lineSubtotal: (json['line_subtotal'] as num?)?.toDouble() ?? 0.0,
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0.0,
      lineTotal: (json['line_total'] as num?)?.toDouble() ?? 0.0,
    );
  }

  @override
  List<Object?> get props => [
        productVariantId,
        productId,
        productName,
        variantName,
        sku,
        fulfillmentMode,
        primaryImageUrl,
        quantity,
        originalUnitPrice,
        unitPrice,
        lineSubtotal,
        discountAmount,
        lineTotal,
      ];
}

class OrderDetailPricingModel extends Equatable {
  final double subtotal;
  final double discountAmount;
  final double deliveryFee;
  final double total;

  const OrderDetailPricingModel({
    required this.subtotal,
    required this.discountAmount,
    required this.deliveryFee,
    required this.total,
  });

  const OrderDetailPricingModel.empty()
      : subtotal = 0.0,
        discountAmount = 0.0,
        deliveryFee = 0.0,
        total = 0.0;

  factory OrderDetailPricingModel.fromJson(Map<String, dynamic> json) {
    return OrderDetailPricingModel(
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0.0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
    );
  }

  @override
  List<Object?> get props => [subtotal, discountAmount, deliveryFee, total];
}
