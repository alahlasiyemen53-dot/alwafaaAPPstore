import '../datasources/checkout_remote_datasource.dart';
import '../models/checkout/checkout_preview_model.dart';
import '../models/checkout/customer_order_model.dart';
import '../models/checkout/payment_attempt_model.dart';

abstract interface class CheckoutRepository {
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

final class CheckoutRepositoryImpl implements CheckoutRepository {
  final CheckoutRemoteDataSource _remoteDataSource;

  CheckoutRepositoryImpl({CheckoutRemoteDataSource? remoteDataSource})
      : _remoteDataSource =
            remoteDataSource ?? CheckoutRemoteDataSourceImpl();

  @override
  Future<CheckoutPreviewModel> getPreview({
    int? customerAddressId,
    String? couponCode,
    bool? giftWrappingRequested,
    String? giftMessage,
    String? paymentMethod,
    String? shippingMethod,
    List<int>? cartItemIds,
  }) =>
      _remoteDataSource.getPreview(
        customerAddressId: customerAddressId,
        couponCode: couponCode,
        giftWrappingRequested: giftWrappingRequested,
        giftMessage: giftMessage,
        paymentMethod: paymentMethod,
        shippingMethod: shippingMethod,
        cartItemIds: cartItemIds,
      );

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
  }) =>
      _remoteDataSource.createOrder(
        customerAddressId: customerAddressId,
        paymentMethod: paymentMethod,
        idempotencyKey: idempotencyKey,
        shippingMethod: shippingMethod,
        couponCode: couponCode,
        giftWrappingRequested: giftWrappingRequested,
        giftMessage: giftMessage,
        notes: notes,
        payment: payment,
        cartItemIds: cartItemIds,
      );

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
  }) =>
      _remoteDataSource.initiatePayment(
        customerAddressId: customerAddressId,
        paymentMethod: paymentMethod,
        shippingMethod: shippingMethod,
        couponCode: couponCode,
        giftWrappingRequested: giftWrappingRequested,
        giftMessage: giftMessage,
        notes: notes,
        cartItemIds: cartItemIds,
      );

  @override
  Future<CustomerOrderModel> confirmPayment({
    required String attemptUuid,
    required String otp,
  }) =>
      _remoteDataSource.confirmPayment(
        attemptUuid: attemptUuid,
        otp: otp,
      );
}
