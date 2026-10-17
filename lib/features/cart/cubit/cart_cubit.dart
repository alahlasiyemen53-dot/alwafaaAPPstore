import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_failure.dart';
import '../../../core/utils/idempotency_key_generator.dart';
import '../../../data/datasources/guest_cart_wishlist_storage.dart';
import '../../../data/models/cart/cart_api_model.dart';
import '../../../data/models/cart_item_model.dart';
import '../../../data/models/category_model.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/cart_repository.dart';

enum CartStatus { initial, loading, loaded, error }

class CartState extends Equatable {
  final CartStatus status;
  final CartApiModel? cart;
  final List<CartItemModel> items;
  final Set<String> selectedKeys;
  final bool isMutating;
  final bool isApplyingCoupon;
  final String? couponError;
  final String? errorMessage;
  final bool giftWrapping;

  const CartState({
    this.status = CartStatus.initial,
    this.cart,
    this.items = const [],
    this.selectedKeys = const {},
    this.isMutating = false,
    this.isApplyingCoupon = false,
    this.couponError,
    this.errorMessage,
    this.giftWrapping = true,
  });

  String? get promoCode => cart?.coupon?.code;
  bool get isPromoValid => cart?.coupon?.isValid ?? false;
  double get discountAmountSAR => cart?.pricing.totalDiscount ?? 0.0;
  double get subtotalSAR =>
      (cart != null && cart!.cartId > 0 && cart!.pricing.itemsSubtotal > 0)
          ? cart!.pricing.itemsSubtotal
          : items.fold(0.0, (total, item) => total + item.totalPriceSAR);
  double get totalSAR =>
      (cart != null && cart!.cartId > 0 && cart!.pricing.estimatedTotal > 0)
          ? cart!.pricing.estimatedTotal
          : subtotalSAR;

  // Sprint 36 Section 18: Cart does not enforce mock shipping fees
  double get shippingCostSAR => 0.0;

  int get totalItemsCount => items.isNotEmpty
      ? items.fold(0, (sum, item) => sum + item.quantity)
      : (cart?.totalQuantity ?? 0);

  String get currencySymbol => cart?.currency.symbol ?? 'ر.ي';

  List<CartItemModel> get selectedItems =>
      items.where((item) => selectedKeys.contains(item.selectionKey)).toList();

  bool get hasSelection => selectedItems.isNotEmpty;

  bool get isAllSelected =>
      items.isNotEmpty && selectedItems.length == items.length;

  bool get isPartialSelection =>
      hasSelection && selectedItems.length < items.length;

  int get selectedLinesCount => selectedItems.length;

  int get selectedQuantityCount =>
      selectedItems.fold(0, (sum, item) => sum + item.quantity);

  double get selectedSubtotalSAR =>
      selectedItems.fold(0.0, (total, item) => total + item.totalPriceSAR);

  /// Server totals only when the full cart is selected; otherwise local selected sum.
  double get checkoutSubtotalSAR =>
      isPartialSelection ? selectedSubtotalSAR : subtotalSAR;

  double get checkoutDiscountSAR =>
      isPartialSelection ? 0.0 : discountAmountSAR;

  double get checkoutTotalSAR =>
      isPartialSelection ? selectedSubtotalSAR : totalSAR;

  /// Server cart line ids for partial checkout. Null = full cart (omit field).
  /// Empty list = selection invalid for checkout (block).
  List<int>? get selectedCartItemIdsForCheckout {
    if (!hasSelection) return const [];
    if (isAllSelected) return null;
    final ids = selectedItems.map((i) => i.id).whereType<int>().toList();
    if (ids.length != selectedItems.length) return const [];
    return ids;
  }

  bool isItemSelected(CartItemModel item) =>
      selectedKeys.contains(item.selectionKey);

