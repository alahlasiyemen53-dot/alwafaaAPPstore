import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/repositories/catalog_repository.dart';
import 'related_products_state.dart';

class RelatedProductsCubit extends Cubit<RelatedProductsState> {
  final CatalogRepository _catalogRepository;
  int _currentRequestId = 0;
  dynamic _lastLoadedId;

  RelatedProductsCubit({CatalogRepository? catalogRepository})
      : _catalogRepository = catalogRepository ?? CatalogRepositoryImpl(),
        super(const RelatedProductsState.initial());

  @override
  void emit(RelatedProductsState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<void> loadRelatedProducts(dynamic idOrSlug, {bool force = false}) async {
    if (idOrSlug == null || isClosed) return;

    if (!force &&
        _lastLoadedId == idOrSlug &&
        (state.isSuccess || state.isEmpty)) {
      return;
    }

    final requestId = ++_currentRequestId;
    _lastLoadedId = idOrSlug;

    emit(const RelatedProductsState.loading());

    try {
      final items = await _catalogRepository.getRelatedProducts(idOrSlug);

      // Stale response / race-condition protection:
      // If a newer request was dispatched while this request was pending, discard it.
      if (requestId != _currentRequestId || isClosed) {
        return;
      }

      if (items.isEmpty) {
        emit(const RelatedProductsState.empty());
      } else {
        final products =
            items.map((item) => ProductModel.fromApiListItem(item)).toList();
        emit(RelatedProductsState.success(products));
      }
    } catch (e) {
      if (requestId != _currentRequestId || isClosed) {
        return;
      }
      emit(RelatedProductsState.error(e.toString()));
    }
  }

  void reset() {
    _currentRequestId++;
    _lastLoadedId = null;
    emit(const RelatedProductsState.initial());
  }
}
