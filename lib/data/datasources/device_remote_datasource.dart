import '../../core/network/api_endpoints.dart';
import '../../core/network/api_envelope.dart';
import '../../core/network/dio_client.dart';
import '../models/device/push_device_model.dart';

abstract interface class DeviceRemoteDataSource {
  Future<PushDeviceModel> registerDevice(RegisterPushDevicePayload payload);
  Future<void> deleteDevice(dynamic deviceId);
}

final class DeviceRemoteDataSourceImpl implements DeviceRemoteDataSource {
  final DioClient _dioClient;

  DeviceRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<PushDeviceModel> registerDevice(RegisterPushDevicePayload payload) async {
    final response = await _dioClient.post(
      ApiEndpoints.devices,
      data: payload.toJson(),
    );
    return ApiEnvelope<PushDeviceModel>.parse(
      response.data,
      (data) => PushDeviceModel.fromJson(data as Map<String, dynamic>),
    ).data;
  }

  @override
  Future<void> deleteDevice(dynamic deviceId) async {
    await _dioClient.delete(ApiEndpoints.deviceItem(deviceId));
  }
}
