import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/notifications/push_notification_service.dart';
import 'package:store/data/models/device/push_device_model.dart';
import 'package:store/data/repositories/device_repository.dart';

enum PushDeviceStatus { initial, registering, registered, failed, unregistered }

class PushDeviceState extends Equatable {
  final PushDeviceStatus status;
  final PushDeviceModel? registeredDevice;
  final String? errorMessage;

  const PushDeviceState({
    this.status = PushDeviceStatus.initial,
    this.registeredDevice,
    this.errorMessage,
  });

  PushDeviceState copyWith({
    PushDeviceStatus? status,
    PushDeviceModel? registeredDevice,
    String? errorMessage,
    bool clearDevice = false,
    bool clearError = false,
  }) {
    return PushDeviceState(
      status: status ?? this.status,
      registeredDevice:
          clearDevice ? null : (registeredDevice ?? this.registeredDevice),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, registeredDevice, errorMessage];
}

class PushDeviceCubit extends Cubit<PushDeviceState> {
  final DeviceRepository _deviceRepository;
  final PushNotificationService _notificationService;
  StreamSubscription<String>? _tokenRefreshSubscription;

  PushDeviceCubit({
    required DeviceRepository deviceRepository,
    required PushNotificationService notificationService,
  })  : _deviceRepository = deviceRepository,
        _notificationService = notificationService,
        super(const PushDeviceState()) {
    _subscribeToTokenRefresh();
  }

  void _subscribeToTokenRefresh() {
    _tokenRefreshSubscription =
        _notificationService.onTokenRefresh.listen((newToken) {
      if (state.status != PushDeviceStatus.registering) {
        unawaited(registerCurrentDevice(overrideToken: newToken));
      }
    });
  }

  /// Registers the current FCM token with the authenticated customer.
  /// Retries briefly when FCM has not issued a token yet (common right after login).
  Future<bool> registerCurrentDevice({String? overrideToken}) async {
    emit(state.copyWith(
      status: PushDeviceStatus.registering,
      clearError: true,
    ));

    try {
      if (overrideToken == null) {
        await _notificationService.requestPermission();
      }

      final token = overrideToken ?? await _resolveDeviceToken();
      if (token == null || token.isEmpty) {
        debugPrint('PushDeviceCubit: FCM token unavailable after retries.');
        emit(state.copyWith(
          status: PushDeviceStatus.failed,
          errorMessage: 'رمز الإشعارات غير متوفر على هذا الجهاز.',
        ));
        return false;
      }

      final payload = RegisterPushDevicePayload(
        platform: _notificationService.platformName,
        fcmToken: token,
      );

      final device = await _deviceRepository.registerDevice(payload);
      emit(state.copyWith(
        status: PushDeviceStatus.registered,
        registeredDevice: device,
        clearError: true,
      ));
      debugPrint('PushDeviceCubit: device registered id=${device.id}');
      return true;
    } catch (error, stack) {
      debugPrint('PushDeviceCubit: register failed: $error');
      debugPrintStack(stackTrace: stack);
      emit(state.copyWith(
        status: PushDeviceStatus.failed,
        errorMessage: 'تعذر تسجيل جهاز الإشعارات.',
      ));
      return false;
    }
  }

  Future<String?> _resolveDeviceToken() async {
    const delays = <Duration>[
      Duration.zero,
      Duration(milliseconds: 1000),
      Duration(seconds: 2),
      Duration(seconds: 3),
    ];

    for (final delay in delays) {
      if (delay > Duration.zero) {
        await Future<void>.delayed(delay);
      }
      final token = await _notificationService.getDeviceToken();
      if (token != null && token.trim().isNotEmpty) {
        return token.trim();
      }
    }

    return null;
  }

  Future<void> unregisterCurrentDevice() async {
    final currentDevice = state.registeredDevice;
    if (currentDevice != null) {
      try {
        await _deviceRepository.deleteDevice(currentDevice.id);
      } catch (_) {
        // Handled gracefully during logout
      }
    }
    emit(const PushDeviceState(status: PushDeviceStatus.unregistered));
  }

  void resetDevice() {
    emit(const PushDeviceState());
  }

  @override
  Future<void> close() {
    _tokenRefreshSubscription?.cancel();
    return super.close();
  }
}