  CartState copyWith({
    CartStatus? status,
    CartApiModel? cart,
    List<CartItemModel>? items,
    Set<String>? selectedKeys,
    bool? isMutating,
    bool? isApplyingCoupon,
    String? couponError,
    String? errorMessage,
    bool? giftWrapping,
  }) {
    return CartState(
      status: status ?? this.status,
      cart: cart ?? this.cart,
      items: items ?? this.items,
      selectedKeys: selectedKeys ?? this.selectedKeys,
      isMutating: isMutating ?? this.isMutating,
      isApplyingCoupon: isApplyingCoupon ?? this.isApplyingCoupon,
      couponError: couponError,
      errorMessage: errorMessage,
      giftWrapping: giftWrapping ?? this.giftWrapping,
    );
  }

  @override
  List<Object?> get props => [
        status,
        cart,
        items,
        selectedKeys,
        isMutating,
        isApplyingCoupon,
        couponError,
        errorMessage,
        giftWrapping,
      ];
}

class CartCubit extends Cubit<CartState> {
  final CartRepository _repository;
  final GuestCartWishlistStorage _guestStorage;
  bool _operationInProgress = false;
  bool _syncInProgress = false;
  // Guest until splash/session resolves; defaulting to true caused unauthenticated
  // API adds (and opaque failures) before setAuthenticated(false) ran.
  bool _isAuthenticated = false;

  bool get isAuthenticated => _isAuthenticated;

  CartCubit({
    CartRepository? repository,
    GuestCartWishlistStorage? guestStorage,
  })  : _repository = repository ?? CartRepositoryImpl(),
        _guestStorage =
            guestStorage ?? SharedPreferencesGuestCartWishlistStorage(),
        super(const CartState());

  List<CartItemModel> _mapItems(CartApiModel cart) {
    return cart.items.map((i) => CartItemModel.fromApiModel(i)).toList();
  }

  Set<String> _reconcileSelection({
    required List<CartItemModel> nextItems,
    required List<CartItemModel> previousItems,
    required Set<String> previousKeys,
  }) {
    if (nextItems.isEmpty) return {};
    if (previousKeys.isEmpty) {
      return nextItems.map((item) => item.selectionKey).toSet();
    }

    final previouslySelectedVariantIds = previousItems
        .where((item) => previousKeys.contains(item.selectionKey))
        .map((item) => item.productVariantId)
        .whereType<int>()
        .toSet();

    final previousIdentity = previousItems.map((item) {
      if (item.id != null) return 'id:${item.id}';
      if (item.productVariantId != null) return 'variant:${item.productVariantId}';
      return item.selectionKey;
    }).toSet();

    final next = <String>{};
    for (final item in nextItems) {
      final key = item.selectionKey;
      final variantId = item.productVariantId;
      final identity = item.id != null
          ? 'id:${item.id}'
          : (variantId != null ? 'variant:$variantId' : key);
      final isNew = !previousIdentity.contains(identity) &&
          !(variantId != null && previousIdentity.contains('variant:$variantId'));

      final wasSelected = previousKeys.contains(key) ||
          (variantId != null && previousKeys.contains('variant:$variantId')) ||
          (variantId != null && previouslySelectedVariantIds.contains(variantId));

      if (wasSelected || isNew) {
        next.add(key);
      }
    }

    return next;
  }

  void _emitLoadedCart({
    required CartApiModel cart,
    required List<CartItemModel> items,
    bool? isApplyingCoupon,
    bool clearCouponError = true,
  }) {
    emit(state.copyWith(
      status: CartStatus.loaded,
      cart: cart,
      items: items,
      selectedKeys: _reconcileSelection(
        nextItems: items,
        previousItems: state.items,
        previousKeys: state.selectedKeys,
      ),
      isMutating: false,
      isApplyingCoupon: isApplyingCoupon,
      couponError: clearCouponError ? null : state.couponError,
      errorMessage: null,
    ));
  }

  void toggleItemSelection(String selectionKey) {
    final next = Set<String>.from(state.selectedKeys);
    if (next.contains(selectionKey)) {
      next.remove(selectionKey);
    } else {
      next.add(selectionKey);
    }
    emit(state.copyWith(selectedKeys: next));
  }

