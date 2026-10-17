import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/data/repositories/catalog_repository.dart';

enum CategoriesStatus { initial, loading, success, empty, error }

class CategoriesState extends Equatable {
  final CategoriesStatus status;
  final List<CategoryModel> categories;
  final String? errorMessage;

  const CategoriesState({
    this.status = CategoriesStatus.initial,
    this.categories = const [],
    this.errorMessage,
  });

  CategoriesState copyWith({
    CategoriesStatus? status,
    List<CategoryModel>? categories,
    String? errorMessage,
    bool clearError = false,
  }) {
    return CategoriesState(
      status: status ?? this.status,
      categories: categories ?? this.categories,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, categories, errorMessage];
}

class CategoriesCubit extends Cubit<CategoriesState> {
  final CatalogRepository _repository;
  bool _hasLoaded = false;

  CategoriesCubit({CatalogRepository? repository})
      : _repository = repository ?? CatalogRepositoryImpl(),
        super(const CategoriesState());

  @override
  void emit(CategoriesState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<void> loadCategories({bool force = false}) async {
    if (_hasLoaded && !force) return;
    _hasLoaded = true;

    emit(state.copyWith(status: CategoriesStatus.loading, clearError: true));
    try {
      final apiCategories = await _repository.getCategories();
      // List screen shows root categories only; children appear in catalog filters.
      final categories = apiCategories
          .map(CategoryModel.fromApiModel)
          .toList();

      emit(state.copyWith(
        status:
            categories.isEmpty ? CategoriesStatus.empty : CategoriesStatus.success,
        categories: categories,
        clearError: true,
      ));
    } catch (e) {
      _hasLoaded = false;
      emit(state.copyWith(
        status: CategoriesStatus.error,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> retry() => loadCategories(force: true);
}
