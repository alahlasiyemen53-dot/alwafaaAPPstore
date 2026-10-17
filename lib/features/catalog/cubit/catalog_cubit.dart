import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/utils/arabic_text_normalizer.dart';
import '../../../data/models/catalog/product_list_item_model.dart';
import '../../../data/models/category_model.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/catalog_repository.dart';

enum CatalogSortOption { featured, priceAsc, priceDesc, rating, newest }

enum CardVariation { classic, editorial, compact }

class CatalogState extends Equatable {
  final List<ProductModel> allProducts;
  final List<CategoryModel> categories;
  final ProductCategory selectedCategory;
  final List<int> selectedServerCategoryIds;
  final Map<int, String> selectedCategoryNames;
  final List<int> selectedBrandIds;
  final Map<int, String> selectedBrandNames;

  /// First selected server category (Home / legacy single-select helpers).
  int? get selectedServerCategoryId =>
      selectedServerCategoryIds.isEmpty ? null : selectedServerCategoryIds.first;
  final String searchQuery;
  final bool inStockOnly;
  final bool onlyOffers;
  final String? selectedBadge;
  final CatalogSortOption sortBy;
  final bool isGridView;
  final CardVariation cardVariation;
  final bool isLoading;
  final bool isLoadingMore;
  final String? errorMessage;
  final int currentPage;
  final bool hasMore;
  final int totalCount;
  final bool isUsingApi;
  final bool isSearching;

  const CatalogState({
    required this.allProducts,
    required this.categories,
    this.selectedCategory = ProductCategory.all,
    this.selectedServerCategoryIds = const [],
    this.selectedCategoryNames = const {},
    this.selectedBrandIds = const [],
    this.selectedBrandNames = const {},
    this.searchQuery = '',
    this.inStockOnly = false,
    this.onlyOffers = false,
    this.selectedBadge,
    this.sortBy = CatalogSortOption.featured,
    this.isGridView = true,
    this.cardVariation = CardVariation.classic,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.errorMessage,
    this.currentPage = 1,
    this.hasMore = false,
    this.totalCount = 0,
    this.isUsingApi = false,
    this.isSearching = false,
  });

  /// Visible product count for the catalog header.
  /// Prefers API pagination total; falls back to loaded/filtered length when
  /// a client-only filter (e.g. offers) changes the server total meaning.
  int get displayedProductCount {
    if (!isUsingApi || onlyOffers) {
      return filteredProducts.length;
    }
    if (totalCount > 0) return totalCount;
    return filteredProducts.length;
  }

  /// Category chips for filters: roots by default; children when a single
  /// parent branch is selected.
  List<CategoryModel> get categoryFilterChips {
    final keyed = categories.where((c) => c.serverId != null).toList();
    final roots = keyed.where((c) => c.parentId == null).toList();
    if (selectedServerCategoryIds.isEmpty) return roots;

    int? branchParentId;
    for (final selectedId in selectedServerCategoryIds) {
      CategoryModel? selected;
      for (final category in keyed) {
        if (category.serverId == selectedId) {
          selected = category;
          break;
        }
      }
      if (selected == null) continue;
      final parentId = selected.parentId ?? selected.serverId;
      if (branchParentId == null) {
        branchParentId = parentId;
      } else if (branchParentId != parentId) {
        return roots;
      }
    }
    if (branchParentId == null) return roots;

    final children =
        keyed.where((c) => c.parentId == branchParentId).toList();
    if (children.isEmpty) return roots;

    CategoryModel? parent;
    for (final category in keyed) {
      if (category.serverId == branchParentId) {
        parent = category;
        break;
      }
    }
    if (parent == null) return children;
    return [parent, ...children];
  }

