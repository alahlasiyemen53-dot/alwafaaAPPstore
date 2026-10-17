import 'package:geolocator/geolocator.dart';

enum DeviceLocationFailure {
  serviceDisabled,
  permissionDenied,
  permissionDeniedForever,
  unavailable
}

final class DeviceLocationResult {
  const DeviceLocationResult._({this.latitude, this.longitude, this.failure});

  const DeviceLocationResult.success(double latitude, double longitude)
      : this._(latitude: latitude, longitude: longitude);

  const DeviceLocationResult.failure(DeviceLocationFailure failure)
      : this._(failure: failure);

  final double? latitude;
  final double? longitude;
  final DeviceLocationFailure? failure;
}

abstract interface class DeviceLocationService {
  /// Shows the system permission dialog when status is [LocationPermission.denied].
  Future<DeviceLocationFailure?> ensurePermission();

  Future<DeviceLocationResult> currentLocation();
}

final class GeolocatorDeviceLocationService implements DeviceLocationService {
  Future<DeviceLocationFailure?> _resolvePermission() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return DeviceLocationFailure.serviceDisabled;
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.deniedForever) {
        return DeviceLocationFailure.permissionDeniedForever;
      }
      if (permission == LocationPermission.denied) {
        return DeviceLocationFailure.permissionDenied;
      }
      return null;
    } catch (_) {
      return DeviceLocationFailure.unavailable;
    }
  }

  @override
  Future<DeviceLocationFailure?> ensurePermission() => _resolvePermission();

  @override
  Future<DeviceLocationResult> currentLocation() async {
    try {
      final permissionFailure = await _resolvePermission();
      if (permissionFailure != null) {
        return DeviceLocationResult.failure(permissionFailure);
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          timeLimit: Duration(seconds: 10),
        ),
      );
      return DeviceLocationResult.success(
          position.latitude, position.longitude);
    } catch (_) {
      return const DeviceLocationResult.failure(
          DeviceLocationFailure.unavailable);
    }
  }
}