  void selectAllItems() {
    emit(state.copyWith(
      selectedKeys: state.items.map((item) => item.selectionKey).toSet(),
    ));
  }

  void clearItemSelection() {
    emit(state.copyWith(selectedKeys: {}));
  }

  void toggleSelectAll() {
    if (state.isAllSelected) {
      clearItemSelection();
    } else {
      selectAllItems();
    }
  }

  Future<void> fetchCart() async {
    if (!_isAuthenticated) return _loadGuestCart();
    emit(state.copyWith(status: CartStatus.loading, errorMessage: null));
    try {
      final cart = await _repository.getCart();
      _emitLoadedCart(cart: cart, items: _mapItems(cart));
    } catch (e) {
      if (e is ApiFailure && e.kind == ApiFailureKind.unauthenticated) {
        _isAuthenticated = false;
        return _loadGuestCart();
      }
      emit(state.copyWith(
        status: CartStatus.error,
        isMutating: false,
        errorMessage: e is ApiFailure ? e.message : 'فشل جلب بيانات السلة.',
      ));
    }
  }

  Future<void> setAuthenticated(bool isAuthenticated) async {
    _isAuthenticated = isAuthenticated;
    if (!isAuthenticated) await _loadGuestCart();
  }

  Future<bool> synchronizeGuestCart() async {
    if (!_isAuthenticated) return false;
    if (_syncInProgress) {
      while (_syncInProgress) {
        await Future<void>.delayed(const Duration(milliseconds: 100));
      }
      return (await _guestStorage.cartLines()).isEmpty;
    }
    _syncInProgress = true;
    try {
      var pendingLines = await _guestStorage.cartLines();
      if (pendingLines.isEmpty) {
        await fetchCart();
        return true;
      }
      for (final line in List<GuestCartLine>.from(pendingLines)) {
        try {
          await _repository.addItem(
            productVariantId: line.productVariantId,
            quantity: line.quantity,
            idempotencyKey: line.syncKey,
          );
          pendingLines = pendingLines
              .where((candidate) =>
                  candidate.productVariantId != line.productVariantId)
              .toList();
          await _guestStorage.saveCartLines(pendingLines);
        } on ApiFailure {
          // Keep this line and its idempotency key for safe retry.
        }
      }
      await fetchCart();
      return pendingLines.isEmpty;
    } finally {
      _syncInProgress = false;
    }
  }

  Future<void> _loadGuestCart() async {
    emit(state.copyWith(status: CartStatus.loading, errorMessage: null));
    try {
      final lines = await _guestStorage.cartLines();
      final items = lines.map(_guestLineToCartItem).toList();
      _emitLoadedCart(cart: CartApiModel.empty(), items: items);
    } catch (_) {
      emit(state.copyWith(
        status: CartStatus.error,
        isMutating: false,
        errorMessage: 'تعذر فتح السلة المحفوظة على الجهاز.',
      ));
    }
  }

  CartItemModel _guestLineToCartItem(GuestCartLine line) {
    final specs = [
      if (line.size != null && line.size!.trim().isNotEmpty) line.size!.trim(),
      if (line.shade != null && line.shade!.name.trim().isNotEmpty)
        line.shade!.name.trim(),
    ].join(' • ');

    return CartItemModel(
      productVariantId: line.productVariantId,
      quantity: line.quantity,
      selectedSize: line.size,
      selectedShade: line.shade,
      variantSpecs: specs.isEmpty ? null : specs,
      unitPrice: line.displayPrice,
      lineTotal: line.displayPrice * line.quantity,
      product: ProductModel(
        id: line.productId.toString(),
        serverId: line.productId,
        name: line.name,
        brand: '',
        category: ProductCategory.all,
        priceSAR: line.displayPrice,
        rating: 0,
        reviewsCount: 0,
        image: line.imageUrl,
        images: [line.imageUrl],
        description: '',
        selectedVariantId: line.productVariantId,
        selectedVariantSku: line.sku,
      ),
    );
  }