  List<ProductModel> get filteredProducts {
    // When using live API data, server already handled categories, search, pagination, and sorting
    if (isUsingApi) {
      if (isSearching) {
        return allProducts;
      }
      if (searchQuery.trim().isEmpty && !inStockOnly && !onlyOffers) {
        return allProducts;
      }
      return allProducts.where((p) {
        if (searchQuery.trim().isNotEmpty) {
          final q = searchQuery.trim();
          final nameMatch =
              ArabicTextNormalizer.containsNormalized(p.name, q);
          final brandMatch =
              ArabicTextNormalizer.containsNormalized(p.brand, q);
          final descMatch =
              ArabicTextNormalizer.containsNormalized(p.description, q);
          if (!nameMatch && !brandMatch && !descMatch) return false;
        }
        if (inStockOnly && !p.inStock) return false;
        if (onlyOffers &&
            (p.discountPercentage == null || p.discountPercentage! <= 0)) {
          return false;
        }
        return true;
      }).toList();
    }

    return allProducts.where((p) {
      if (selectedCategory != ProductCategory.all &&
          p.category != selectedCategory) {
        return false;
      }
      if (selectedBrandIds.isNotEmpty) {
        final names = selectedBrandNames.values
            .map((n) => n.trim())
            .where((n) => n.isNotEmpty)
            .toList();
        if (names.isNotEmpty &&
            !names.any(
              (name) => ArabicTextNormalizer.containsNormalized(p.brand, name),
            )) {
          return false;
        }
      }
      if (searchQuery.trim().isNotEmpty) {
        final q = searchQuery.trim();
        final nameMatch = ArabicTextNormalizer.containsNormalized(p.name, q);
        final brandMatch = ArabicTextNormalizer.containsNormalized(p.brand, q);
        final descMatch =
            ArabicTextNormalizer.containsNormalized(p.description, q);
        final notesMatch = p.notes != null &&
            (ArabicTextNormalizer.containsNormalized(p.notes!.top ?? '', q) ||
                ArabicTextNormalizer.containsNormalized(
                    p.notes!.heart ?? '', q) ||
                ArabicTextNormalizer.containsNormalized(
                    p.notes!.base ?? '', q));
        if (!nameMatch && !brandMatch && !descMatch && !notesMatch) {
          return false;
        }
      }
      if (inStockOnly && !p.inStock) return false;
      if (onlyOffers &&
          (p.discountPercentage == null || p.discountPercentage! <= 0)) {
        return false;
      }
      return true;
    }).toList();
  }

  CatalogState copyWith({
    List<ProductModel>? allProducts,
    List<CategoryModel>? categories,
    ProductCategory? selectedCategory,
    List<int>? selectedServerCategoryIds,
    Map<int, String>? selectedCategoryNames,
    bool clearServerCategory = false,
    List<int>? selectedBrandIds,
    Map<int, String>? selectedBrandNames,
    bool clearBrand = false,
    String? searchQuery,
    bool? inStockOnly,
    bool? onlyOffers,
    String? selectedBadge,
    bool clearBadge = false,
    CatalogSortOption? sortBy,
    bool? isGridView,
    CardVariation? cardVariation,
    bool? isLoading,
    bool? isLoadingMore,
    String? errorMessage,
    bool clearError = false,
    int? currentPage,
    bool? hasMore,
    int? totalCount,
    bool? isUsingApi,
    bool? isSearching,
  }) {
    return CatalogState(
      allProducts: allProducts ?? this.allProducts,
      categories: categories ?? this.categories,
      selectedCategory: selectedCategory ?? this.selectedCategory,
      selectedServerCategoryIds: clearServerCategory
          ? const []
          : (selectedServerCategoryIds ?? this.selectedServerCategoryIds),
      selectedCategoryNames: clearServerCategory
          ? const {}
          : (selectedCategoryNames ?? this.selectedCategoryNames),
      selectedBrandIds:
          clearBrand ? const [] : (selectedBrandIds ?? this.selectedBrandIds),
      selectedBrandNames: clearBrand
          ? const {}
          : (selectedBrandNames ?? this.selectedBrandNames),
      searchQuery: searchQuery ?? this.searchQuery,
      inStockOnly: inStockOnly ?? this.inStockOnly,
      onlyOffers: onlyOffers ?? this.onlyOffers,
      selectedBadge:
          clearBadge ? null : (selectedBadge ?? this.selectedBadge),
      sortBy: sortBy ?? this.sortBy,
      isGridView: isGridView ?? this.isGridView,
      cardVariation: cardVariation ?? this.cardVariation,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
      totalCount: totalCount ?? this.totalCount,
      isUsingApi: isUsingApi ?? this.isUsingApi,
      isSearching: isSearching ?? this.isSearching,
    );
  }

