import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/data/models/orders/order_status_mapper.dart';
import '../../../core/network/api_failure.dart';
import '../../../data/models/orders/order_detail_model.dart';
import '../../../data/models/orders/order_summary_model.dart';
import '../../../data/models/orders/order_tracking_model.dart';
import '../../../data/models/orders/reorder_result_model.dart';
import '../../../data/repositories/orders_repository.dart';

enum OrdersStatus {
  initial,
  loading,
  loaded,
  error,
}

class OrdersState extends Equatable {
  final OrdersStatus status;
  final List<OrderSummaryModel> orders;
  final OrderPaginationModel pagination;
  final bool isLoadingMore;

  // Selected Order Detail
  final bool isLoadingDetail;
  final OrderDetailModel? selectedOrderDetail;
  final ApiFailure? detailFailure;

  // Selected Tracking
  final bool isLoadingTracking;
  final OrderTrackingModel? selectedTracking;
  final ApiFailure? trackingFailure;

  // Reorder
  final bool isReordering;
  final ReorderResultModel? reorderResult;

  // General Failure
  final ApiFailure? failure;
  final String? errorMessage;

  const OrdersState({
    this.status = OrdersStatus.initial,
    this.orders = const [],
    this.pagination = const OrderPaginationModel.empty(),
    this.isLoadingMore = false,
    this.isLoadingDetail = false,
    this.selectedOrderDetail,
    this.detailFailure,
    this.isLoadingTracking = false,
    this.selectedTracking,
    this.trackingFailure,
    this.isReordering = false,
    this.reorderResult,
    this.failure,
    this.errorMessage,
  });

  bool get isLoading => status == OrdersStatus.loading;
  bool get hasOrders => orders.isNotEmpty;

  int get activeOrdersCount => orders
      .where((order) => OrderStatusMapper.isActiveStatus(order.status))
      .length;

  OrdersState copyWith({
    OrdersStatus? status,
    List<OrderSummaryModel>? orders,
    OrderPaginationModel? pagination,
    bool? isLoadingMore,
    bool? isLoadingDetail,
    OrderDetailModel? selectedOrderDetail,
    bool clearSelectedOrderDetail = false,
    ApiFailure? detailFailure,
    bool clearDetailFailure = false,
    bool? isLoadingTracking,
    OrderTrackingModel? selectedTracking,
    bool clearSelectedTracking = false,
    ApiFailure? trackingFailure,
    bool clearTrackingFailure = false,
    bool? isReordering,
    ReorderResultModel? reorderResult,
    bool clearReorderResult = false,
    ApiFailure? failure,
    bool clearFailure = false,
    String? errorMessage,
    bool clearErrorMessage = false,
  }) {
    return OrdersState(
      status: status ?? this.status,
      orders: orders ?? this.orders,
      pagination: pagination ?? this.pagination,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      isLoadingDetail: isLoadingDetail ?? this.isLoadingDetail,
      selectedOrderDetail: clearSelectedOrderDetail
          ? null
          : (selectedOrderDetail ?? this.selectedOrderDetail),
      detailFailure: clearDetailFailure
          ? null
          : (detailFailure ?? this.detailFailure),
      isLoadingTracking: isLoadingTracking ?? this.isLoadingTracking,
      selectedTracking: clearSelectedTracking
          ? null
          : (selectedTracking ?? this.selectedTracking),
      trackingFailure: clearTrackingFailure
          ? null
          : (trackingFailure ?? this.trackingFailure),
      isReordering: isReordering ?? this.isReordering,
      reorderResult: clearReorderResult
          ? null
          : (reorderResult ?? this.reorderResult),
      failure: clearFailure ? null : (failure ?? this.failure),
      errorMessage:
          clearErrorMessage ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [
        status,
        orders,
        pagination,
        isLoadingMore,
        isLoadingDetail,
        selectedOrderDetail,
        detailFailure,
        isLoadingTracking,
        selectedTracking,
        trackingFailure,
        isReordering,
        reorderResult,
        failure,
        errorMessage,
      ];
}

class OrdersCubit extends Cubit<OrdersState> {
  final OrdersRepository _repository;

  OrdersCubit({OrdersRepository? repository})
      : _repository = repository ?? OrdersRepositoryImpl(),
        super(const OrdersState());

  @override
  void emit(OrdersState state) {
    if (isClosed) return;
    super.emit(state);
  }

  /// Load orders if not yet loaded (account stats, etc.).
  Future<void> ensureOrdersLoaded({bool forceRefresh = false}) async {
    if (state.isLoading) return;
    if (!forceRefresh && state.status == OrdersStatus.loaded) return;
    await fetchOrders(refresh: true);
  }

  /// Fetch order history with pagination support
  Future<void> fetchOrders({bool refresh = false}) async {
    if (state.isLoading) return;

    final targetPage = refresh ? 1 : state.pagination.currentPage;

    emit(state.copyWith(
      status: OrdersStatus.loading,
      clearFailure: true,
      clearErrorMessage: true,
    ));

    try {
      final response = await _repository.getOrders(page: targetPage);

      emit(state.copyWith(
        status: OrdersStatus.loaded,
        orders: response.items,
        pagination: response.pagination,
        clearFailure: true,
        clearErrorMessage: true,
      ));
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        status: OrdersStatus.error,
        failure: f,
        errorMessage: f.message,
      ));
    } catch (e) {
      emit(state.copyWith(
        status: OrdersStatus.error,
        errorMessage: 'تعذر استرجاع الطلبات. يرجى المحاولة مجدداً.',
      ));
    }
  }

