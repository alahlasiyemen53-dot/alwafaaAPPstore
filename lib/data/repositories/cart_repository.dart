import '../datasources/cart_remote_datasource.dart';
import '../models/cart/cart_api_model.dart';

abstract interface class CartRepository {
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

final class CartRepositoryImpl implements CartRepository {
  final CartRemoteDataSource _remoteDataSource;

  CartRepositoryImpl({CartRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? CartRemoteDataSourceImpl();

  @override
  Future<CartApiModel> getCart() {
    return _remoteDataSource.getCart();
  }

  @override
  Future<CartApiModel> addItem({
    required int productVariantId,
    int quantity = 1,
    String? idempotencyKey,
  }) {
    return _remoteDataSource.addItem(
      productVariantId: productVariantId,
      quantity: quantity,
      idempotencyKey: idempotencyKey,
    );
  }

  @override
  Future<CartApiModel> updateItemQuantity(
      {required int itemId, required int quantity}) {
    return _remoteDataSource.updateItemQuantity(
        itemId: itemId, quantity: quantity);
  }

  @override
  Future<CartApiModel> removeItem(int itemId) {
    return _remoteDataSource.removeItem(itemId);
  }

  @override
  Future<CartApiModel> clearCart() {
    return _remoteDataSource.clearCart();
  }

  @override
  Future<CartApiModel> applyCoupon(String code) {
    return _remoteDataSource.applyCoupon(code);
  }

  @override
  Future<CartApiModel> removeCoupon() {
    return _remoteDataSource.removeCoupon();
  }
}
