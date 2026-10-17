import 'package:equatable/equatable.dart';
import '../../../../data/models/product_model.dart';

enum RelatedProductsStatus { initial, loading, success, empty, error }

class RelatedProductsState extends Equatable {
  final RelatedProductsStatus status;
  final List<ProductModel> products;
  final String? errorMessage;

  const RelatedProductsState({
    this.status = RelatedProductsStatus.initial,
    this.products = const [],
    this.errorMessage,
  });

  const RelatedProductsState.initial()
      : this(status: RelatedProductsStatus.initial);

  const RelatedProductsState.loading()
      : this(status: RelatedProductsStatus.loading);

  const RelatedProductsState.success(List<ProductModel> products)
      : this(status: RelatedProductsStatus.success, products: products);

  const RelatedProductsState.empty()
      : this(status: RelatedProductsStatus.empty);

  const RelatedProductsState.error(String message)
      : this(status: RelatedProductsStatus.error, errorMessage: message);

  bool get isInitial => status == RelatedProductsStatus.initial;
  bool get isLoading => status == RelatedProductsStatus.loading;
  bool get isSuccess => status == RelatedProductsStatus.success;
  bool get isEmpty => status == RelatedProductsStatus.empty;
  bool get isError => status == RelatedProductsStatus.error;

  @override
  List<Object?> get props => [status, products, errorMessage];
}
