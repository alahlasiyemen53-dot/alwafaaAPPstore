import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_failure.dart';
import '../../../data/models/customer/notification_model.dart';
import '../../../data/repositories/notifications_repository.dart';

enum NotificationsStatus { initial, loading, loaded, error }

class NotificationsState extends Equatable {
  final NotificationsStatus status;
  final List<CustomerNotificationModel> items;
  final int unreadCount;
  final NotificationsPaginationModel pagination;
  final bool isLoadingMore;
  final bool isMutating;
  final String? errorMessage;

  const NotificationsState({
    this.status = NotificationsStatus.initial,
    this.items = const [],
    this.unreadCount = 0,
    this.pagination = const NotificationsPaginationModel.empty(),
    this.isLoadingMore = false,
    this.isMutating = false,
    this.errorMessage,
  });

  NotificationsState copyWith({
    NotificationsStatus? status,
    List<CustomerNotificationModel>? items,
    int? unreadCount,
    NotificationsPaginationModel? pagination,
    bool? isLoadingMore,
    bool? isMutating,
    String? errorMessage,
    bool clearError = false,
  }) {
    return NotificationsState(
      status: status ?? this.status,
      items: items ?? this.items,
      unreadCount: unreadCount ?? this.unreadCount,
      pagination: pagination ?? this.pagination,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      isMutating: isMutating ?? this.isMutating,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [
        status,
        items,
        unreadCount,
        pagination,
        isLoadingMore,
        isMutating,
        errorMessage,
      ];
}

class NotificationsCubit extends Cubit<NotificationsState> {
  final NotificationsRepository _repository;

  NotificationsCubit({NotificationsRepository? repository})
      : _repository = repository ?? NotificationsRepositoryImpl(),
        super(const NotificationsState());

  Future<void> load({bool refresh = true}) async {
    emit(state.copyWith(
      status: NotificationsStatus.loading,
      clearError: true,
    ));
    try {
      final response = await _repository.getNotifications(page: 1);
      emit(state.copyWith(
        status: NotificationsStatus.loaded,
        items: response.items,
        unreadCount: response.unreadCount,
        pagination: response.pagination,
        clearError: true,
      ));
    } catch (e) {
      emit(state.copyWith(
        status: NotificationsStatus.error,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل الإشعارات.',
      ));
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.pagination.hasMore) return;
    emit(state.copyWith(isLoadingMore: true));
    try {
      final nextPage = state.pagination.currentPage + 1;
      final response = await _repository.getNotifications(page: nextPage);
      final existingIds = state.items.map((e) => e.id).toSet();
      final newItems =
          response.items.where((e) => !existingIds.contains(e.id)).toList();
      emit(state.copyWith(
        isLoadingMore: false,
        items: [...state.items, ...newItems],
        unreadCount: response.unreadCount,
        pagination: response.pagination,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoadingMore: false,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل المزيد من الإشعارات.',
      ));
    }
  }

  Future<void> markRead(int id) async {
    final index = state.items.indexWhere((e) => e.id == id);
    if (index < 0) return;
    final target = state.items[index];
    if (!target.isUnread) return;

    final previous = List<CustomerNotificationModel>.from(state.items);
    final previousUnread = state.unreadCount;
    final updated = List<CustomerNotificationModel>.from(state.items);
    updated[index] = target.copyWith(readAt: DateTime.now());

    emit(state.copyWith(
      items: updated,
      unreadCount: (previousUnread - 1).clamp(0, previousUnread),
      isMutating: true,
      clearError: true,
    ));

    try {
      await _repository.markRead(id);
      emit(state.copyWith(isMutating: false));
    } catch (e) {
      emit(state.copyWith(
        items: previous,
        unreadCount: previousUnread,
        isMutating: false,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تعليم الإشعار كمقروء.',
      ));
    }
  }

  Future<void> markAllRead() async {
    if (state.unreadCount <= 0) return;
    final previous = List<CustomerNotificationModel>.from(state.items);
    final previousUnread = state.unreadCount;
    final now = DateTime.now();
    final updated = state.items
        .map((e) => e.isUnread ? e.copyWith(readAt: now) : e)
        .toList();

    emit(state.copyWith(
      items: updated,
      unreadCount: 0,
      isMutating: true,
      clearError: true,
    ));

    try {
      await _repository.markAllRead();
      emit(state.copyWith(isMutating: false));
    } catch (e) {
      emit(state.copyWith(
        items: previous,
        unreadCount: previousUnread,
        isMutating: false,
        errorMessage: e is ApiFailure
            ? e.message
            : 'تعذر تعليم جميع الإشعارات كمقروءة.',
      ));
    }
  }

  void clearSession() {
    emit(const NotificationsState());
  }
}