  /// Load next page in order history
  Future<void> loadMoreOrders() async {
    if (state.isLoadingMore ||
        state.isLoading ||
        !state.pagination.hasMore) {
      return;
    }

    final nextPage = state.pagination.currentPage + 1;

    emit(state.copyWith(isLoadingMore: true));

    try {
      final response = await _repository.getOrders(page: nextPage);

      // Section 7: Prevent duplicate orders
      final existingIds = state.orders.map((o) => o.id).toSet();
      final newItems = response.items
          .where((o) => !existingIds.contains(o.id))
          .toList();

      emit(state.copyWith(
        isLoadingMore: false,
        orders: [...state.orders, ...newItems],
        pagination: response.pagination,
      ));
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        isLoadingMore: false,
        failure: f,
        errorMessage: f.message,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoadingMore: false,
        errorMessage: 'تعذر تحميل المزيد من الطلبات.',
      ));
    }
  }

  /// Fetch authoritative order detail
  Future<void> fetchOrderDetail(int orderId) async {
    emit(state.copyWith(
      isLoadingDetail: true,
      clearDetailFailure: true,
    ));

    try {
      final detail = await _repository.getOrderDetail(orderId);

      emit(state.copyWith(
        isLoadingDetail: false,
        selectedOrderDetail: detail,
        clearDetailFailure: true,
      ));
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        isLoadingDetail: false,
        detailFailure: f,
        errorMessage: f.message,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoadingDetail: false,
        errorMessage: 'تعذر استرجاع تفاصيل الطلب.',
      ));
    }
  }

  /// Fetch authoritative order tracking
  Future<void> fetchOrderTracking(int orderId) async {
    emit(state.copyWith(
      isLoadingTracking: true,
      clearTrackingFailure: true,
    ));

    try {
      final tracking = await _repository.getOrderTracking(orderId);

      emit(state.copyWith(
        isLoadingTracking: false,
        selectedTracking: tracking,
        clearTrackingFailure: true,
      ));
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        isLoadingTracking: false,
        trackingFailure: f,
        errorMessage: f.message,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoadingTracking: false,
        errorMessage: 'تعذر استرجاع تتبع الطلب.',
      ));
    }
  }

  /// Re-add available order items to the current cart (authoritative current truth).
  Future<ReorderResultModel?> reorder(int orderId) async {
    if (state.isReordering) return null;

    emit(state.copyWith(
      isReordering: true,
      clearReorderResult: true,
      clearErrorMessage: true,
    ));

    try {
      final result = await _repository.reorder(orderId);
      emit(state.copyWith(
        isReordering: false,
        reorderResult: result,
        clearErrorMessage: true,
      ));
      return result;
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        isReordering: false,
        failure: f,
        errorMessage: f.message,
      ));
      return null;
    } catch (_) {
      emit(state.copyWith(
        isReordering: false,
        errorMessage: 'تعذر إعادة الطلب إلى السلة.',
      ));
      return null;
    }
  }

  Future<OrderDetailModel?> confirmDelivery(int orderId) async {
    emit(state.copyWith(isLoadingDetail: true, clearErrorMessage: true));
    try {
      final detail = await _repository.confirmDelivery(orderId);
      emit(state.copyWith(
        isLoadingDetail: false,
        selectedOrderDetail: detail,
        clearErrorMessage: true,
      ));
      return detail;
    } on ApiFailure catch (f) {
      emit(state.copyWith(
        isLoadingDetail: false,
        detailFailure: f,
        errorMessage: f.message,
      ));
      return null;
    } catch (_) {
      emit(state.copyWith(
        isLoadingDetail: false,
        errorMessage: 'تعذر تأكيد استلام الطلب.',
      ));
      return null;
    }
  }

  Future<bool> submitOrderRatings({
    required int orderId,
    required List<Map<String, dynamic>> products,
    int? driverRating,
    String? driverComment,
  }) async {
    try {
      await _repository.submitRatings(
        orderId: orderId,
        products: products,
        driverRating: driverRating,
        driverComment: driverComment,
      );
      return true;
    } on ApiFailure catch (f) {
      emit(state.copyWith(errorMessage: f.message));
      return false;
    } catch (_) {
      emit(state.copyWith(errorMessage: 'تعذر حفظ التقييم.'));
      return false;
    }
  }

  void clearReorderResult() {
    emit(state.copyWith(clearReorderResult: true));
  }

  /// Section 34 & PBI-39.19: Customer-scoped cache isolation - clear state upon logout
  void clearOrders() {
    emit(const OrdersState());
  }
}
