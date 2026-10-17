import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../data/models/catalog/brand_api_model.dart';
import '../../../data/repositories/catalog_repository.dart';

enum BrandsStatus { initial, loading, success, empty, error }

class BrandsState extends Equatable {
  final BrandsStatus status;
  final List<BrandApiModel> brands;
  final String? errorMessage;

  const BrandsState({
    this.status = BrandsStatus.initial,
    this.brands = const [],
    this.errorMessage,
  });

  BrandsState copyWith({
    BrandsStatus? status,
    List<BrandApiModel>? brands,
    String? errorMessage,
    bool clearError = false,
  }) {
    return BrandsState(
      status: status ?? this.status,
      brands: brands ?? this.brands,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, brands, errorMessage];
}

class BrandsCubit extends Cubit<BrandsState> {
  final CatalogRepository _repository;
  bool _hasLoaded = false;

  BrandsCubit({CatalogRepository? repository})
      : _repository = repository ?? CatalogRepositoryImpl(),
        super(const BrandsState());

  @override
  void emit(BrandsState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<void> loadBrands({bool force = false}) async {
    if (_hasLoaded && !force) return;
    _hasLoaded = true;

    emit(state.copyWith(status: BrandsStatus.loading, clearError: true));
    try {
      final brands = await _repository.getBrands();
      emit(state.copyWith(
        status: brands.isEmpty ? BrandsStatus.empty : BrandsStatus.success,
        brands: brands,
        clearError: true,
      ));
    } catch (e) {
      _hasLoaded = false;
      emit(state.copyWith(
        status: BrandsStatus.error,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> retry() => loadBrands(force: true);
}
