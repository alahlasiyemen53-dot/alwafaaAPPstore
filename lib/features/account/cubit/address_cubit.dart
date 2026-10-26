import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/core/location/device_location_service.dart';
import 'package:store/data/models/account/customer_address_model.dart';
import 'package:store/data/models/reference/yemen_reference_models.dart';
import 'package:store/data/repositories/account_repository.dart';
import 'package:store/data/repositories/reference_repository.dart';

enum AddressStatus { initial, loading, success, failure }

class AddressState extends Equatable {
  final AddressStatus status;
  final List<CustomerAddressModel> addresses;
  final List<CountryModel> countries;
  final List<GovernorateModel> governorates;
  final int? selectedCountryId;
  final int? selectedGovernorateId;
  final bool isSubmitting;
  final double? selectedLatitude;
  final double? selectedLongitude;
  final String? locationError;
  final String? errorMessage;
  final Map<String, String> fieldErrors;

  const AddressState({
    this.status = AddressStatus.initial,
    this.addresses = const [],
    this.countries = const [],
    this.governorates = const [],
    this.selectedCountryId,
    this.selectedGovernorateId,
    this.isSubmitting = false,
    this.selectedLatitude,
    this.selectedLongitude,
    this.locationError,
    this.errorMessage,
    this.fieldErrors = const {},
  });

  CustomerAddressModel? get defaultAddress {
    try {
      return addresses.firstWhere((a) => a.isDefault);
    } catch (_) {
      return addresses.isNotEmpty ? addresses.first : null;
    }
  }

  CountryModel? get selectedCountry {
    if (selectedCountryId == null) return null;
    try {
      return countries.firstWhere((c) => c.id == selectedCountryId);
    } catch (_) {
      return null;
    }
  }

  AddressState copyWith({
    AddressStatus? status,
    List<CustomerAddressModel>? addresses,
    List<CountryModel>? countries,
    List<GovernorateModel>? governorates,
    int? selectedCountryId,
    bool clearCountry = false,
    int? selectedGovernorateId,
    bool clearGovernorate = false,
    bool? isSubmitting,
    double? selectedLatitude,
    bool clearSelectedLatitude = false,
    double? selectedLongitude,
    bool clearSelectedLongitude = false,
    String? locationError,
    bool clearLocationError = false,
    String? errorMessage,
    bool clearErrorMessage = false,
    Map<String, String>? fieldErrors,
    bool clearFieldErrors = false,
  }) {
    return AddressState(
      status: status ?? this.status,
      addresses: addresses ?? this.addresses,
      countries: countries ?? this.countries,
      governorates: governorates ?? this.governorates,
      selectedCountryId: clearCountry
          ? null
          : (selectedCountryId ?? this.selectedCountryId),
      selectedGovernorateId: clearGovernorate
          ? null
          : (selectedGovernorateId ?? this.selectedGovernorateId),
      isSubmitting: isSubmitting ?? this.isSubmitting,
      selectedLatitude: clearSelectedLatitude
          ? null
          : (selectedLatitude ?? this.selectedLatitude),
      selectedLongitude: clearSelectedLongitude
          ? null
          : (selectedLongitude ?? this.selectedLongitude),
      locationError:
          clearLocationError ? null : (locationError ?? this.locationError),
      errorMessage:
          clearErrorMessage ? null : (errorMessage ?? this.errorMessage),
      fieldErrors:
          clearFieldErrors ? const {} : (fieldErrors ?? this.fieldErrors),
    );
  }

  @override
  List<Object?> get props => [
        status,
        addresses,
        countries,
        governorates,
        selectedCountryId,
        selectedGovernorateId,
        isSubmitting,
        selectedLatitude,
        selectedLongitude,
        locationError,
        errorMessage,
        fieldErrors,
      ];
}

