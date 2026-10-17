import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_failure.dart';
import '../../../core/utils/idempotency_key_generator.dart';
import '../../../data/models/account/customer_address_model.dart';
import '../../../data/models/checkout/checkout_preview_model.dart';
import '../../../data/models/checkout/customer_order_model.dart';
import '../../../data/models/checkout/payment_attempt_model.dart';
import '../../../data/repositories/checkout_repository.dart';

enum CheckoutStatus {
  initial,
  loadingPreview,
  previewReady,
  submittingOrder,
  initiatingPayment,
  awaitingPaymentOtp,
  confirmingPayment,
  orderSuccess,
  error,
}

enum CheckoutStep {
  address,
  payment,
  review,
}

class CheckoutState extends Equatable {
  final CheckoutStatus status;
  final CheckoutStep currentStep;
  final CustomerAddressModel? selectedAddress;
  final String shippingMethod;
  final String paymentMethod;
  final bool giftWrappingRequested;
  final String? giftMessage;
  final String? notes;
  final String? couponCode;
  final List<int>? cartItemIds;
  final CheckoutPreviewModel? preview;
  final CustomerOrderModel? confirmedOrder;
  final String? idempotencyKey;
  final ApiFailure? failure;
  final String? errorMessage;
  final int previewSequence;
  final String jaibCode;
  final String paymentOtp;
  final PaymentAttemptModel? pendingPaymentAttempt;
  final bool isApplyingCoupon;
  final String? couponError;

  const CheckoutState({
    this.status = CheckoutStatus.initial,
    this.currentStep = CheckoutStep.address,
    this.selectedAddress,
    this.shippingMethod = 'standard',
    this.paymentMethod = 'cod',
    this.giftWrappingRequested = false,
    this.giftMessage,
    this.notes,
    this.couponCode,
    this.cartItemIds,
    this.preview,
    this.confirmedOrder,
    this.idempotencyKey,
    this.failure,
    this.errorMessage,
    this.previewSequence = 0,
    this.jaibCode = '',
    this.paymentOtp = '',
    this.pendingPaymentAttempt,
    this.isApplyingCoupon = false,
    this.couponError,
  });

  bool get isLoadingPreview => status == CheckoutStatus.loadingPreview;
  bool get isSubmitting =>
      status == CheckoutStatus.submittingOrder ||
      status == CheckoutStatus.confirmingPayment;
  bool get isInitiatingPayment => status == CheckoutStatus.initiatingPayment;
  bool get isAwaitingPaymentOtp => status == CheckoutStatus.awaitingPaymentOtp;
  bool get isSuccess => status == CheckoutStatus.orderSuccess;
  bool get canCheckout => preview?.canCheckout ?? false;
  bool get hasJaibCredentials => jaibCode.trim().isNotEmpty;

  CheckoutPaymentMethodModel? get selectedPaymentMethodModel {
    final methods = preview?.paymentMethods ?? const [];
    for (final method in methods) {
      if (method.code == paymentMethod) {
        return method;
      }
    }
    return methods.where((m) => m.enabled).isNotEmpty
        ? methods.firstWhere((m) => m.enabled)
        : (methods.isNotEmpty ? methods.first : null);
  }

  bool get paymentFieldsReady {
    final method = selectedPaymentMethodModel;
    if (method == null) return paymentMethod == 'cod';
    if (method.isStep1) {
      return jaibCode.trim().isNotEmpty;
    }
    if (method.isStep2 && isAwaitingPaymentOtp) {
      return paymentOtp.trim().isNotEmpty;
    }
    return true;
  }