  @override
  List<Object?> get props => [
        allProducts,
        categories,
        selectedCategory,
        selectedServerCategoryIds,
        selectedCategoryNames,
        selectedBrandIds,
        selectedBrandNames,
        searchQuery,
        inStockOnly,
        onlyOffers,
        selectedBadge,
        sortBy,
        isGridView,
        cardVariation,
        isLoading,
        isLoadingMore,
        errorMessage,
        currentPage,
        hasMore,
        totalCount,
        isUsingApi,
        isSearching,
      ];
}

class CatalogCubit extends Cubit<CatalogState> {
  final CatalogRepository _repository;
  int _loadSequence = 0;

  CatalogCubit({CatalogRepository? repository})
      : _repository = repository ?? CatalogRepositoryImpl(),
        super(
          const CatalogState(allProducts: [], categories: []),
        ) {
    loadInitialData();
  }

  String _mapSortOption(CatalogSortOption option) => sortApiValue(option);

  /// Exposed for tests / UI labels.
  static String sortApiValue(CatalogSortOption option) {
    switch (option) {
      case CatalogSortOption.priceAsc:
        return 'price_asc';
      case CatalogSortOption.priceDesc:
        return 'price_desc';
      case CatalogSortOption.rating:
        return 'rating';
      case CatalogSortOption.newest:
        return 'newest';
      case CatalogSortOption.featured:
        return 'featured';
    }
  }

  Future<void> loadInitialData() async {
    emit(state.copyWith(isLoading: true, clearError: true));
    try {
      // 1. Fetch categories (no synthetic "الكل" — all products show until a category is chosen)
      final apiCategories = await _repository.getCategories();
      final List<CategoryModel> categories = [];

      for (final cat in apiCategories) {
        categories.add(CategoryModel.fromApiModel(cat));
        for (final child in cat.children) {
          categories.add(CategoryModel.fromApiModel(child));
        }
      }

      // 2. Fetch first page of products
      final response = await _repository.getProducts(
        page: 1,
        sort: _mapSortOption(state.sortBy),
      );

      final products =
          response.items.map((i) => ProductModel.fromApiListItem(i)).toList();

      emit(state.copyWith(
        categories: categories,
        allProducts: products,
        currentPage: response.currentPage,
        hasMore: response.hasMore,
        totalCount: response.total,
        isLoading: false,
        isLoadingMore: false,
        isUsingApi: true,
        isSearching: false,
        clearError: true,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoading: false,
        isLoadingMore: false,
        errorMessage: e.toString(),
        isUsingApi: false,
        isSearching: false,
      ));
    }
  }