  Future<bool> _addToGuestCart({
    required ProductModel product,
    required int targetVariantId,
    required int quantity,
    String? selectedSize,
    ProductShade? selectedShade,
  }) async {
    try {
      final lines = List<GuestCartLine>.from(await _guestStorage.cartLines());
      final existing = lines
          .indexWhere((line) => line.productVariantId == targetVariantId);
      if (existing >= 0) {
        lines[existing] = lines[existing].copyWith(
          quantity: (lines[existing].quantity + quantity).clamp(1, 100),
        );
      } else {
        final selectedVariant = product.detailApiModel?.variants
            .where((variant) => variant.id == targetVariantId)
            .firstOrNull;
        final selectedImage = selectedVariant?.images
                .where((image) => image.isPrimary)
                .firstOrNull
                ?.optimizedUrl ??
            selectedVariant?.images.firstOrNull?.optimizedUrl ??
            product.image;

        var size = selectedSize;
        var shade = selectedShade;
        if ((size == null || size.trim().isEmpty) || shade == null) {
          for (final attr in selectedVariant?.attributes ?? const []) {
            final code = attr.code.toLowerCase();
            final value = attr.displayValue.trim();
            if (value.isEmpty) continue;
            if (size == null || size.trim().isEmpty) {
              if (code.contains('size') ||
                  code.contains('volume') ||
                  code.contains('حجم')) {
                size = value;
              }
            }
            if (shade == null) {
              if (code.contains('shade') ||
                  code.contains('color') ||
                  code.contains('لون')) {
                shade = ProductShade(name: value, hex: '#C5A880');
              }
            }
          }
        }

        lines.add(GuestCartLine(
          productVariantId: targetVariantId,
          productId: product.serverId ?? int.tryParse(product.id) ?? 0,
          name: product.name,
          imageUrl: selectedImage,
          displayPrice: product.priceSAR,
          quantity: quantity.clamp(1, 100),
          syncKey: IdempotencyKeyGenerator.generate(),
          sku: product.selectedVariantSku,
          size: size,
          shade: shade,
        ));
      }
      await _guestStorage.saveCartLines(lines);
      await _loadGuestCart();
      return true;
    } catch (_) {
      emit(state.copyWith(
        isMutating: false,
        errorMessage: 'تعذر حفظ المنتج في السلة على الجهاز.',
      ));
      return false;
    }
  }

  Future<bool> addToCart(
    ProductModel product, {
    int quantity = 1,
    String? selectedSize,
    ProductShade? selectedShade,
    int? variantId,
  }) async {
    final targetVariantId = variantId ?? product.selectedVariantId;
    if (targetVariantId == null || targetVariantId <= 0) {
      emit(state.copyWith(
        errorMessage: 'يرجى اختيار النوع أو الحجم المطلوب أولاً.',
      ));
      return false;
    }

    if (_operationInProgress || _syncInProgress) {
      emit(state.copyWith(
        errorMessage: _syncInProgress
            ? 'جاري مزامنة سلة الضيف، يرجى المحاولة بعد لحظات.'
            : 'يرجى الانتظار حتى تكتمل العملية الحالية.',
      ));
      return false;
    }
    _operationInProgress = true;

    emit(state.copyWith(isMutating: true, errorMessage: null));

    try {
      if (!_isAuthenticated) {
        return await _addToGuestCart(
          product: product,
          targetVariantId: targetVariantId,
          quantity: quantity,
          selectedSize: selectedSize,
          selectedShade: selectedShade,
        );
      }

      // Same variant already in cart → increase quantity instead of a new line.
      final existingIndex = state.items.indexWhere(
        (item) => item.productVariantId == targetVariantId,
      );
      final CartApiModel cart;
      if (existingIndex >= 0) {
        final existing = state.items[existingIndex];
        final itemId = existing.id;
        if (itemId == null) {
          cart = await _repository.addItem(
            productVariantId: targetVariantId,
            quantity: quantity,
            idempotencyKey: IdempotencyKeyGenerator.generate(),
          );
        } else {
          final newQty = (existing.quantity + quantity).clamp(1, 100);
          cart = await _repository.updateItemQuantity(
            itemId: itemId,
            quantity: newQty,
          );
        }
      } else {
        cart = await _repository.addItem(
          productVariantId: targetVariantId,
          quantity: quantity,
          idempotencyKey: IdempotencyKeyGenerator.generate(),
        );
      }
      _emitLoadedCart(cart: cart, items: _mapItems(cart));
      return true;
    } catch (e) {
      if (e is ApiFailure && e.kind == ApiFailureKind.unauthenticated) {
        _isAuthenticated = false;
        try {
          return await _addToGuestCart(
            product: product,
            targetVariantId: targetVariantId,
            quantity: quantity,
            selectedSize: selectedSize,
            selectedShade: selectedShade,
          );
        } catch (_) {}
      }
      emit(state.copyWith(
        isMutating: false,
        errorMessage: _addToCartErrorMessage(e),
      ));
      return false;
    } finally {
      _operationInProgress = false;
    }
  }