  CheckoutState copyWith({
    CheckoutStatus? status,
    CheckoutStep? currentStep,
    CustomerAddressModel? selectedAddress,
    bool clearSelectedAddress = false,
    String? shippingMethod,
    String? paymentMethod,
    bool? giftWrappingRequested,
    String? giftMessage,
    bool clearGiftMessage = false,
    String? notes,
    bool clearNotes = false,
    String? couponCode,
    bool clearCouponCode = false,
    List<int>? cartItemIds,
    bool clearCartItemIds = false,
    CheckoutPreviewModel? preview,
    bool clearPreview = false,
    CustomerOrderModel? confirmedOrder,
    bool clearConfirmedOrder = false,
    String? idempotencyKey,
    bool clearIdempotencyKey = false,
    ApiFailure? failure,
    bool clearFailure = false,
    String? errorMessage,
    bool clearErrorMessage = false,
    int? previewSequence,
    String? jaibCode,
    String? paymentOtp,
    PaymentAttemptModel? pendingPaymentAttempt,
    bool clearPendingPaymentAttempt = false,
    bool? isApplyingCoupon,
    String? couponError,
    bool clearCouponError = false,
  }) {
    return CheckoutState(
      status: status ?? this.status,
      currentStep: currentStep ?? this.currentStep,
      selectedAddress: clearSelectedAddress
          ? null
          : (selectedAddress ?? this.selectedAddress),
      shippingMethod: shippingMethod ?? this.shippingMethod,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      giftWrappingRequested:
          giftWrappingRequested ?? this.giftWrappingRequested,
      giftMessage:
          clearGiftMessage ? null : (giftMessage ?? this.giftMessage),
      notes: clearNotes ? null : (notes ?? this.notes),
      couponCode:
          clearCouponCode ? null : (couponCode ?? this.couponCode),
      cartItemIds:
          clearCartItemIds ? null : (cartItemIds ?? this.cartItemIds),
      preview: clearPreview ? null : (preview ?? this.preview),
      confirmedOrder: clearConfirmedOrder
          ? null
          : (confirmedOrder ?? this.confirmedOrder),
      idempotencyKey: clearIdempotencyKey
          ? null
          : (idempotencyKey ?? this.idempotencyKey),
      failure: clearFailure ? null : (failure ?? this.failure),
      errorMessage:
          clearErrorMessage ? null : (errorMessage ?? this.errorMessage),
      previewSequence: previewSequence ?? this.previewSequence,
      jaibCode: jaibCode ?? this.jaibCode,
      paymentOtp: paymentOtp ?? this.paymentOtp,
      pendingPaymentAttempt: clearPendingPaymentAttempt
          ? null
          : (pendingPaymentAttempt ?? this.pendingPaymentAttempt),
      isApplyingCoupon: isApplyingCoupon ?? this.isApplyingCoupon,
      couponError:
          clearCouponError ? null : (couponError ?? this.couponError),
    );
  }

  @override
  List<Object?> get props => [
        status,
        currentStep,
        selectedAddress,
        shippingMethod,
        paymentMethod,
        giftWrappingRequested,
        giftMessage,
        notes,
        couponCode,
        cartItemIds,
        preview,
        confirmedOrder,
        idempotencyKey,
        failure,
        errorMessage,
        previewSequence,
        jaibCode,
        paymentOtp,
        pendingPaymentAttempt,
        isApplyingCoupon,
        couponError,
      ];
}

class CheckoutCubit extends Cubit<CheckoutState> {
  final CheckoutRepository _checkoutRepository;
  int _sequenceCounter = 0;

  CheckoutCubit({CheckoutRepository? checkoutRepository})
      : _checkoutRepository =
            checkoutRepository ?? CheckoutRepositoryImpl(),
        super(const CheckoutState());

  void setStep(CheckoutStep step) {
    emit(state.copyWith(currentStep: step));
  }

  void nextStep() {
    if (state.currentStep == CheckoutStep.address) {
      emit(state.copyWith(currentStep: CheckoutStep.payment));
    } else if (state.currentStep == CheckoutStep.payment) {
      emit(state.copyWith(currentStep: CheckoutStep.review));
    }
  }

