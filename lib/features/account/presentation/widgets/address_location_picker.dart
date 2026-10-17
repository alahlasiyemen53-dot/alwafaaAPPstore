import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/constants/app_colors.dart';
import '../../cubit/address_cubit.dart';

class AddressLocationPicker extends StatefulWidget {
  const AddressLocationPicker({super.key, required this.state});

  static const sanaaInitialPosition = LatLng(15.3694, 44.1910);
  final AddressState state;

  @override
  State<AddressLocationPicker> createState() => _AddressLocationPickerState();
}

class _AddressLocationPickerState extends State<AddressLocationPicker> {
  static const _watchdogDuration = Duration(seconds: 8);

  GoogleMapController? _controller;
  bool _mapReady = false;
  bool _mapFailed = false;
  int _mapGeneration = 0;
  Timer? _mapWatchdog;
  bool _ignoreNextCameraFollow = false;
  bool _cameraMoving = false;
  LatLng? _pendingCenter;

  LatLng get _selectedLocation => LatLng(
        widget.state.selectedLatitude ??
            AddressLocationPicker.sanaaInitialPosition.latitude,
        widget.state.selectedLongitude ??
            AddressLocationPicker.sanaaInitialPosition.longitude,
      );

  /// Claim map gestures so the parent ListView / bottom sheet does not steal pans.
  final Set<Factory<OneSequenceGestureRecognizer>> _mapGestureRecognizers = {
    Factory<OneSequenceGestureRecognizer>(EagerGestureRecognizer.new),
  };

  @override
  void initState() {
    super.initState();
    _armWatchdog();
  }

  @override
  void dispose() {
    _mapWatchdog?.cancel();
    final controller = _controller;
    _controller = null;
    if (controller != null) {
      scheduleMicrotask(() {
        try {
          controller.dispose();
        } catch (_) {}
      });
    }
    super.dispose();
  }

  void _armWatchdog() {
    _mapWatchdog?.cancel();
    final generation = _mapGeneration;
    _mapWatchdog = Timer(_watchdogDuration, () {
      if (!mounted || _mapReady || generation != _mapGeneration) return;
      if (kDebugMode) {
        debugPrint(
          'AddressLocationPicker: onMapCreated timed out. '
          'Check Maps SDK for Android + Android-restricted API key '
          '(package com.alwafaamed.storeapp + SHA-1). '
          'Do not reuse the browser/web Admin Maps key.',
        );
      }
      setState(() => _mapFailed = true);
    });
  }

  void _retryMap() {
    final old = _controller;
    _controller = null;
    if (old != null) {
      scheduleMicrotask(() {
        try {
          old.dispose();
        } catch (_) {}
      });
    }
    setState(() {
      _mapGeneration++;
      _mapFailed = false;
      _mapReady = false;
      _cameraMoving = false;
      _pendingCenter = null;
    });
    _armWatchdog();
  }

  void _commitLocation(LatLng location) {
    _ignoreNextCameraFollow = true;
    context.read<AddressCubit>().selectLocation(
          location.latitude,
          location.longitude,
        );
  }

  @override
  void didUpdateWidget(covariant AddressLocationPicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    final latChanged =
        oldWidget.state.selectedLatitude != widget.state.selectedLatitude;
    final lngChanged =
        oldWidget.state.selectedLongitude != widget.state.selectedLongitude;
    if (!latChanged && !lngChanged) return;

    if (_ignoreNextCameraFollow) {
      _ignoreNextCameraFollow = false;
      return;
    }

    // Follow camera only for external updates (e.g. "use my location").
    try {
      _controller?.animateCamera(CameraUpdate.newLatLng(_selectedLocation));
    } catch (_) {}
  }

  void _onMapCreated(GoogleMapController controller) {
    _controller = controller;
    _mapWatchdog?.cancel();
    if (!mounted) return;
    setState(() {
      _mapReady = true;
      _mapFailed = false;
    });
    if (widget.state.selectedLatitude == null ||
        widget.state.selectedLongitude == null) {
      _commitLocation(AddressLocationPicker.sanaaInitialPosition);
    }
  }

  void _onCameraMove(CameraPosition position) {
    _pendingCenter = position.target;
    if (!_cameraMoving && mounted) {
      setState(() => _cameraMoving = true);
    }
  }

  void _onCameraIdle() {
    final center = _pendingCenter;
    if (mounted && _cameraMoving) {
      setState(() => _cameraMoving = false);
    }
    if (center == null) return;
    _commitLocation(center);
  }

