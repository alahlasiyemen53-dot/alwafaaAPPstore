import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/orders/order_detail_model.dart';
import '../models/orders/order_summary_model.dart';
import '../models/orders/order_tracking_model.dart';
import '../models/orders/reorder_result_model.dart';

abstract interface class OrdersRemoteDataSource {
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

final class OrdersRemoteDataSourceImpl implements OrdersRemoteDataSource {
  final DioClient _dioClient;

  OrdersRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<OrdersResponseModel> getOrders({
    int page = 1,
    int perPage = 15,
  }) async {
    final response = await _dioClient.get(
      ApiEndpoints.orders,
      queryParameters: {
        'page': page,
        'per_page': perPage,
      },
    );

    return ApiEnvelope<OrdersResponseModel>.parse(
      response.data,
      (data) => OrdersResponseModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<OrderDetailModel> getOrderDetail(int orderId) async {
    final response = await _dioClient.get(
      ApiEndpoints.orderDetails(orderId),
    );

    return ApiEnvelope<OrderDetailModel>.parse(
      response.data,
      (data) => OrderDetailModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<OrderTrackingModel> getOrderTracking(int orderId) async {
    final response = await _dioClient.get(
      ApiEndpoints.trackOrder(orderId),
    );

    return ApiEnvelope<OrderTrackingModel>.parse(
      response.data,
      (data) => OrderTrackingModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<ReorderResultModel> reorder(int orderId) async {
    final response = await _dioClient.post(ApiEndpoints.orderReorder(orderId));
    return ApiEnvelope<ReorderResultModel>.parse(
      response.data,
      (data) {
        final envelope = response.data;
        final message = envelope is Map<String, dynamic>
            ? envelope['message'] as String?
            : null;
        return ReorderResultModel.fromJson(
          data as Map<String, dynamic>? ?? {},
          message: message,
        );
      },
    ).data;
  }

  @override
  Future<OrderDetailModel> confirmDelivery(int orderId) async {
    final response =
        await _dioClient.post(ApiEndpoints.orderConfirmDelivery(orderId));
    return ApiEnvelope<OrderDetailModel>.parse(
      response.data,
      (data) => OrderDetailModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<void> submitRatings({
    required int orderId,
    required List<Map<String, dynamic>> products,
    int? driverRating,
    String? driverComment,
  }) async {
    await _dioClient.post(
      ApiEndpoints.orderRatings(orderId),
      data: {
        'products': products,
        if (driverRating != null) 'driver_rating': driverRating,
        if (driverComment != null) 'driver_comment': driverComment,
      },
    );
  }
}
