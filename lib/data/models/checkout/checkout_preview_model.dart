import 'package:equatable/equatable.dart';
import 'package:store/core/utils/currency_formatter.dart';

class CheckoutPreviewModel extends Equatable {
  final bool canCheckout;
  final List<String> warnings;
  final CheckoutAddressModel? deliveryAddress;
  final List<CheckoutShippingMethodModel> shippingMethods;
  final String? selectedShippingMethod;
  final List<CheckoutPaymentMethodModel> paymentMethods;
  final CheckoutGiftWrappingModel? giftWrapping;
  final List<CheckoutItemModel> items;
  final int itemsCount;
  final int totalQuantity;
  final CheckoutPricingModel pricing;
  final CheckoutCurrencyModel currency;

  const CheckoutPreviewModel({
    required this.canCheckout,
    required this.warnings,
    this.deliveryAddress,
    required this.shippingMethods,
    this.selectedShippingMethod,
    required this.paymentMethods,
    this.giftWrapping,
    required this.items,
    required this.itemsCount,
    required this.totalQuantity,
    required this.pricing,
    required this.currency,
  });

  factory CheckoutPreviewModel.fromJson(Map<String, dynamic> json) {
    return CheckoutPreviewModel(
      canCheckout: json['can_checkout'] as bool? ?? false,
      warnings: (json['warnings'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      deliveryAddress: json['delivery_address'] != null
          ? CheckoutAddressModel.fromJson(
              json['delivery_address'] as Map<String, dynamic>)
          : null,
      shippingMethods: (json['shipping_methods'] as List<dynamic>?)
              ?.map((e) => CheckoutShippingMethodModel.fromJson(
                  e as Map<String, dynamic>))
              .toList() ??
          const [],
      selectedShippingMethod: json['selected_shipping_method'] as String?,
      paymentMethods: (json['payment_methods'] as List<dynamic>?)
              ?.map((e) => CheckoutPaymentMethodModel.fromJson(
                  e as Map<String, dynamic>))
              .toList() ??
          const [],
      giftWrapping: json['gift_wrapping'] != null
          ? CheckoutGiftWrappingModel.fromJson(
              json['gift_wrapping'] as Map<String, dynamic>)
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) =>
                  CheckoutItemModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      itemsCount: (json['items_count'] as num?)?.toInt() ?? 0,
      totalQuantity: (json['total_quantity'] as num?)?.toInt() ?? 0,
      pricing: json['pricing'] != null
          ? CheckoutPricingModel.fromJson(
              json['pricing'] as Map<String, dynamic>)
          : const CheckoutPricingModel.empty(),
      currency: json['currency'] != null
          ? CheckoutCurrencyModel.fromJson(
              json['currency'] as Map<String, dynamic>)
          : const CheckoutCurrencyModel.defaultCurrency(),
    );
  }

  @override
  List<Object?> get props => [
        canCheckout,
        warnings,
        deliveryAddress,
        shippingMethods,
        selectedShippingMethod,
        paymentMethods,
        giftWrapping,
        items,
        itemsCount,
        totalQuantity,
        pricing,
        currency,
      ];
}

class CheckoutAddressModel extends Equatable {
  final int id;
  final String? label;
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
  final double deliveryFee;

  const CheckoutAddressModel({
    required this.id,
    this.label,
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
    this.deliveryFee = 0.0,
  });

  factory CheckoutAddressModel.fromJson(Map<String, dynamic> json) {
    return CheckoutAddressModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      label: json['label'] as String?,
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
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
    );
  }

  @override
  List<Object?> get props => [
        id,
        label,
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
        deliveryFee,
      ];
}

class CheckoutShippingMethodModel extends Equatable {
  final int? id;
  final String code;
  final String nameAr;
  final String nameEn;
  final double cost;
  final String estimatedDelivery;
  final bool enabled;

  const CheckoutShippingMethodModel({
    this.id,
    required this.code,
    required this.nameAr,
    required this.nameEn,
    required this.cost,
    required this.estimatedDelivery,
    this.enabled = true,
  });

  factory CheckoutShippingMethodModel.fromJson(Map<String, dynamic> json) {
    return CheckoutShippingMethodModel(
      id: json['id'] as int?,
      code: json['code'] as String? ?? 'standard',
      nameAr: json['name_ar'] as String? ?? 'توصيل قياسي',
      nameEn: json['name_en'] as String? ?? 'Standard Delivery',
      cost: (json['cost'] as num?)?.toDouble() ?? 0.0,
      estimatedDelivery:
          json['estimated_delivery'] as String? ?? 'خلال 24-48 ساعة',
      enabled: json['enabled'] as bool? ?? true,
    );
  }

  @override
  List<Object?> get props =>
      [id, code, nameAr, nameEn, cost, estimatedDelivery, enabled];
}

class CheckoutPaymentMethodModel extends Equatable {
  final String code;
  final String? providerCode;
  final String nameAr;
  final String nameEn;
  final String? descriptionAr;
  final String? descriptionEn;
  final String? instructionsAr;
  final String? instructionsEn;
  final String channel;
  final String flowType; // none | step1 | step2
  final String? logoUrl;
  final bool enabled;

  const CheckoutPaymentMethodModel({
    required this.code,
    this.providerCode,
    required this.nameAr,
    required this.nameEn,
    this.descriptionAr,
    this.descriptionEn,
    this.instructionsAr,
    this.instructionsEn,
    this.channel = 'offline',
    this.flowType = 'none',
    this.logoUrl,
    this.enabled = true,
  });