  @override
  Widget build(BuildContext context) {
    final hasSelection = widget.state.selectedLatitude != null &&
        widget.state.selectedLongitude != null;
    final cameraTarget = _selectedLocation;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'تحديد الموقع على خريطة Google *',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.sp),
        ),
        SizedBox(height: 4.h),
        Text(
          _mapFailed
              ? 'تعذر تحميل الخريطة. يمكنك استخدام موقعك الحالي أو إكمال العنوان نصياً.'
              : 'حرّك الخريطة حتى يصبح الدبوس فوق موقع التوصيل',
          style: TextStyle(fontSize: 11.sp, color: AppColors.textMuted),
        ),
        SizedBox(height: 8.h),
        ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: SizedBox(
            height: 260.h,
            child: _mapFailed
                ? _MapFallback(
                    hasSelection: hasSelection,
                    latitude: widget.state.selectedLatitude,
                    longitude: widget.state.selectedLongitude,
                    onUseCurrentLocation: () =>
                        context.read<AddressCubit>().useCurrentLocation(),
                    onRetryMap: _retryMap,
                  )
                : Stack(
                    alignment: Alignment.center,
                    children: [
                      KeyedSubtree(
                        key: ValueKey('google-map-$_mapGeneration'),
                        child: GoogleMap(
                          initialCameraPosition: CameraPosition(
                            target: cameraTarget,
                            zoom: hasSelection ? 16 : 13,
                          ),
                          onMapCreated: _onMapCreated,
                          onCameraMove: _onCameraMove,
                          onCameraIdle: _onCameraIdle,
                          onTap: (latLng) {
                            try {
                              _controller?.animateCamera(
                                CameraUpdate.newLatLng(latLng),
                              );
                            } catch (_) {}
                          },
                          gestureRecognizers: _mapGestureRecognizers,
                          myLocationButtonEnabled: false,
                          zoomControlsEnabled: true,
                          scrollGesturesEnabled: true,
                          zoomGesturesEnabled: true,
                          tiltGesturesEnabled: false,
                          rotateGesturesEnabled: false,
                          liteModeEnabled: false,
                          compassEnabled: false,
                          mapToolbarEnabled: false,
                        ),
                      ),
                      // Fixed center pin: pan the map under it (reliable inside ListView).
                      IgnorePointer(
                        child: AnimatedPadding(
                          duration: const Duration(milliseconds: 120),
                          padding: EdgeInsets.only(
                            bottom: _cameraMoving ? 18.h : 0,
                          ),
                          child: Icon(
                            Icons.location_on,
                            size: 42.sp,
                            color: AppColors.primaryDark,
                            shadows: const [
                              Shadow(
                                blurRadius: 4,
                                color: Colors.black26,
                                offset: Offset(0, 2),
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (!_mapReady)
                        const ColoredBox(
                          color: AppColors.surfaceSoft,
                          child: Center(
                            child: CircularProgressIndicator(
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ),
                    ],
                  ),
          ),
        ),
        SizedBox(height: 8.h),
        if (!_mapFailed)
          OutlinedButton.icon(
            onPressed: () => context.read<AddressCubit>().useCurrentLocation(),
            icon: const Icon(Icons.my_location_outlined),
            label: const Text('استخدام موقعي الحالي'),
            style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primaryDark),
          ),
        if (widget.state.locationError != null)
          Padding(
            padding: EdgeInsets.only(top: 6.h),
            child: Text(
              widget.state.locationError!,
              softWrap: true,
              style: TextStyle(
                color: AppColors.error,
                fontSize: 11.sp,
                height: 1.35,
              ),
            ),
          ),
        if (kDebugMode && _mapFailed)
          Padding(
            padding: EdgeInsets.only(top: 4.h),
            child: Text(
              'تحقق: Maps SDK for Android + مفتاح Android (package + SHA-1). لا تستخدم مفتاح Web/Admin.',
              style: TextStyle(fontSize: 10.sp, color: AppColors.textMuted),
            ),
          ),
      ],
    );
  }
}

class _MapFallback extends StatelessWidget {
  const _MapFallback({
    required this.hasSelection,
    required this.latitude,
    required this.longitude,
    required this.onUseCurrentLocation,
    required this.onRetryMap,
  });

  final bool hasSelection;
  final double? latitude;
  final double? longitude;
  final VoidCallback onUseCurrentLocation;
  final VoidCallback onRetryMap;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.surfaceSoft,
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.map_outlined, size: 36.w, color: AppColors.primaryLight),
            SizedBox(height: 8.h),
            Text(
              'تعذر تحميل الخريطة',
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13.sp,
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: 4.h),
            Text(
              hasSelection
                  ? 'تم حفظ موقعك: ${latitude!.toStringAsFixed(5)}, ${longitude!.toStringAsFixed(5)}'
                  : 'استخدم موقعك الحالي أو أكمل بيانات العنوان النصية.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11.sp, color: AppColors.textMuted),
            ),
            SizedBox(height: 12.h),
            Wrap(
              spacing: 8.w,
              runSpacing: 8.h,
              alignment: WrapAlignment.center,
              children: [
                ElevatedButton.icon(
                  onPressed: onUseCurrentLocation,
                  icon: const Icon(Icons.my_location_outlined, size: 18),
                  label: const Text('موقعي الحالي'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primaryDark,
                    foregroundColor: AppColors.textLight,
                  ),
                ),
                OutlinedButton(
                  onPressed: onRetryMap,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.primaryDark,
                  ),
                  child: const Text('إعادة تحميل الخريطة'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
