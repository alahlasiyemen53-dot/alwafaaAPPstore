import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_failure.dart';
import '../../../data/datasources/guest_discovery_storage.dart';
import '../../../data/models/customer/search_history_item_model.dart';
import '../../../data/repositories/search_history_repository.dart';

enum SearchHistoryStatus { initial, loading, loaded, error }

class SearchHistoryState extends Equatable {
  final SearchHistoryStatus status;
  final List<SearchHistoryItemModel> items;
  final String? errorMessage;

  const SearchHistoryState({
    this.status = SearchHistoryStatus.initial,
    this.items = const [],
    this.errorMessage,
  });

  SearchHistoryState copyWith({
    SearchHistoryStatus? status,
    List<SearchHistoryItemModel>? items,
    String? errorMessage,
    bool clearError = false,
  }) {
    return SearchHistoryState(
      status: status ?? this.status,
      items: items ?? this.items,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, items, errorMessage];
}

class SearchHistoryCubit extends Cubit<SearchHistoryState> {
  final SearchHistoryRepository _repository;
  final SharedPreferencesGuestDiscoveryStorage _guestStorage;
  bool _isAuthenticated = false;

  SearchHistoryCubit({
    SearchHistoryRepository? repository,
    SharedPreferencesGuestDiscoveryStorage? guestStorage,
  })  : _repository = repository ?? SearchHistoryRepositoryImpl(),
        _guestStorage =
            guestStorage ?? SharedPreferencesGuestDiscoveryStorage(),
        super(const SearchHistoryState());

  Future<void> setAuthenticated(bool isAuthenticated) async {
    _isAuthenticated = isAuthenticated;
    if (!isAuthenticated) {
      await load();
    }
  }

  Future<void> load() async {
    emit(state.copyWith(status: SearchHistoryStatus.loading, clearError: true));
    try {
      if (_isAuthenticated) {
        final response = await _repository.getSearchHistory();
        emit(state.copyWith(
          status: SearchHistoryStatus.loaded,
          items: response.items,
          clearError: true,
        ));
      } else {
        await _loadGuest();
      }
    } catch (e) {
      emit(state.copyWith(
        status: SearchHistoryStatus.error,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل سجل البحث.',
      ));
    }
  }

  /// Record a submitted/executed search only (not keystrokes).
  Future<void> recordSubmittedQuery(String query) async {
    final trimmed = query.trim();
    if (trimmed.length < 2) return;

    if (!_isAuthenticated) {
      await _guestStorage.recordSearchQuery(trimmed);
      await _loadGuest();
      return;
    }

    try {
      await _repository.recordSearch(trimmed);
      await load();
    } on ApiFailure {
      // Best-effort recording.
    } catch (_) {}
  }

  Future<void> deleteItem(SearchHistoryItemModel item) async {
    if (!_isAuthenticated) {
      final entries = await _guestStorage.searchHistoryEntries();
      entries.removeWhere(
        (e) => e.query.trim().toLowerCase() == item.query.trim().toLowerCase(),
      );
      await _guestStorage.saveSearchHistoryEntries(entries);
      await _loadGuest();
      return;
    }

    if (item.id == null) return;
    try {
      await _repository.deleteItem(item.id!);
      emit(state.copyWith(
        items: state.items.where((e) => e.id != item.id).toList(),
      ));
    } catch (e) {
      emit(state.copyWith(
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر حذف عنصر سجل البحث.',
      ));
    }
  }

  Future<void> clearAll() async {
    if (!_isAuthenticated) {
      await _guestStorage.saveSearchHistoryEntries(const []);
      emit(state.copyWith(
        status: SearchHistoryStatus.loaded,
        items: const [],
        clearError: true,
      ));
      return;
    }

    try {
      await _repository.clearAll();
      emit(state.copyWith(
        status: SearchHistoryStatus.loaded,
        items: const [],
        clearError: true,
      ));
    } catch (e) {
      emit(state.copyWith(
        errorMessage: e is ApiFailure ? e.message : 'تعذر مسح سجل البحث.',
      ));
    }
  }

  Future<bool> synchronizeGuestSearchHistory() async {
    if (!_isAuthenticated) return false;
    final entries = await _guestStorage.searchHistoryEntries();
    if (entries.isEmpty) {
      await load();
      return true;
    }
    try {
      final queries = entries.map((e) => e.query).toList();
      final response = await _repository.mergeGuestQueries(queries);
      await _guestStorage.saveSearchHistoryEntries(const []);
      emit(state.copyWith(
        status: SearchHistoryStatus.loaded,
        items: response.items,
        clearError: true,
      ));
      return true;
    } on ApiFailure {
      await load();
      return false;
    }
  }

  Future<void> _loadGuest() async {
    final entries = await _guestStorage.searchHistoryEntries();
    emit(state.copyWith(
      status: SearchHistoryStatus.loaded,
      items: entries
          .map((e) => SearchHistoryItemModel(
                query: e.query,
                lastSearchedAt: e.lastSearchedAt,
              ))
          .toList(),
      clearError: true,
    ));
  }

  void clearSession() {
    _isAuthenticated = false;
    emit(const SearchHistoryState());
  }

  Future<void> clearGuestData() =>
      _guestStorage.saveSearchHistoryEntries(const []);
}
