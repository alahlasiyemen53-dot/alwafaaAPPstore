import '../datasources/orders_remote_datasource.dart';
import '../models/orders/order_detail_model.dart';
import '../models/orders/order_summary_model.dart';
import '../models/orders/order_tracking_model.dart';
import '../models/orders/reorder_result_model.dart';

abstract interface class OrdersRepository {
  Future<OrdersResponseModel> getOrders({int page = 1, int perPage = 15});
  Future<OrderDetailModel> getOrderDetail(int orderId);
  Future<OrderTrackingModel> getOrderTracking(int orderId);
  Future<ReorderResultModel> reorder(int orderId);
  Future<OrderDetailModel> confirmDelivery(int orderId);
  Future<void> submitRatings({
    required int orderId,
    required List<Map<String, dynamic>> products,
    int? driverRating,
    String? driverComment,
  });
}

final class OrdersRepositoryImpl implements OrdersRepository {
  final OrdersRemoteDataSource _remoteDataSource;

  OrdersRepositoryImpl({OrdersRemoteDataSource? remoteDataSource})
      : _remoteDataSource =
            remoteDataSource ?? OrdersRemoteDataSourceImpl();

  @override
  Future<OrdersResponseModel> getOrders({int page = 1, int perPage = 15}) =>
      _remoteDataSource.getOrders(page: page, perPage: perPage);

  @override
  Future<OrderDetailModel> getOrderDetail(int orderId) =>
      _remoteDataSource.getOrderDetail(orderId);

  @override
  Future<OrderTrackingModel> getOrderTracking(int orderId) =>
      _remoteDataSource.getOrderTracking(orderId);

  @override
  Future<ReorderResultModel> reorder(int orderId) =>
      _remoteDataSource.reorder(orderId);

  @override
  Future<OrderDetailModel> confirmDelivery(int orderId) =>
      _remoteDataSource.confirmDelivery(orderId);

  @override
  Future<void> submitRatings({
    required int orderId,
    required List<Map<String, dynamic>> products,
    int? driverRating,
    String? driverComment,
  }) =>
      _remoteDataSource.submitRatings(
        orderId: orderId,
        products: products,
        driverRating: driverRating,
        driverComment: driverComment,
      );
}