class AddressCubit extends Cubit<AddressState> {
  final AccountRepository _accountRepository;
  final ReferenceRepository _referenceRepository;
  final DeviceLocationService _deviceLocationService;
  int _countryRequestId = 0;
  int _governorateRequestId = 0;

  AddressCubit({
    AccountRepository? accountRepository,
    required this._referenceRepository,
    DeviceLocationService? deviceLocationService,
  })  : _accountRepository = accountRepository ?? AccountRepositoryImpl(),
        _deviceLocationService =
            deviceLocationService ?? GeolocatorDeviceLocationService(),
        super(const AddressState());

  Future<void> fetchAddresses() async {
    emit(
        state.copyWith(status: AddressStatus.loading, clearErrorMessage: true));
    try {
      final addresses = await _accountRepository.getAddresses();
      emit(state.copyWith(
        status: AddressStatus.success,
        addresses: addresses,
      ));
    } on ApiFailure catch (e) {
      emit(state.copyWith(
        status: AddressStatus.failure,
        errorMessage: e.message,
      ));
    } catch (_) {
      emit(state.copyWith(
        status: AddressStatus.failure,
        errorMessage: 'تعذر جلب العناوين. يرجى المحاولة مرة أخرى.',
      ));
    }
  }

  Future<void> loadReferenceData({
    int? preferredCountryId,
    int? includeCountryId,
  }) async {
    final requestId = ++_countryRequestId;

    if (state.countries.isEmpty) {
      final cached = await _referenceRepository.peekCachedCountries();
      if (requestId != _countryRequestId) return;
      if (cached.isNotEmpty) {
        final sortedCached = _sortedCountries(cached);
        final deliveryCountries = _filterDeliveryCountries(
          sortedCached,
          includeCountryId: includeCountryId ?? state.selectedCountryId,
        );
        final resolvedId = _resolveCountryId(
          deliveryCountries,
          preferredCountryId: preferredCountryId,
        );
        emit(state.copyWith(
          countries: deliveryCountries,
          selectedCountryId: state.selectedCountryId ?? resolvedId,
        ));
        final countryId = state.selectedCountryId ?? resolvedId;
        if (countryId != null && state.governorates.isEmpty) {
          final cachedGovs =
              await _referenceRepository.peekCachedGovernoratesByCountry(countryId);
          if (requestId == _countryRequestId &&
              state.selectedCountryId == countryId &&
              cachedGovs.isNotEmpty) {
            _emitGovernorates(
              _filterDeliveryGovernorates(
                cachedGovs,
                includeGovernorateId: state.selectedGovernorateId,
              ),
              preferredGovernorateId: state.selectedGovernorateId,
            );
          }
        }
      }
    }

    try {
      final countries = await _referenceRepository.getCountries();
      if (requestId != _countryRequestId) return;
      final sorted = _sortedCountries(countries);
      final deliveryCountries = _filterDeliveryCountries(
        sorted,
        includeCountryId: includeCountryId ?? state.selectedCountryId,
      );
      final resolvedId = _resolveCountryId(
        deliveryCountries,
        preferredCountryId: preferredCountryId,
      );
      emit(state.copyWith(
        countries: deliveryCountries,
        selectedCountryId: state.selectedCountryId ?? resolvedId,
      ));
      final countryId = state.selectedCountryId ?? resolvedId;
      if (countryId != null) {
        await _loadGovernoratesForCountry(countryId);
      }
    } catch (_) {
      // Reference data loading error handled gracefully (cache may already show)
    }
  }

  List<CountryModel> _sortedCountries(List<CountryModel> countries) {
    return List<CountryModel>.from(countries)
      ..sort((a, b) {
        final byOrder = a.sortOrder.compareTo(b.sortOrder);
        if (byOrder != 0) return byOrder;
        return a.displayName.compareTo(b.displayName);
      });
  }

