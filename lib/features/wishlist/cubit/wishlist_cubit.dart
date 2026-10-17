import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_failure.dart';
import '../../../data/models/catalog/product_list_item_model.dart';
import '../../../data/datasources/guest_cart_wishlist_storage.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/wishlist_repository.dart';

enum WishlistStatus { initial, loading, loaded, error }

class WishlistState extends Equatable {
  final WishlistStatus status;
  final List<ProductListItemModel> items;
  final Set<String> productIds;
  final String? errorMessage;
  final bool isMutating;

  const WishlistState({
    this.status = WishlistStatus.initial,
    this.items = const [],
    this.productIds = const {},
    this.errorMessage,
    this.isMutating = false,
  });

  bool contains(dynamic id) {
    if (id == null) return false;
    return productIds.contains(id.toString());
  }

  List<ProductModel> get wishlistProducts {
    return items.map((item) => ProductModel.fromApiListItem(item)).toList();
  }

  WishlistState copyWith({
    WishlistStatus? status,
    List<ProductListItemModel>? items,
    Set<String>? productIds,
    String? errorMessage,
    bool? isMutating,
  }) {
    return WishlistState(
      status: status ?? this.status,
      items: items ?? this.items,
      productIds: productIds ?? this.productIds,
      errorMessage: errorMessage,
      isMutating: isMutating ?? this.isMutating,
    );
  }

  @override
  List<Object?> get props =>
      [status, items, productIds, errorMessage, isMutating];
}

class WishlistCubit extends Cubit<WishlistState> {
  final WishlistRepository _repository;
  final GuestCartWishlistStorage _guestStorage;
  bool _isAuthenticated = true;

  WishlistCubit({
    WishlistRepository? repository,
    GuestCartWishlistStorage? guestStorage,
  })  : _repository = repository ?? WishlistRepositoryImpl(),
        _guestStorage =
            guestStorage ?? SharedPreferencesGuestCartWishlistStorage(),
        super(const WishlistState());

  Future<void> fetchWishlist() async {
    if (!_isAuthenticated) return _loadGuestWishlist();
    emit(state.copyWith(status: WishlistStatus.loading, errorMessage: null));
    try {
      final response = await _repository.getWishlist();
      final ids = response.items.map((i) => i.id.toString()).toSet();
      emit(state.copyWith(
        status: WishlistStatus.loaded,
        items: response.items,
        productIds: ids,
        errorMessage: null,
      ));
    } catch (e) {
      emit(state.copyWith(
        status: WishlistStatus.error,
        errorMessage: e is ApiFailure ? e.message : 'فشل تحميل قائمة المفضلة.',
      ));
    }
  }

  Future<void> setAuthenticated(bool isAuthenticated) async {
    _isAuthenticated = isAuthenticated;
    if (!isAuthenticated) await _loadGuestWishlist();
  }

  Future<bool> synchronizeGuestWishlist() async {
    if (!_isAuthenticated) return false;

    var pendingEntries = await _guestStorage.wishlistEntries();
    for (final entry in List<GuestWishlistEntry>.from(pendingEntries)) {
      try {
        await _repository.addToWishlist(entry.productId);
        pendingEntries = pendingEntries
            .where((candidate) => candidate.productId != entry.productId)
            .toList();
        await _guestStorage.saveWishlistEntries(pendingEntries);
      } on ApiFailure {
        // Keep failed entries locally; wishlist addition is server-idempotent.
      }
    }
    await fetchWishlist();
    return pendingEntries.isEmpty;
  }

  Future<void> _loadGuestWishlist() async {
    emit(state.copyWith(status: WishlistStatus.loading, errorMessage: null));
    try {
      final entries = await _guestStorage.wishlistEntries();
      final items = entries.map(_guestEntryToProduct).toList();
      emit(state.copyWith(
        status: WishlistStatus.loaded,
        items: items,
        productIds: entries.map((entry) => entry.productId.toString()).toSet(),
        errorMessage: null,
      ));
    } catch (_) {
      emit(state.copyWith(
        status: WishlistStatus.error,
        errorMessage: 'تعذر فتح المفضلة المحفوظة على الجهاز.',
      ));
    }
  }