  void previousStep() {
    if (state.currentStep == CheckoutStep.review) {
      emit(state.copyWith(currentStep: CheckoutStep.payment));
    } else if (state.currentStep == CheckoutStep.payment) {
      emit(state.copyWith(currentStep: CheckoutStep.address));
    }
  }

  Future<void> initializeCheckout({
    required List<CustomerAddressModel> addresses,
    String? couponCode,
    bool giftWrapping = false,
    List<int>? cartItemIds,
  }) async {
    CustomerAddressModel? resolvedAddress = state.selectedAddress;

    if (resolvedAddress == null && addresses.isNotEmpty) {
      resolvedAddress = addresses.firstWhere(
        (a) => a.isDefault,
        orElse: () => addresses.first,
      );
    }

    emit(state.copyWith(
      selectedAddress: resolvedAddress,
      couponCode: couponCode,
      giftWrappingRequested: giftWrapping,
      cartItemIds: cartItemIds,
      clearCartItemIds: cartItemIds == null,
      currentStep: CheckoutStep.address,
      clearFailure: true,
      clearErrorMessage: true,
      clearConfirmedOrder: true,
      clearPendingPaymentAttempt: true,
      paymentOtp: '',
      jaibCode: '',
      isApplyingCoupon: false,
      clearCouponError: true,
    ));

    await fetchPreview();
  }

  Future<void> selectAddress(CustomerAddressModel address) async {
    // Keep existing preview so the screen soft-refreshes shipping/totals
    // instead of swapping to a full-page skeleton.
    emit(state.copyWith(
      selectedAddress: address,
      shippingMethod: 'standard',
      status: CheckoutStatus.loadingPreview,
      clearFailure: true,
      clearErrorMessage: true,
      clearPendingPaymentAttempt: true,
      paymentOtp: '',
    ));
    await fetchPreview();
  }

  Future<void> selectShippingMethod(String methodCode) async {
    if (state.shippingMethod == methodCode && state.preview != null) return;
    emit(state.copyWith(
      shippingMethod: methodCode,
      status: CheckoutStatus.loadingPreview,
      clearFailure: true,
      clearErrorMessage: true,
    ));
    await fetchPreview();
  }

  Future<void> selectPaymentMethod(String methodCode) async {
    final switchingAwayFromPending =
        state.isAwaitingPaymentOtp || state.pendingPaymentAttempt != null;

    if (state.paymentMethod == methodCode &&
        !switchingAwayFromPending &&
        state.preview != null) {
      return;
    }

    emit(state.copyWith(
      paymentMethod: methodCode,
      status: state.preview != null
          ? CheckoutStatus.previewReady
          : CheckoutStatus.initial,
      clearFailure: true,
      clearErrorMessage: true,
      clearPendingPaymentAttempt: true,
      paymentOtp: '',
    ));
  }

  void saveJaibPurchaseCode(String code) {
    emit(state.copyWith(
      jaibCode: code.trim(),
      clearFailure: true,
      clearErrorMessage: true,
    ));
  }

  void setJaibCode(String value) {
    emit(state.copyWith(jaibCode: value));
  }

  void setPaymentOtp(String value) {
    emit(state.copyWith(paymentOtp: value));
  }

  Future<void> toggleGiftWrapping(bool requested) async {
    if (state.giftWrappingRequested == requested) return;
    emit(state.copyWith(giftWrappingRequested: requested));
    await fetchPreview();
  }

  void setGiftMessage(String? message) {
    emit(state.copyWith(giftMessage: message));
  }

  void setNotes(String? notes) {
    emit(state.copyWith(notes: notes));
  }