  List<CountryModel> _filterDeliveryCountries(
    List<CountryModel> countries, {
    int? includeCountryId,
  }) {
    final delivery = countries.where((c) => c.isDeliveryActive).toList();
    if (includeCountryId != null && !delivery.any((c) => c.id == includeCountryId)) {
      final extra = countries.where((c) => c.id == includeCountryId);
      if (extra.isNotEmpty) {
        delivery.addAll(extra);
      }
    }
    return delivery.isNotEmpty ? delivery : countries;
  }

  List<GovernorateModel> _dedupeGovernorates(List<GovernorateModel> govs) {
    final unique = <int, GovernorateModel>{};
    for (final g in govs) {
      unique[g.id] = g;
    }
    return unique.values.toList();
  }

  List<GovernorateModel> _filterDeliveryGovernorates(
    List<GovernorateModel> govs, {
    int? includeGovernorateId,
  }) {
    final delivery = _dedupeGovernorates(
      govs.where((g) => g.isDeliveryActive).toList(),
    );
    if (includeGovernorateId != null &&
        !delivery.any((g) => g.id == includeGovernorateId)) {
      for (final g in govs) {
        if (g.id == includeGovernorateId) {
          delivery.add(g);
          break;
        }
      }
    }
    return delivery.isNotEmpty ? delivery : _dedupeGovernorates(govs);
  }

  /// Emits governorates and keeps [selectedGovernorateId] only when it exists
  /// in the list (prevents DropdownButton "value not in items" crashes).
  void _emitGovernorates(
    List<GovernorateModel> govs, {
    int? preferredGovernorateId,
  }) {
    final deduped = _dedupeGovernorates(govs);
    final candidate = preferredGovernorateId ?? state.selectedGovernorateId;
    final validId = candidate != null && deduped.any((g) => g.id == candidate)
        ? candidate
        : null;
    emit(state.copyWith(
      governorates: deduped,
      selectedGovernorateId: validId,
      clearGovernorate: validId == null,
    ));
  }

  CountryModel? _defaultCountry(List<CountryModel> sorted) {
    final yemen = sorted.where((c) => c.isoCode.toUpperCase() == 'YE' && c.isDeliveryActive);
    if (yemen.isNotEmpty) return yemen.first;
    final anyDelivery = sorted.where((c) => c.isDeliveryActive);
    if (anyDelivery.isNotEmpty) return anyDelivery.first;
    final yemenAny = sorted.where((c) => c.isoCode.toUpperCase() == 'YE');
    if (yemenAny.isNotEmpty) return yemenAny.first;
    return sorted.isNotEmpty ? sorted.first : null;
  }

  /// Prefer the customer's registration country if it allows delivery, then default delivery country.
  int? _resolveCountryId(
    List<CountryModel> sorted, {
    int? preferredCountryId,
  }) {
    if (preferredCountryId != null &&
        sorted.any((c) => c.id == preferredCountryId && c.isDeliveryActive)) {
      return preferredCountryId;
    }
    return _defaultCountry(sorted)?.id;
  }

  Future<void> selectCountry(int? countryId) async {
    if (state.selectedCountryId == countryId && countryId != null) {
      return;
    }

    emit(state.copyWith(
      selectedCountryId: countryId,
      clearGovernorate: true,
      governorates: const [],
    ));

    if (countryId != null) {
      await _loadGovernoratesForCountry(countryId);
    }
  }

  Future<void> selectGovernorate(int? governorateId) async {
    emit(state.copyWith(selectedGovernorateId: governorateId));
  }

