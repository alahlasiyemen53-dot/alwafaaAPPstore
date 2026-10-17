import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_failure.dart';
import '../../../data/models/catalog/collection_model.dart';
import '../../../data/models/catalog/product_list_item_model.dart';
import '../../../data/models/product_model.dart';
import '../../../data/repositories/collections_repository.dart';

enum CollectionsStatus { initial, loading, loaded, error }

class CollectionsState extends Equatable {
  final CollectionsStatus status;
  final List<CollectionSummaryModel> collections;
  final CollectionSummaryModel? selectedCollection;
  final List<ProductListItemModel> products;
  final bool isLoadingMore;
  final bool hasMore;
  final int currentPage;
  final String? errorMessage;

  const CollectionsState({
    this.status = CollectionsStatus.initial,
    this.collections = const [],
    this.selectedCollection,
    this.products = const [],
    this.isLoadingMore = false,
    this.hasMore = false,
    this.currentPage = 1,
    this.errorMessage,
  });

  List<ProductModel> get productModels =>
      products.map(ProductModel.fromApiListItem).toList();

  CollectionsState copyWith({
    CollectionsStatus? status,
    List<CollectionSummaryModel>? collections,
    CollectionSummaryModel? selectedCollection,
    bool clearSelected = false,
    List<ProductListItemModel>? products,
    bool? isLoadingMore,
    bool? hasMore,
    int? currentPage,
    String? errorMessage,
    bool clearError = false,
  }) {
    return CollectionsState(
      status: status ?? this.status,
      collections: collections ?? this.collections,
      selectedCollection: clearSelected
          ? null
          : (selectedCollection ?? this.selectedCollection),
      products: products ?? this.products,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      hasMore: hasMore ?? this.hasMore,
      currentPage: currentPage ?? this.currentPage,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [
        status,
        collections,
        selectedCollection,
        products,
        isLoadingMore,
        hasMore,
        currentPage,
        errorMessage,
      ];
}

class CollectionsCubit extends Cubit<CollectionsState> {
  final CollectionsRepository _repository;

  CollectionsCubit({CollectionsRepository? repository})
      : _repository = repository ?? CollectionsRepositoryImpl(),
        super(const CollectionsState());

  Future<void> loadCollections() async {
    emit(state.copyWith(status: CollectionsStatus.loading, clearError: true));
    try {
      final response = await _repository.getCollections();
      emit(state.copyWith(
        status: CollectionsStatus.loaded,
        collections: response.items,
        clearError: true,
      ));
    } catch (e) {
      emit(state.copyWith(
        status: CollectionsStatus.error,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل مجموعات المنتجات.',
      ));
    }
  }

  Future<void> loadCollectionDetail(dynamic idOrSlug, {bool refresh = true}) async {
    emit(state.copyWith(
      status: CollectionsStatus.loading,
      clearError: true,
      products: refresh ? const [] : null,
    ));
    try {
      final detail = await _repository.getCollectionDetail(idOrSlug, page: 1);
      emit(state.copyWith(
        status: CollectionsStatus.loaded,
        selectedCollection: detail.collection,
        products: detail.items,
        currentPage: detail.currentPage,
        hasMore: detail.hasMore,
        clearError: true,
      ));
    } catch (e) {
      emit(state.copyWith(
        status: CollectionsStatus.error,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل تفاصيل المجموعة.',
      ));
    }
  }

  Future<void> loadMoreProducts() async {
    final collection = state.selectedCollection;
    if (collection == null || state.isLoadingMore || !state.hasMore) return;

    emit(state.copyWith(isLoadingMore: true));
    try {
      final nextPage = state.currentPage + 1;
      final detail = await _repository.getCollectionDetail(
        collection.id,
        page: nextPage,
      );
      final existingIds = state.products.map((e) => e.id).toSet();
      final newItems =
          detail.items.where((e) => !existingIds.contains(e.id)).toList();
      emit(state.copyWith(
        isLoadingMore: false,
        products: [...state.products, ...newItems],
        currentPage: detail.currentPage,
        hasMore: detail.hasMore,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoadingMore: false,
        errorMessage:
            e is ApiFailure ? e.message : 'تعذر تحميل المزيد من المنتجات.',
      ));
    }
  }
}
