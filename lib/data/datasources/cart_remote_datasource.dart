import 'package:dio/dio.dart';

import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/cart/cart_api_model.dart';

abstract interface class CartRemoteDataSource {
  Future<CartApiModel> getCart();
  Future<CartApiModel> addItem({
    required int productVariantId,
    int quantity = 1,
    String? idempotencyKey,
  });
  Future<CartApiModel> updateItemQuantity(
      {required int itemId, required int quantity});
  Future<CartApiModel> removeItem(int itemId);
  Future<CartApiModel> clearCart();
  Future<CartApiModel> applyCoupon(String code);
  Future<CartApiModel> removeCoupon();
}

final class CartRemoteDataSourceImpl implements CartRemoteDataSource {
  final DioClient _dioClient;

  CartRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<CartApiModel> getCart() async {
    final response = await _dioClient.get(ApiEndpoints.cart);
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> addItem({
    required int productVariantId,
    int quantity = 1,
    String? idempotencyKey,
  }) async {
    final response = await _dioClient.post(
      ApiEndpoints.cartItems,
      data: {
        'product_variant_id': productVariantId,
        'quantity': quantity,
      },
      options: idempotencyKey == null
          ? null
          : Options(headers: {'Idempotency-Key': idempotencyKey}),
    );
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> updateItemQuantity(
      {required int itemId, required int quantity}) async {
    final response = await _dioClient.patch(
      ApiEndpoints.cartItem(itemId),
      data: {'quantity': quantity},
    );
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> removeItem(int itemId) async {
    final response = await _dioClient.delete(
      ApiEndpoints.cartItem(itemId),
    );
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> clearCart() async {
    final response = await _dioClient.delete(ApiEndpoints.cart);
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> applyCoupon(String code) async {
    final response = await _dioClient.post(
      ApiEndpoints.cartCoupon,
      data: {'code': code.trim()},
    );
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<CartApiModel> removeCoupon() async {
    final response = await _dioClient.delete(ApiEndpoints.cartCoupon);
    return ApiEnvelope<CartApiModel>.parse(
      response.data,
      (data) => CartApiModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }
}