  Future<void> _loadGovernoratesForCountry(
    int countryId, {
    int? includeGovernorateId,
  }) async {
    final targetGovId = includeGovernorateId ?? state.selectedGovernorateId;
    final requestId = ++_governorateRequestId;

    if (state.governorates.isEmpty || state.selectedCountryId != countryId) {
      final cached =
          await _referenceRepository.peekCachedGovernoratesByCountry(countryId);
      if (requestId == _governorateRequestId &&
          state.selectedCountryId == countryId &&
          cached.isNotEmpty) {
        _emitGovernorates(
          _filterDeliveryGovernorates(
            cached,
            includeGovernorateId: targetGovId,
          ),
          preferredGovernorateId: targetGovId,
        );
      }
    }

    try {
      final governorates =
          await _referenceRepository.getGovernoratesByCountry(countryId);
      if (requestId != _governorateRequestId ||
          state.selectedCountryId != countryId) {
        return;
      }
      _emitGovernorates(
        _filterDeliveryGovernorates(
          governorates,
          includeGovernorateId: targetGovId,
        ),
        preferredGovernorateId: targetGovId,
      );
    } catch (_) {}
  }

  Future<void> setGeographyFromAddress(CustomerAddressModel address) async {
    final countryId = address.countryId;
    final govId = address.governorateId;
    // Clear governorate until the matching list is loaded — selecting an id
    // before items exist crashes DropdownButtonFormField.
    emit(state.copyWith(
      selectedCountryId: countryId,
      clearGovernorate: true,
      governorates: const [],
      clearFieldErrors: true,
      clearErrorMessage: true,
      selectedLatitude: address.latitude,
      selectedLongitude: address.longitude,
    ));

    if (countryId != null) {
      await _loadGovernoratesForCountry(countryId, includeGovernorateId: govId);
    }
  }

  void resetFormSelection({int? preferredCountryId}) {
    final countryId = _resolveCountryId(
      state.countries,
      preferredCountryId: preferredCountryId,
    );
    emit(state.copyWith(
      selectedCountryId: countryId,
      clearGovernorate: true,
      governorates: const [],
      clearFieldErrors: true,
      clearErrorMessage: true,
      clearSelectedLatitude: true,
      clearSelectedLongitude: true,
      clearLocationError: true,
    ));
    if (countryId != null) {
      _loadGovernoratesForCountry(countryId);
    }
  }

  void selectLocation(double latitude, double longitude) {
    emit(state.copyWith(
      selectedLatitude: latitude,
      selectedLongitude: longitude,
      clearLocationError: true,
    ));
  }

  /// Called when the address form opens: shows the system location permission
  /// dialog immediately. When [moveToCurrent] is true (new address / no pin),
  /// also centers on the device GPS if granted.
  Future<void> promptLocationAccessOnFormOpen({
    required bool moveToCurrent,
  }) async {
    try {
      if (moveToCurrent) {
        await useCurrentLocation();
        return;
      }

      final failure = await _deviceLocationService.ensurePermission();
      if (failure == null) return;
      emit(state.copyWith(locationError: _locationFailureMessage(failure)));
    } catch (_) {
      // Prevent any unhandled platform exception on iOS from crashing
    }
  }

  Future<void> useCurrentLocation() async {
    try {
      final location = await _deviceLocationService.currentLocation();
      if (location.latitude != null && location.longitude != null) {
        selectLocation(location.latitude!, location.longitude!);
        return;
      }

      emit(state.copyWith(
        locationError: _locationFailureMessage(
          location.failure ?? DeviceLocationFailure.unavailable,
        ),
      ));
    } catch (_) {
      emit(state.copyWith(
        locationError: _locationFailureMessage(DeviceLocationFailure.unavailable),
      ));
    }
  }

  String _locationFailureMessage(DeviceLocationFailure failure) {
    return switch (failure) {
      DeviceLocationFailure.serviceDisabled =>
        'خدمة تحديد الموقع متوقفة. يمكنك اختيار الموقع يدوياً من الخريطة.',
      DeviceLocationFailure.permissionDenied =>
        'لم يتم السماح بالوصول للموقع. يمكنك اختيار الموقع يدوياً من الخريطة.',
      DeviceLocationFailure.permissionDeniedForever =>
        'تم رفض إذن الموقع نهائياً. يمكنك اختيار الموقع يدوياً من الخريطة.',
      DeviceLocationFailure.unavailable =>
        'تعذر تحديد موقعك الحالي. يمكنك اختيار الموقع يدوياً من الخريطة.',
    };
  }