  String _addToCartErrorMessage(Object e) {
    if (e is ApiFailure) {
      final message = e.message.trim();
      if (message.isNotEmpty) return message;
    }
    // Type/format failures after a successful add used to look like a hard fail.
    return 'تعذر تحديث السلة بعد الإضافة. افتح السلة للتأكد أو أعد المحاولة.';
  }

  Future<void> updateQuantity(int index, int newQuantity) async {
    if (index < 0 || index >= state.items.length) return;
    final item = state.items[index];
    if (_operationInProgress || _syncInProgress) return;
    _operationInProgress = true;

    emit(state.copyWith(isMutating: true, errorMessage: null));

    try {
      if (!_isAuthenticated) {
        final lines = List<GuestCartLine>.from(await _guestStorage.cartLines());
        final lineIndex = lines.indexWhere(
          (line) => line.productVariantId == item.productVariantId,
        );
        if (lineIndex < 0) {
          emit(state.copyWith(isMutating: false));
          return;
        }
        if (newQuantity <= 0) {
          lines.removeAt(lineIndex);
        } else {
          lines[lineIndex] = lines[lineIndex].copyWith(
            quantity: newQuantity.clamp(1, 100),
          );
        }
        await _guestStorage.saveCartLines(lines);
        await _loadGuestCart();
        return;
      }
      final itemId = item.id;
      if (itemId == null) {
        final variantId = item.productVariantId;
        if (newQuantity <= 0 || variantId == null || variantId <= 0) {
          emit(state.copyWith(
            isMutating: false,
            errorMessage: 'تعذر تحديث العنصر قبل اكتمال مزامنة السلة.',
          ));
          return;
        }
        final cart = await _repository.addItem(
          productVariantId: variantId,
          quantity: newQuantity.clamp(1, 100),
          idempotencyKey: IdempotencyKeyGenerator.generate(),
        );
        _emitLoadedCart(cart: cart, items: _mapItems(cart));
        return;
      }
      final CartApiModel updatedCart;
      if (newQuantity <= 0) {
        updatedCart = await _repository.removeItem(itemId);
      } else {
        updatedCart = await _repository.updateItemQuantity(
          itemId: itemId,
          quantity: newQuantity,
        );
      }

      _emitLoadedCart(cart: updatedCart, items: _mapItems(updatedCart));
    } catch (e) {
      if (e is ApiFailure && e.kind == ApiFailureKind.unauthenticated) {
        _isAuthenticated = false;
        await _loadGuestCart();
        return;
      }
      emit(state.copyWith(
        isMutating: false,
        errorMessage: e is ApiFailure ? e.message : 'فشل تحديث كمية العنصر.',
      ));
    } finally {
      _operationInProgress = false;
    }
  }