  Future<bool> applyCoupon(String code) async {
    final cleanCode = code.trim().toUpperCase();
    if (cleanCode.isEmpty) {
      emit(state.copyWith(couponError: 'يرجى إدخال رمز الكوبون.'));
      return false;
    }

    emit(state.copyWith(
      isApplyingCoupon: true,
      clearCouponError: true,
    ));

    final previousCoupon = state.couponCode;
    try {
      final preview = await _checkoutRepository.getPreview(
        customerAddressId: state.selectedAddress?.id,
        couponCode: cleanCode,
        giftWrappingRequested: state.giftWrappingRequested,
        giftMessage: state.giftMessage,
        paymentMethod: state.paymentMethod,
        shippingMethod: state.shippingMethod,
        cartItemIds: state.cartItemIds,
      );

      emit(state.copyWith(
        isApplyingCoupon: false,
        couponCode: cleanCode,
        preview: preview,
        clearCouponError: true,
        clearErrorMessage: true,
        clearFailure: true,
      ));
      return true;
    } on ApiFailure catch (failure) {
      emit(state.copyWith(
        isApplyingCoupon: false,
        couponCode: previousCoupon,
        clearCouponCode: previousCoupon == null,
        couponError: failure.message,
      ));
      return false;
    } catch (e) {
      emit(state.copyWith(
        isApplyingCoupon: false,
        couponCode: previousCoupon,
        clearCouponCode: previousCoupon == null,
        couponError: 'فشل تطبيق الكوبون. يرجى التأكد من الرمز والمحاولة ثانية.',
      ));
      return false;
    }
  }

  Future<void> removeCoupon() async {
    emit(state.copyWith(
      isApplyingCoupon: true,
      clearCouponError: true,
    ));

    try {
      final preview = await _checkoutRepository.getPreview(
        customerAddressId: state.selectedAddress?.id,
        couponCode: null,
        giftWrappingRequested: state.giftWrappingRequested,
        giftMessage: state.giftMessage,
        paymentMethod: state.paymentMethod,
        shippingMethod: state.shippingMethod,
        cartItemIds: state.cartItemIds,
      );

      emit(state.copyWith(
        isApplyingCoupon: false,
        clearCouponCode: true,
        preview: preview,
        clearCouponError: true,
      ));
    } catch (_) {
      emit(state.copyWith(
        isApplyingCoupon: false,
        clearCouponCode: true,
      ));
      await fetchPreview();
    }
  }