  Future<bool> createAddress(Map<String, dynamic> data) async {
    emit(state.copyWith(
      isSubmitting: true,
      clearErrorMessage: true,
      clearFieldErrors: true,
    ));

    try {
      final newAddress = await _accountRepository.createAddress(data);
      final updatedList = List<CustomerAddressModel>.from(state.addresses);

      if (newAddress.isDefault) {
        for (var i = 0; i < updatedList.length; i++) {
          if (updatedList[i].isDefault) {
            updatedList[i] = updatedList[i].copyWith(isDefault: false);
          }
        }
      }

      updatedList.insert(0, newAddress);
      emit(state.copyWith(
        isSubmitting: false,
        addresses: updatedList,
      ));
      return true;
    } on ApiFailure catch (e) {
      if (e.kind == ApiFailureKind.validation) {
        final errors = <String, String>{};
        e.fieldErrors.forEach((field, msgs) {
          if (msgs.isNotEmpty) {
            errors[field] = _oneLineError(msgs.first);
          }
        });
        emit(state.copyWith(
          isSubmitting: false,
          errorMessage: _oneLineError(
            errors.isNotEmpty ? errors.values.first : e.message,
          ),
          fieldErrors: errors,
        ));
      } else {
        emit(state.copyWith(
          isSubmitting: false,
          errorMessage: _oneLineError(e.message),
        ));
      }
      return false;
    } catch (_) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: 'تعذر حفظ العنوان. يرجى المحاولة مرة أخرى.',
      ));
      return false;
    }
  }

  Future<bool> updateAddress(int id, Map<String, dynamic> data) async {
    emit(state.copyWith(
      isSubmitting: true,
      clearErrorMessage: true,
      clearFieldErrors: true,
    ));

    try {
      final updated = await _accountRepository.updateAddress(id, data);
      final updatedList = state.addresses.map((a) {
        if (a.id == id) return updated;
        if (updated.isDefault && a.isDefault) {
          return a.copyWith(isDefault: false);
        }
        return a;
      }).toList();

      emit(state.copyWith(
        isSubmitting: false,
        addresses: updatedList,
      ));
      return true;
    } on ApiFailure catch (e) {
      if (e.kind == ApiFailureKind.validation) {
        final errors = <String, String>{};
        e.fieldErrors.forEach((field, msgs) {
          if (msgs.isNotEmpty) {
            errors[field] = _oneLineError(msgs.first);
          }
        });
        emit(state.copyWith(
          isSubmitting: false,
          errorMessage: _oneLineError(
            errors.isNotEmpty ? errors.values.first : e.message,
          ),
          fieldErrors: errors,
        ));
      } else {
        emit(state.copyWith(
          isSubmitting: false,
          errorMessage: _oneLineError(e.message),
        ));
      }
      return false;
    } catch (_) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: 'تعذر تعديل العنوان. يرجى المحاولة مرة أخرى.',
      ));
      return false;
    }
  }

  Future<bool> deleteAddress(int id) async {
    emit(state.copyWith(isSubmitting: true, clearErrorMessage: true));
    try {
      await _accountRepository.deleteAddress(id);
      final updatedList = state.addresses.where((a) => a.id != id).toList();
      emit(state.copyWith(
        isSubmitting: false,
        addresses: updatedList,
      ));
      return true;
    } on ApiFailure catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: _oneLineError(e.message),
      ));
      return false;
    } catch (_) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: 'تعذر حذف العنوان. يرجى المحاولة مرة أخرى.',
      ));
      return false;
    }
  }

  void clearAddresses() {
    emit(const AddressState());
  }

  static String _oneLineError(String message) {
    return message
        .replaceAll(RegExp(r'[\r\n]+'), ' ')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }
}