  ProductListItemModel _guestEntryToProduct(GuestWishlistEntry entry) =>
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

  Future<void> toggleWishlist(
    dynamic productId, {
    ProductModel? guestProduct,
  }) async {
    final strId = productId.toString();
    final intId = int.tryParse(strId);
    if (intId == null || intId <= 0) return;

    final wasFavorite = state.contains(strId);
    final previousIds = Set<String>.from(state.productIds);
    final previousItems = List<ProductListItemModel>.from(state.items);

    // Optimistic update as allowed by Section 6 of Sprint 36
    final updatedIds = Set<String>.from(state.productIds);
    if (wasFavorite) {
      updatedIds.remove(strId);
    } else {
      updatedIds.add(strId);
    }

    emit(state.copyWith(
      productIds: updatedIds,
      isMutating: true,
      errorMessage: null,
    ));

    try {
      if (!_isAuthenticated) {
        final entries =
            List<GuestWishlistEntry>.from(await _guestStorage.wishlistEntries());
        if (wasFavorite) {
          entries.removeWhere((entry) => entry.productId == intId);
        } else {
          entries.add(GuestWishlistEntry(
            productId: intId,
            name: guestProduct?.name ?? 'منتج',
            imageUrl: guestProduct?.image ?? 'assets/images/placeholder.png',
            displayPrice: guestProduct?.priceSAR ?? 0,
          ));
        }
        await _guestStorage.saveWishlistEntries(entries);
        await _loadGuestWishlist();
        return;
      }
      if (wasFavorite) {
        await _repository.removeFromWishlist(intId);
        final updatedItems = state.items.where((i) => i.id != intId).toList();
        emit(state.copyWith(
          items: updatedItems,
          productIds: updatedIds,
          isMutating: false,
        ));
      } else {
        await _repository.addToWishlist(intId);
        final response = await _repository.getWishlist();
        final ids = response.items.map((i) => i.id.toString()).toSet();
        emit(state.copyWith(
          status: WishlistStatus.loaded,
          items: response.items,
          productIds: ids,
          isMutating: false,
        ));
      }
    } catch (e) {
      // Mandatory Rollback on failure
      emit(state.copyWith(
        productIds: previousIds,
        items: previousItems,
        isMutating: false,
        errorMessage: e is ApiFailure ? e.message : 'تعذر تحديث المفضلة.',
      ));
    }
  }

  Future<void> removeFromWishlist(dynamic productId) async {
    final strId = productId.toString();
    final intId = int.tryParse(strId);
    if (intId == null || intId <= 0) return;

    final previousIds = Set<String>.from(state.productIds);
    final previousItems = List<ProductListItemModel>.from(state.items);

    final updatedIds = Set<String>.from(state.productIds)..remove(strId);
    final updatedItems = state.items.where((i) => i.id != intId).toList();

    emit(state.copyWith(
      productIds: updatedIds,
      items: updatedItems,
      isMutating: true,
      errorMessage: null,
    ));

    try {
      if (!_isAuthenticated) {
        final entries = await _guestStorage.wishlistEntries();
        entries.removeWhere((entry) => entry.productId == intId);
        await _guestStorage.saveWishlistEntries(entries);
        await _loadGuestWishlist();
        return;
      }
      await _repository.removeFromWishlist(intId);
      emit(state.copyWith(isMutating: false));
    } catch (e) {
      emit(state.copyWith(
        productIds: previousIds,
        items: previousItems,
        isMutating: false,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر إزالة المنتج من المفضلة.',
      ));
    }
  }

  void clearWishlist() {
    _isAuthenticated = false;
    emit(const WishlistState(
      status: WishlistStatus.initial,
      items: [],
      productIds: {},
    ));
  }

  Future<void> clearGuestData() => _guestStorage.saveWishlistEntries(const []);
}