  Future<void> fetchPreview() async {
    final currentSeq = ++_sequenceCounter;
    final addressId = state.selectedAddress?.id;
    final requestedShipping = state.shippingMethod;
    final requestedPayment = state.paymentMethod;
    emit(state.copyWith(
      status: CheckoutStatus.loadingPreview,
      previewSequence: currentSeq,
      clearFailure: true,
      clearErrorMessage: true,
    ));

    try {
      final preview = await _checkoutRepository.getPreview(
        customerAddressId: addressId,
        couponCode: state.couponCode,
        giftWrappingRequested: state.giftWrappingRequested,
        giftMessage: state.giftMessage,
        paymentMethod: requestedPayment,
        shippingMethod: requestedShipping,
        cartItemIds: state.cartItemIds,
      );

      if (currentSeq != _sequenceCounter || isClosed) return;

      final giftEnabled = preview.giftWrapping?.enabled ?? false;
      final availableShipping =
          preview.shippingMethods.map((m) => m.code).toSet();
      final preferredShipping =
          preview.selectedShippingMethod ?? requestedShipping;
      final resolvedShipping = availableShipping.contains(preferredShipping)
          ? preferredShipping
          : (preview.shippingMethods.isNotEmpty
              ? preview.shippingMethods.first.code
              : requestedShipping);

      final enabledPayments =
          preview.paymentMethods.where((m) => m.enabled).toList();
      final availablePayments = enabledPayments.map((m) => m.code).toSet();
      final resolvedPayment = availablePayments.contains(requestedPayment)
          ? requestedPayment
          : (enabledPayments.isNotEmpty
              ? enabledPayments.first.code
              : requestedPayment);

      emit(state.copyWith(
        status: CheckoutStatus.previewReady,
        preview: preview,
        shippingMethod: resolvedShipping,
        paymentMethod: resolvedPayment,
        giftWrappingRequested:
            giftEnabled ? state.giftWrappingRequested : false,
        clearGiftMessage: !giftEnabled,
        clearFailure: true,
        clearErrorMessage: true,
      ));

      if (resolvedShipping != requestedShipping &&
          availableShipping.contains(resolvedShipping) &&
          currentSeq == _sequenceCounter &&
          !isClosed) {
        await fetchPreview();
      }
    } on ApiFailure catch (failure) {
      if (currentSeq != _sequenceCounter || isClosed) return;
      emit(state.copyWith(
        status: CheckoutStatus.error,
        failure: failure,
        errorMessage: failure.message,
      ));
    } catch (e) {
      if (currentSeq != _sequenceCounter || isClosed) return;
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'حدث خطأ أثناء تحميل معاينة الطلب. يرجى المحاولة مجدداً.',
      ));
    }
  }

  /// Place COD / Step1 order. Step2 OTP confirmation uses [confirmPaymentOtp].
  Future<CustomerOrderModel?> placeOrder() async {
    if (state.isSubmitting || state.isInitiatingPayment) return null;

    if (state.isAwaitingPaymentOtp) {
      return confirmPaymentOtp(state.paymentOtp);
    }

    if (state.selectedAddress == null) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'يرجى تحديد عنوان التوصيل أولاً.',
      ));
      return null;
    }

    final method = state.selectedPaymentMethodModel;
    final flowType = method?.flowType ?? 'none';

    if (flowType == 'step2') {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage:
            'يجب إتمام رمز التحقق من المحفظة قبل إنشاء الطلب. أعد اختيار طريقة الدفع إن لزم.',
      ));
      return null;
    }

    if (flowType == 'step1') {
      final code = state.jaibCode.trim();
      if (code.isEmpty) {
        emit(state.copyWith(
          status: CheckoutStatus.error,
          errorMessage: 'رمز الشراء مطلوب لإتمام الدفع عبر جيب.',
        ));
        return null;
      }
      return _createOrderWithPayment({
        'code': code,
      });
    }

    return _createOrderWithPayment(null);
  }

  /// Start Step2 pending invoice (call when user selects a step2 method).
  /// Customer phone is resolved from the auth token on the backend.
  Future<bool> initiateStep2Payment() async {
    if (state.isSubmitting || state.isInitiatingPayment) return false;

    if (state.selectedAddress == null) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'يرجى تحديد عنوان التوصيل أولاً.',
      ));
      return false;
    }

    if (!state.canCheckout) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'لا يمكن بدء الدفع حالياً. تحقق من السلة والعنوان.',
      ));
      return false;
    }

    final method = state.selectedPaymentMethodModel;
    if (method == null || !method.isStep2) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'طريقة الدفع المحددة لا تدعم هذه الخطوة.',
      ));
      return false;
    }

    emit(state.copyWith(
      status: CheckoutStatus.initiatingPayment,
      clearFailure: true,
      clearErrorMessage: true,
      clearPendingPaymentAttempt: true,
      paymentOtp: '',
    ));

    try {
      final attempt = await _checkoutRepository.initiatePayment(
        customerAddressId: state.selectedAddress!.id,
        paymentMethod: state.paymentMethod,
        shippingMethod: state.shippingMethod,
        couponCode: state.couponCode,
        giftWrappingRequested: state.giftWrappingRequested,
        giftMessage: state.giftMessage,
        notes: state.notes,
        cartItemIds: state.cartItemIds,
      );

      emit(state.copyWith(
        status: CheckoutStatus.awaitingPaymentOtp,
        pendingPaymentAttempt: attempt,
        paymentOtp: '',
        clearFailure: true,
        clearErrorMessage: true,
      ));
      return true;
    } on ApiFailure catch (failure) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        failure: failure,
        errorMessage: failure.message,
        clearPendingPaymentAttempt: true,
      ));
      return false;
    } catch (e) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'تعذر بدء عملية الدفع. يرجى المحاولة مجدداً.',
        clearPendingPaymentAttempt: true,
      ));
      return false;
    }
  }

  Future<CustomerOrderModel?> confirmPaymentOtp(String otp) async {
    if (state.isSubmitting) return null;

    final attempt = state.pendingPaymentAttempt;
    final trimmed = otp.trim();
    if (attempt == null || attempt.uuid.isEmpty) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'لا توجد عملية دفع بانتظار التأكيد. أعد المحاولة.',
      ));
      return null;
    }
    if (trimmed.isEmpty) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'يرجى إدخال رمز التحقق.',
      ));
      return null;
    }

    emit(state.copyWith(
      status: CheckoutStatus.confirmingPayment,
      paymentOtp: trimmed,
      clearFailure: true,
      clearErrorMessage: true,
    ));

    try {
      final order = await _checkoutRepository.confirmPayment(
        attemptUuid: attempt.uuid,
        otp: trimmed,
      );

      emit(state.copyWith(
        status: CheckoutStatus.orderSuccess,
        confirmedOrder: order,
        clearPendingPaymentAttempt: true,
        paymentOtp: '',
        jaibCode: '',
        clearFailure: true,
        clearErrorMessage: true,
      ));
      return order;
    } on ApiFailure catch (failure) {
      emit(state.copyWith(
        status: CheckoutStatus.awaitingPaymentOtp,
        failure: failure,
        errorMessage: failure.message,
      ));
      return null;
    } catch (e) {
      emit(state.copyWith(
        status: CheckoutStatus.awaitingPaymentOtp,
        errorMessage: 'حدث خطأ أثناء تأكيد الدفع. يرجى المحاولة مجدداً.',
      ));
      return null;
    }
  }

  void cancelPendingPayment() {
    emit(state.copyWith(
      status: state.preview != null
          ? CheckoutStatus.previewReady
          : CheckoutStatus.initial,
      clearPendingPaymentAttempt: true,
      paymentOtp: '',
      clearFailure: true,
      clearErrorMessage: true,
    ));
  }

  Future<CustomerOrderModel?> _createOrderWithPayment(
    Map<String, dynamic>? payment,
  ) async {
    final idempotencyKey =
        state.idempotencyKey ?? IdempotencyKeyGenerator.generate();

    emit(state.copyWith(
      status: CheckoutStatus.submittingOrder,
      idempotencyKey: idempotencyKey,
      clearFailure: true,
      clearErrorMessage: true,
    ));

    try {
      final order = await _checkoutRepository.createOrder(
        customerAddressId: state.selectedAddress!.id,
        paymentMethod: state.paymentMethod,
        idempotencyKey: idempotencyKey,
        shippingMethod: state.shippingMethod,
        couponCode: state.couponCode,
        giftWrappingRequested: state.giftWrappingRequested,
        giftMessage: state.giftMessage,
        notes: state.notes,
        payment: payment,
        cartItemIds: state.cartItemIds,
      );

      emit(state.copyWith(
        status: CheckoutStatus.orderSuccess,
        confirmedOrder: order,
        clearIdempotencyKey: true,
        jaibCode: '',
        paymentOtp: '',
        clearPendingPaymentAttempt: true,
        clearFailure: true,
        clearErrorMessage: true,
      ));

      return order;
    } on ApiFailure catch (failure) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        failure: failure,
        errorMessage: failure.message,
      ));
      return null;
    } catch (e) {
      emit(state.copyWith(
        status: CheckoutStatus.error,
        errorMessage: 'حدث خطأ في الاتصال بالشبكة. يرجى إعادة المحاولة بأمان.',
      ));
      return null;
    }
  }

  void reset() {
    _sequenceCounter = 0;
    emit(const CheckoutState());
  }
}
