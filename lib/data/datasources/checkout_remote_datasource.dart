import 'package:dio/dio.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/checkout/checkout_preview_model.dart';
import '../models/checkout/customer_order_model.dart';
import '../models/checkout/payment_attempt_model.dart';

abstract interface class CheckoutRemoteDataSource {
  Future<CheckoutPreviewModel> getPreview({
    int? customerAddressId,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? paymentMethod,
    String? shippingMethod,
    List<int>? cartItemIds,
  });

  Future<CustomerOrderModel> createOrder({
    required int customerAddressId,
    required String paymentMethod,
    required String idempotencyKey,
    String? shippingMethod,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? notes,
    Map<String, dynamic>? payment,
    List<int>? cartItemIds,
  });

  Future<PaymentAttemptModel> initiatePayment({
    required int customerAddressId,
    required String paymentMethod,
    String? shippingMethod,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? notes,
    List<int>? cartItemIds,
  });

  Future<CustomerOrderModel> confirmPayment({
    required String attemptUuid,
    required String otp,
  });
}

final class CheckoutRemoteDataSourceImpl implements CheckoutRemoteDataSource {
  final DioClient _dioClient;

  CheckoutRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<CheckoutPreviewModel> getPreview({
    int? customerAddressId,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? paymentMethod,
    String? shippingMethod,
    List<int>? cartItemIds,
  }) async {
    final payload = <String, dynamic>{};
    if (customerAddressId != null) {
      payload['customer_address_id'] = customerAddressId;
    }
    if (couponCode != null && couponCode.trim().isNotEmpty) {
      payload['coupon_code'] = couponCode.trim();
    }
    if (giftWrappingRequested != null) {
      payload['gift_wrapping_requested'] = giftWrappingRequested;
    }
    if (giftMessage != null && giftMessage.trim().isNotEmpty) {
      payload['gift_message'] = giftMessage.trim();
    }
    if (paymentMethod != null && paymentMethod.isNotEmpty) {
      payload['payment_method'] = paymentMethod;
    }
    if (shippingMethod != null && shippingMethod.isNotEmpty) {
      payload['shipping_method'] = shippingMethod;
    }
    if (cartItemIds != null && cartItemIds.isNotEmpty) {
      payload['cart_item_ids'] = cartItemIds;
    }

    final response = await _dioClient.post(
      ApiEndpoints.checkoutPreview,
      data: payload,
    );

    return ApiEnvelope<CheckoutPreviewModel>.parse(
      response.data,
      (data) => CheckoutPreviewModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CustomerOrderModel> createOrder({
    required int customerAddressId,
    required String paymentMethod,
    required String idempotencyKey,
    String? shippingMethod,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? notes,
    Map<String, dynamic>? payment,
    List<int>? cartItemIds,
  }) async {
    final payload = _sharedCheckoutPayload(
      customerAddressId: customerAddressId,
      paymentMethod: paymentMethod,
      shippingMethod: shippingMethod,
      couponCode: couponCode,
      giftWrappingRequested: giftWrappingRequested,
      giftMessage: giftMessage,
      notes: notes,
      cartItemIds: cartItemIds,
    );

    if (payment != null && payment.isNotEmpty) {
      payload['payment'] = payment;
    }

    final response = await _dioClient.post(
      ApiEndpoints.orders,
      data: payload,
      options: Options(
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      ),
    );

    return ApiEnvelope<CustomerOrderModel>.parse(
      response.data,
      (data) => CustomerOrderModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<PaymentAttemptModel> initiatePayment({
    required int customerAddressId,
    required String paymentMethod,
    String? shippingMethod,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? notes,
    List<int>? cartItemIds,
  }) async {
    final payload = _sharedCheckoutPayload(
      customerAddressId: customerAddressId,
      paymentMethod: paymentMethod,
      shippingMethod: shippingMethod,
      couponCode: couponCode,
      giftWrappingRequested: giftWrappingRequested,
      giftMessage: giftMessage,
      notes: notes,
      cartItemIds: cartItemIds,
    );

    final response = await _dioClient.post(
      ApiEndpoints.paymentsInitiate,
      data: payload,
    );

    return ApiEnvelope<PaymentAttemptModel>.parse(
      response.data,
      (data) => PaymentAttemptModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CustomerOrderModel> confirmPayment({
    required String attemptUuid,
    required String otp,
  }) async {
    final response = await _dioClient.post(
      ApiEndpoints.paymentConfirm(attemptUuid),
      data: {'otp': otp},
    );

    return ApiEnvelope<CustomerOrderModel>.parse(
      response.data,
      (data) => CustomerOrderModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  Map<String, dynamic> _sharedCheckoutPayload({
    required int customerAddressId,
    required String paymentMethod,
    String? shippingMethod,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? notes,
    List<int>? cartItemIds,
  }) {
    final payload = <String, dynamic>{
      'customer_address_id': customerAddressId,
      'payment_method': paymentMethod,
    };

    if (shippingMethod != null && shippingMethod.isNotEmpty) {
      payload['shipping_method'] = shippingMethod;
    }
    if (couponCode != null && couponCode.trim().isNotEmpty) {
      payload['coupon_code'] = couponCode.trim();
    }
    if (giftWrappingRequested != null) {
      payload['gift_wrapping_requested'] = giftWrappingRequested;
    }
    if (giftMessage != null && giftMessage.trim().isNotEmpty) {
      payload['gift_message'] = giftMessage.trim();
    }
    if (notes != null && notes.trim().isNotEmpty) {
      payload['notes'] = notes.trim();
    }
    if (cartItemIds != null && cartItemIds.isNotEmpty) {
      payload['cart_item_ids'] = cartItemIds;
    }

    return payload;
  }
}