  Future<void> loadProducts({bool refresh = false}) async {
    final page = refresh ? 1 : state.currentPage;
    final seq = ++_loadSequence;
    emit(state.copyWith(
      isLoading: true,
      isLoadingMore: false,
      isSearching: false,
      clearError: true,
      allProducts: refresh ? const [] : null,
      totalCount: refresh ? 0 : null,
      currentPage: refresh ? 1 : null,
      hasMore: refresh ? true : null,
    ));

    try {
      String? availability;
      if (state.inStockOnly) {
        availability = 'in_stock';
      }

      final response = await _repository.getProducts(
        page: page,
        categoryIds: state.selectedServerCategoryIds.isEmpty
            ? null
            : state.selectedServerCategoryIds,
        brandIds: state.selectedBrandIds.isEmpty
            ? null
            : state.selectedBrandIds,
        badge: state.selectedBadge,
        availability: availability,
        sort: _mapSortOption(state.sortBy),
      );

      if (seq != _loadSequence || isClosed) return;

      final products =
          response.items.map((i) => ProductModel.fromApiListItem(i)).toList();

      emit(state.copyWith(
        allProducts: products,
        currentPage: response.currentPage,
        hasMore: response.hasMore,
        totalCount: response.total,
        isLoading: false,
        isLoadingMore: false,
        isUsingApi: true,
        isSearching: false,
        clearError: true,
      ));
    } catch (e) {
      if (seq != _loadSequence || isClosed) return;
      emit(state.copyWith(
        isLoading: false,
        isLoadingMore: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> searchProducts(String query, {bool refresh = false}) async {
    final trimmed = query.trim();
    if (trimmed.isEmpty) {
      emit(state.copyWith(searchQuery: '', isSearching: false));
      loadProducts(refresh: true);
      return;
    }

    final page = refresh ? 1 : state.currentPage;
    final seq = ++_loadSequence;
    emit(state.copyWith(
      searchQuery: query,
      isSearching: true,
      isLoading: true,
      isLoadingMore: false,
      clearError: true,
      allProducts: refresh ? const [] : null,
      totalCount: refresh ? 0 : null,
      currentPage: refresh ? 1 : null,
      hasMore: refresh ? true : null,
    ));

    try {
      String? availability;
      if (state.inStockOnly) {
        availability = 'in_stock';
      }

      final response = await _repository.searchProducts(
        query: trimmed,
        page: page,
        categoryIds: state.selectedServerCategoryIds.isEmpty
            ? null
            : state.selectedServerCategoryIds,
        brandIds: state.selectedBrandIds.isEmpty
            ? null
            : state.selectedBrandIds,
        availability: availability,
        sort: _mapSortOption(state.sortBy),
      );

      if (seq != _loadSequence || isClosed) return;

      final products =
          response.items.map((i) => ProductModel.fromApiListItem(i)).toList();

      emit(state.copyWith(
        allProducts: products,
        currentPage: response.currentPage,
        hasMore: response.hasMore,
        totalCount: response.total,
        isLoading: false,
        isLoadingMore: false,
        isUsingApi: true,
        isSearching: true,
        clearError: true,
      ));
    } catch (e) {
      if (seq != _loadSequence || isClosed) return;
      emit(state.copyWith(
        isLoading: false,
        isLoadingMore: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> loadMoreProducts() async {
    if (state.isLoading || state.isLoadingMore || !state.hasMore) return;

    final seq = _loadSequence;
    final nextPage = state.currentPage + 1;
    emit(state.copyWith(isLoadingMore: true, clearError: true));
    try {
      String? availability;
      if (state.inStockOnly) {
        availability = 'in_stock';
      }

      final PaginatedProductsResponse response;

      final brandIds =
          state.selectedBrandIds.isEmpty ? null : state.selectedBrandIds;
      final categoryIds = state.selectedServerCategoryIds.isEmpty
          ? null
          : state.selectedServerCategoryIds;

      if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
        response = await _repository.searchProducts(
          query: state.searchQuery.trim(),
          page: nextPage,
          categoryIds: categoryIds,
          brandIds: brandIds,
          availability: availability,
          sort: _mapSortOption(state.sortBy),
        );
      } else {
        response = await _repository.getProducts(
          page: nextPage,
          categoryIds: categoryIds,
          brandIds: brandIds,
          badge: state.selectedBadge,
          availability: availability,
          sort: _mapSortOption(state.sortBy),
        );
      }

      if (seq != _loadSequence || isClosed) {
        if (!isClosed) {
          emit(state.copyWith(isLoadingMore: false));
        }
        return;
      }

      final newProducts =
          response.items.map((i) => ProductModel.fromApiListItem(i)).toList();
      final existingIds = state.allProducts.map((p) => p.id).toSet();
      final uniqueNew = newProducts.where((p) => !existingIds.contains(p.id));
      final updatedList = List<ProductModel>.from(state.allProducts)
        ..addAll(uniqueNew);

      emit(state.copyWith(
        allProducts: updatedList,
        currentPage: response.currentPage,
        hasMore: response.hasMore,
        totalCount: response.total,
        isLoadingMore: false,
        clearError: true,
      ));
    } catch (e) {
      if (isClosed) return;
      emit(state.copyWith(
        isLoadingMore: false,
        errorMessage: e.toString(),
      ));
    }
  }

  /// Sets a single category from Home/Categories navigation (or clears).
  void selectCategory(ProductCategory category, {int? serverId}) {
    final isAlreadySelected = serverId != null
        ? (state.selectedServerCategoryIds.length == 1 &&
            state.selectedServerCategoryIds.first == serverId)
        : (state.selectedCategory == category &&
            state.selectedServerCategoryIds.isEmpty &&
            category != ProductCategory.all);

    final shouldClear =
        isAlreadySelected || (category == ProductCategory.all && serverId == null);

    if (shouldClear) {
      emit(state.copyWith(
        selectedCategory: ProductCategory.all,
        clearServerCategory: true,
      ));
    } else if (serverId != null && serverId > 0) {
      var name = '';
      for (final cat in state.categories) {
        if (cat.serverId == serverId) {
          name = cat.nameAr;
          break;
        }
      }
      emit(state.copyWith(
        selectedCategory: category,
        selectedServerCategoryIds: [serverId],
        selectedCategoryNames: {serverId: name},
      ));
    } else {
      emit(state.copyWith(
        selectedCategory: category,
        clearServerCategory: true,
      ));
    }

    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  /// Apply multi-category selection (empty map clears).
  void setSelectedCategories(Map<int, String> categories) {
    final cleaned = <int, String>{};
    for (final entry in categories.entries) {
      if (entry.key > 0) {
        cleaned[entry.key] = entry.value.trim();
      }
    }
    final ids = cleaned.keys.toList(growable: false);
    ProductCategory legacy = ProductCategory.all;
    if (ids.length == 1) {
      for (final cat in state.categories) {
        if (cat.serverId == ids.first) {
          legacy = cat.id;
          break;
        }
      }
    }
    emit(state.copyWith(
      selectedCategory: legacy,
      selectedServerCategoryIds: ids,
      selectedCategoryNames: Map<int, String>.unmodifiable(cleaned),
      clearServerCategory: ids.isEmpty,
    ));
    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  void removeCategory(int serverId) {
    if (!state.selectedServerCategoryIds.contains(serverId)) return;
    final next = Map<int, String>.from(state.selectedCategoryNames)
      ..remove(serverId);
    setSelectedCategories(next);
  }

  /// Sets brands from Home/Brands navigation (single) or clear.
  void selectBrand(int? brandId, {String? brandName}) {
    if (brandId == null) {
      setSelectedBrands(const {});
      return;
    }
    final name = brandName?.trim() ?? '';
    setSelectedBrands({brandId: name});
  }

  /// Apply multi-brand selection (empty map clears).
  void setSelectedBrands(Map<int, String> brands) {
    final cleaned = <int, String>{};
    for (final entry in brands.entries) {
      if (entry.key > 0) {
        cleaned[entry.key] = entry.value.trim();
      }
    }
    final ids = cleaned.keys.toList(growable: false);
    emit(state.copyWith(
      selectedBrandIds: ids,
      selectedBrandNames: Map<int, String>.unmodifiable(cleaned),
      clearBrand: ids.isEmpty,
    ));
    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  void removeBrand(int brandId) {
    if (!state.selectedBrandIds.contains(brandId)) return;
    final next = Map<int, String>.from(state.selectedBrandNames)
      ..remove(brandId);
    setSelectedBrands(next);
  }

  void updateSearchQuery(String query) {
    emit(state.copyWith(searchQuery: query));
    if (query.trim().isEmpty && state.isSearching) {
      searchProducts('', refresh: true);
    }
  }

  void toggleInStockOnly(bool value) {
    emit(state.copyWith(inStockOnly: value));
    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  void toggleOnlyOffers(bool value) {
    emit(state.copyWith(onlyOffers: value));
  }

  void setBadge(String? badge) {
    final normalized = badge?.trim();
    emit(state.copyWith(
      selectedBadge: (normalized == null || normalized.isEmpty) ? null : normalized,
      clearBadge: normalized == null || normalized.isEmpty,
    ));
    // Badge is honored by GET /products; while searching, keep selection but
    // reload list path only when not searching (search API ignores badge).
    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  void setSortBy(CatalogSortOption option) {
    emit(state.copyWith(sortBy: option));
    if (state.isSearching && state.searchQuery.trim().isNotEmpty) {
      searchProducts(state.searchQuery, refresh: true);
    } else {
      loadProducts(refresh: true);
    }
  }

  void toggleGridView() {
    emit(state.copyWith(isGridView: !state.isGridView));
  }

  void setCardVariation(CardVariation variation) {
    emit(state.copyWith(cardVariation: variation));
  }

  /// Clears catalog filters. When [reload] is false (e.g. leaving for home),
  /// only resets selection state so a later category tap is not raced by an
  /// "all products" request.
  void resetFilters({bool reload = true}) {
    emit(state.copyWith(
      selectedCategory: ProductCategory.all,
      clearServerCategory: true,
      clearBrand: true,
      clearBadge: true,
      searchQuery: '',
      inStockOnly: false,
      onlyOffers: false,
      isSearching: false,
      sortBy: CatalogSortOption.featured,
      allProducts: reload ? null : const [],
      totalCount: reload ? null : 0,
      isLoading: reload,
    ));
    if (reload) {
      loadProducts(refresh: true);
    } else {
      // Invalidate any in-flight "all products" load from a prior filter clear.
      _loadSequence++;
    }
  }
}
