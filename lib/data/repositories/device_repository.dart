import '../datasources/device_remote_datasource.dart';
import '../models/device/push_device_model.dart';

abstract interface class DeviceRepository {
  Future<PushDeviceModel> registerDevice(RegisterPushDevicePayload payload);
  Future<void> deleteDevice(dynamic deviceId);
}

final class DeviceRepositoryImpl implements DeviceRepository {
  final DeviceRemoteDataSource _remoteDataSource;

  DeviceRepositoryImpl({DeviceRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? DeviceRemoteDataSourceImpl();

  @override
  Future<PushDeviceModel> registerDevice(RegisterPushDevicePayload payload) =>
      _remoteDataSource.registerDevice(payload);

  @override
  Future<void> deleteDevice(dynamic deviceId) =>
      _remoteDataSource.deleteDevice(deviceId);
}