  Future<void> removeItem(int index) async {
    if (index < 0 || index >= state.items.length) return;
    final item = state.items[index];
    if (_operationInProgress || _syncInProgress) return;
    _operationInProgress = true;

    emit(state.copyWith(isMutating: true, errorMessage: null));

    try {
      if (!_isAuthenticated) {
        final lines = List<GuestCartLine>.from(await _guestStorage.cartLines());
        lines.removeWhere(
            (line) => line.productVariantId == item.productVariantId);
        await _guestStorage.saveCartLines(lines);
        await _loadGuestCart();
        return;
      }
      final itemId = item.id;
      if (itemId == null) {
        emit(state.copyWith(
          isMutating: false,
          errorMessage: 'تعذر حذف العنصر قبل اكتمال مزامنة السلة.',
        ));
        return;
      }
      final updatedCart = await _repository.removeItem(itemId);
      _emitLoadedCart(cart: updatedCart, items: _mapItems(updatedCart));
    } catch (e) {
      if (e is ApiFailure && e.kind == ApiFailureKind.unauthenticated) {
        _isAuthenticated = false;
        await _loadGuestCart();
        return;
      }
      emit(state.copyWith(
        isMutating: false,
        errorMessage: e is ApiFailure ? e.message : 'فشل حذف العنصر من السلة.',
      ));
    } finally {
      _operationInProgress = false;
    }
  }

  Future<bool> applyPromoCode(String code) async {
    if (!_isAuthenticated) {
      emit(state.copyWith(
        couponError: 'سجّل الدخول لتطبيق الكوبون عند إتمام الطلب.',
      ));
      return false;
    }
    final cleanCode = code.trim();
    if (cleanCode.isEmpty) {
      emit(state.copyWith(couponError: 'يرجى إدخال رمز الكوبون.'));
      return false;
    }

    emit(state.copyWith(isApplyingCoupon: true, couponError: null));

    try {
      final cart = await _repository.applyCoupon(cleanCode);
      _emitLoadedCart(
        cart: cart,
        items: _mapItems(cart),
        isApplyingCoupon: false,
      );
      return true;
    } catch (e) {
      final errorMsg = e is ApiFailure ? e.message : 'رمز الكوبون غير صالح.';
      emit(state.copyWith(
        isApplyingCoupon: false,
        couponError: errorMsg,
      ));
      return false;
    }
  }

  Future<void> removeCoupon() async {
    if (!_isAuthenticated) return;
    emit(state.copyWith(isApplyingCoupon: true, couponError: null));
    try {
      final cart = await _repository.removeCoupon();
      _emitLoadedCart(
        cart: cart,
        items: _mapItems(cart),
        isApplyingCoupon: false,
      );
    } catch (e) {
      emit(state.copyWith(
        isApplyingCoupon: false,
        couponError: e is ApiFailure ? e.message : 'فشل إزالة الكوبون.',
      ));
    }
  }

  Future<void> clearCart() async {
    emit(state.copyWith(isMutating: true));
    try {
      if (!_isAuthenticated) {
        await _guestStorage.saveCartLines(const []);
        await _loadGuestCart();
        return;
      }
      final emptyCart = await _repository.clearCart();
      emit(state.copyWith(
        status: CartStatus.loaded,
        cart: emptyCart,
        items: [],
        selectedKeys: {},
        isMutating: false,
        couponError: null,
        errorMessage: null,
      ));
    } catch (e) {
      if (e is ApiFailure && e.kind == ApiFailureKind.unauthenticated) {
        _isAuthenticated = false;
        await _guestStorage.saveCartLines(const []);
        await _loadGuestCart();
        return;
      }
      emit(state.copyWith(
        isMutating: false,
        errorMessage: e is ApiFailure ? e.message : 'فشل تفريغ السلة.',
      ));
    }
  }

  void toggleGiftWrapping(bool value) {
    emit(state.copyWith(giftWrapping: value));
  }

  void resetSession() {
    _isAuthenticated = false;
    _syncInProgress = false;
    _operationInProgress = false;
    emit(const CartState(
      status: CartStatus.initial,
      cart: null,
      items: [],
      isMutating: false,
      isApplyingCoupon: false,
      couponError: null,
      errorMessage: null,
    ));
  }

  Future<void> clearGuestData() => _guestStorage.saveCartLines(const []);
}
