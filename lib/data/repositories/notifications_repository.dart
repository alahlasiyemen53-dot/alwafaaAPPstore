import '../datasources/notifications_remote_datasource.dart';
import '../models/customer/notification_model.dart';

abstract interface class NotificationsRepository {
  Future<NotificationsResponseModel> getNotifications({
    int page = 1,
    int perPage = 20,
  });
  Future<CustomerNotificationModel> markRead(int id);
  Future<int> markAllRead();
}

final class NotificationsRepositoryImpl implements NotificationsRepository {
  final NotificationsRemoteDataSource _remote;

  NotificationsRepositoryImpl({NotificationsRemoteDataSource? remote})
      : _remote = remote ?? NotificationsRemoteDataSourceImpl();

  @override
  Future<NotificationsResponseModel> getNotifications({
    int page = 1,
    int perPage = 20,
  }) =>
      _remote.getNotifications(page: page, perPage: perPage);

  @override
  Future<CustomerNotificationModel> markRead(int id) => _remote.markRead(id);

  @override
  Future<int> markAllRead() => _remote.markAllRead();
}
