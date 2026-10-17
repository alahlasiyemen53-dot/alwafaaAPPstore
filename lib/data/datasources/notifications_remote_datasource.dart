import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/customer/notification_model.dart';

abstract interface class NotificationsRemoteDataSource {
  Future<NotificationsResponseModel> getNotifications({
    int page = 1,
    int perPage = 20,
  });
  Future<CustomerNotificationModel> markRead(int id);
  Future<int> markAllRead();
}

final class NotificationsRemoteDataSourceImpl
    implements NotificationsRemoteDataSource {
  final DioClient _dioClient;

  NotificationsRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<NotificationsResponseModel> getNotifications({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _dioClient.get(
      ApiEndpoints.notifications,
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return ApiEnvelope<NotificationsResponseModel>.parse(
      response.data,
      (data) => NotificationsResponseModel.fromJson(
        data as Map<String, dynamic>? ?? {},
      ),
    ).data;
  }

  @override
  Future<CustomerNotificationModel> markRead(int id) async {
    final response =
        await _dioClient.patch(ApiEndpoints.notificationRead(id));
    return ApiEnvelope<CustomerNotificationModel>.parse(
      response.data,
      (data) {
        final map = data as Map<String, dynamic>? ?? {};
        return CustomerNotificationModel(
          id: map['id'] as int? ?? id,
          type: '',
          title: '',
          body: '',
          readAt: DateTime.tryParse(map['read_at'] as String? ?? '') ??
              DateTime.now(),
        );
      },
    ).data;
  }

  @override
  Future<int> markAllRead() async {
    final response = await _dioClient.patch(ApiEndpoints.notificationsReadAll);
    return ApiEnvelope<int>.parse(
      response.data,
      (data) {
        if (data is Map<String, dynamic>) {
          return data['updated'] as int? ?? 0;
        }
        return 0;
      },
    ).data;
  }
}
