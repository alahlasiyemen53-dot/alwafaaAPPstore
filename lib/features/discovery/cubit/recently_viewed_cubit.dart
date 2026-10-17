import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_failure.dart';
import '../../../data/datasources/guest_discovery_storage.dart';
import '../../../data/models/catalog/product_list_item_model.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/recently_viewed_repository.dart';

enum RecentlyViewedStatus { initial, loading, loaded, error }

class RecentlyViewedState extends Equatable {
  final RecentlyViewedStatus status;
  final List<ProductListItemModel> items;
  final String? errorMessage;

  const RecentlyViewedState({
    this.status = RecentlyViewedStatus.initial,
    this.items = const [],
    this.errorMessage,
  });

  List<ProductModel> get products =>
      items.map(ProductModel.fromApiListItem).toList();

  RecentlyViewedState copyWith({
    RecentlyViewedStatus? status,
    List<ProductListItemModel>? items,
    String? errorMessage,
    bool clearError = false,
  }) {
    return RecentlyViewedState(
      status: status ?? this.status,
      items: items ?? this.items,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, items, errorMessage];
}

class RecentlyViewedCubit extends Cubit<RecentlyViewedState> {
  final RecentlyViewedRepository _repository;
  final SharedPreferencesGuestDiscoveryStorage _guestStorage;
  bool _isAuthenticated = false;

  RecentlyViewedCubit({
    RecentlyViewedRepository? repository,
    SharedPreferencesGuestDiscoveryStorage? guestStorage,
  })  : _repository = repository ?? RecentlyViewedRepositoryImpl(),
        _guestStorage =
            guestStorage ?? SharedPreferencesGuestDiscoveryStorage(),
        super(const RecentlyViewedState());

  Future<void> setAuthenticated(bool isAuthenticated) async {
    _isAuthenticated = isAuthenticated;
    if (!isAuthenticated) {
      await load();
    }
  }

  Future<void> load() async {
    emit(state.copyWith(
      status: RecentlyViewedStatus.loading,
      clearError: true,
    ));
    try {
      if (_isAuthenticated) {
        final items = await _repository.getRecentlyViewed();
        emit(state.copyWith(
          status: RecentlyViewedStatus.loaded,
          items: items,
          clearError: true,
        ));
      } else {
        await _loadGuest();
      }
    } catch (e) {
      emit(state.copyWith(
        status: RecentlyViewedStatus.error,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل المنتجات التي شاهدتها.',
      ));
    }
  }

  Future<void> recordView(ProductModel product) async {
    final intId = product.serverId ?? int.tryParse(product.id);
    if (intId == null || intId <= 0) return;

    if (!_isAuthenticated) {
      await _guestStorage.recordRecentlyViewed(GuestRecentlyViewedEntry(
        productId: intId,
        name: product.name,
        imageUrl: product.image,
        displayPrice: product.priceSAR,
        viewedAt: DateTime.now(),
      ));
      await _loadGuest();
      return;
    }

    try {
      await _repository.recordView(intId);
      // Refresh quietly so home rail stays current.
      final items = await _repository.getRecentlyViewed();
      emit(state.copyWith(
        status: RecentlyViewedStatus.loaded,
        items: items,
        clearError: true,
      ));
    } on ApiFailure {
      // View recording is best-effort; keep current list.
    } catch (_) {}
  }

  Future<bool> synchronizeGuestRecentlyViewed() async {
    if (!_isAuthenticated) return false;
    final entries = await _guestStorage.recentlyViewedEntries();
    if (entries.isEmpty) {
      await load();
      return true;
    }
    try {
      final ids = entries.map((e) => e.productId).toList();
      final items = await _repository.mergeGuestViews(ids);
      await _guestStorage.saveRecentlyViewedEntries(const []);
      emit(state.copyWith(
        status: RecentlyViewedStatus.loaded,
        items: items,
        clearError: true,
      ));
      return true;
    } on ApiFailure {
      await load();
      return false;
    }
  }

  Future<void> _loadGuest() async {
    final entries = await _guestStorage.recentlyViewedEntries();
    final items = entries.map(_guestToListItem).toList();
    emit(state.copyWith(
      status: RecentlyViewedStatus.loaded,
      items: items,
      clearError: true,
    ));
  }

  ProductListItemModel _guestToListItem(GuestRecentlyViewedEntry entry) =>
      ProductListItemModel(
        id: entry.productId,
        nameAr: entry.name,
        nameEn: '',
        slug: entry.productId.toString(),
        primaryImage: entry.imageUrl,
        priceSummary: ProductPriceSummary(
          minPrice: entry.displayPrice,
          maxPrice: entry.displayPrice,
          currency: 'YER',
          hasRange: false,
        ),
        availability: const ProductAvailabilitySummary(
          isAvailable: true,
          status: 'in_stock',
        ),
        rating: const ProductRatingSummary(average: 0, reviewsCount: 0),
        badges: const ProductBadges(
          isBestSeller: false,
          isNew: false,
          isExclusive: false,
        ),
      );

  void clearSession() {
    _isAuthenticated = false;
    emit(const RecentlyViewedState());
  }

  Future<void> clearGuestData() =>
      _guestStorage.saveRecentlyViewedEntries(const []);
}