  bool get isStep1 => flowType == 'step1';
  bool get isStep2 => flowType == 'step2';

  factory CheckoutPaymentMethodModel.fromJson(Map<String, dynamic> json) {
    return CheckoutPaymentMethodModel(
      code: json['code'] as String? ?? 'cod',
      providerCode: json['provider_code'] as String?,
      nameAr: json['name_ar'] as String? ?? 'الدفع عند الاستلام',
      nameEn: json['name_en'] as String? ?? 'Cash on Delivery',
      descriptionAr: json['description_ar'] as String?,
      descriptionEn: json['description_en'] as String?,
      instructionsAr: json['instructions_ar'] as String?,
      instructionsEn: json['instructions_en'] as String?,
      channel: json['channel'] as String? ?? 'offline',
      flowType: json['flow_type'] as String? ?? 'none',
      logoUrl: json['logo_url'] as String?,
      enabled: json['enabled'] as bool? ?? true,
    );
  }

  @override
  List<Object?> get props => [
        code,
        providerCode,
        nameAr,
        nameEn,
        descriptionAr,
        descriptionEn,
        instructionsAr,
        instructionsEn,
        channel,
        flowType,
        logoUrl,
        enabled,
      ];
}

class CheckoutGiftWrappingModel extends Equatable {
  final bool enabled;
  final bool messageEnabled;
  final int maxLength;
  final bool requested;
  final bool isFree;
  final double fee;
  final String? message;

  const CheckoutGiftWrappingModel({
    this.enabled = false,
    this.messageEnabled = false,
    this.maxLength = 250,
    required this.requested,
    this.isFree = true,
    this.fee = 0.0,
    this.message,
  });

  factory CheckoutGiftWrappingModel.fromJson(Map<String, dynamic> json) {
    return CheckoutGiftWrappingModel(
      enabled: json['enabled'] as bool? ?? false,
      messageEnabled: json['message_enabled'] as bool? ?? false,
      maxLength: (json['max_length'] as num?)?.toInt() ?? 250,
      requested: json['requested'] as bool? ?? false,
      isFree: json['is_free'] as bool? ?? true,
      fee: (json['fee'] as num?)?.toDouble() ?? 0.0,
      message: json['message'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        enabled,
        messageEnabled,
        maxLength,
        requested,
        isFree,
        fee,
        message,
      ];
}

class CheckoutItemModel extends Equatable {
  final int productVariantId;
  final String productName;
  final String variantName;
  final String sku;
  final String fulfillmentMode; // 'stocked' | 'on_demand'
  final double quantity;
  final double unitPrice;
  final double originalUnitPrice;
  final double lineSubtotal;
  final double discountAmount;
  final double lineTotal;
  final bool isAvailable;
  final String? warning;
  final String? primaryImageUrl;

  const CheckoutItemModel({
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
    required this.isAvailable,
    this.warning,
    this.primaryImageUrl,
  });

  factory CheckoutItemModel.fromJson(Map<String, dynamic> json) {
    return CheckoutItemModel(
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
      isAvailable: json['is_available'] as bool? ?? true,
      warning: json['warning'] as String?,
      primaryImageUrl: json['primary_image_url'] as String?,
    );
  }

  @override
  List<Object?> get props => [
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
        isAvailable,
        warning,
        primaryImageUrl,
      ];
}

class CheckoutPricingModel extends Equatable {
  final double subtotal;
  final double itemsDiscount;
  final double couponDiscount;
  final double discountAmount;
  final double deliveryFee;
  final double giftWrappingFee;
  final double total;
  final String? couponCode;

  const CheckoutPricingModel({
    required this.subtotal,
    required this.itemsDiscount,
    required this.couponDiscount,
    required this.discountAmount,
    required this.deliveryFee,
    required this.giftWrappingFee,
    required this.total,
    this.couponCode,
  });

  const CheckoutPricingModel.empty()
      : subtotal = 0.0,
        itemsDiscount = 0.0,
        couponDiscount = 0.0,
        discountAmount = 0.0,
        deliveryFee = 0.0,
        giftWrappingFee = 0.0,
        total = 0.0,
        couponCode = null;

  factory CheckoutPricingModel.fromJson(Map<String, dynamic> json) {
    return CheckoutPricingModel(
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
      itemsDiscount: (json['items_discount'] as num?)?.toDouble() ?? 0.0,
      couponDiscount: (json['coupon_discount'] as num?)?.toDouble() ?? 0.0,
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0.0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
      giftWrappingFee: (json['gift_wrapping_fee'] as num?)?.toDouble() ?? 0.0,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      couponCode: json['coupon_code'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        subtotal,
        itemsDiscount,
        couponDiscount,
        discountAmount,
        deliveryFee,
        giftWrappingFee,
        total,
        couponCode,
      ];
}

class CheckoutCurrencyModel extends Equatable {
  final int id;
  final String code;
  final String symbol;

  const CheckoutCurrencyModel({
    required this.id,
    required this.code,
    required this.symbol,
  });

  const CheckoutCurrencyModel.defaultCurrency()
      : id = 1,
        code = 'YER',
        symbol = 'ر.ي';

  factory CheckoutCurrencyModel.fromJson(Map<String, dynamic> json) {
    return CheckoutCurrencyModel(
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
