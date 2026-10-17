import '../datasources/catalog_remote_datasource.dart';
import '../models/catalog/brand_api_model.dart';
import '../models/catalog/category_api_model.dart';
import '../models/catalog/product_detail_api_model.dart';
import '../models/catalog/product_list_item_model.dart';

abstract class CatalogRepository {
  Future<List<CategoryApiModel>> getCategories();
  Future<CategoryApiModel> getCategoryDetail(dynamic idOrSlug);
  Future<List<BrandApiModel>> getBrands();
  Future<PaginatedProductsResponse> getProducts({
    int page = 1,
    int perPage = 15,
    List<int>? categoryIds,
    List<int>? brandIds,
    String? badge,
    String? availability,
    String? sort,
  });
  Future<PaginatedProductsResponse> searchProducts({
    required String query,
    int page = 1,
    int perPage = 15,
    List<int>? categoryIds,
    List<int>? brandIds,
    String? availability,
    String? sort,
  });
  Future<ProductDetailApiModel> getProductDetail(dynamic idOrSlug);
  Future<List<ProductListItemModel>> getRelatedProducts(dynamic idOrSlug,
      {int limit = 8});
}

class CatalogRepositoryImpl implements CatalogRepository {
  final CatalogRemoteDataSource _remoteDataSource;

  CatalogRepositoryImpl({CatalogRemoteDataSource? remoteDataSource})
      : _remoteDataSource = remoteDataSource ?? CatalogRemoteDataSourceImpl();

  @override
  Future<List<CategoryApiModel>> getCategories() async {
    return await _remoteDataSource.getCategories();
  }

  @override
  Future<CategoryApiModel> getCategoryDetail(dynamic idOrSlug) async {
    return await _remoteDataSource.getCategoryDetail(idOrSlug);
  }

  @override
  Future<List<BrandApiModel>> getBrands() async {
    return await _remoteDataSource.getBrands();
  }

  @override
  Future<PaginatedProductsResponse> getProducts({
    int page = 1,
    int perPage = 15,
    List<int>? categoryIds,
    List<int>? brandIds,
    String? badge,
    String? availability,
    String? sort,
  }) async {
    return await _remoteDataSource.getProducts(
      page: page,
      perPage: perPage,
      categoryIds: categoryIds,
      brandIds: brandIds,
      badge: badge,
      availability: availability,
      sort: sort,
    );
  }

  @override
  Future<PaginatedProductsResponse> searchProducts({
    required String query,
    int page = 1,
    int perPage = 15,
    List<int>? categoryIds,
    List<int>? brandIds,
    String? availability,
    String? sort,
  }) async {
    return await _remoteDataSource.searchProducts(
      query: query,
      page: page,
      perPage: perPage,
      categoryIds: categoryIds,
      brandIds: brandIds,
      availability: availability,
      sort: sort,
    );
  }

  @override
  Future<ProductDetailApiModel> getProductDetail(dynamic idOrSlug) async {
    return await _remoteDataSource.getProductDetail(idOrSlug);
  }

  @override
  Future<List<ProductListItemModel>> getRelatedProducts(dynamic idOrSlug,
      {int limit = 8}) async {
    return await _remoteDataSource.getRelatedProducts(idOrSlug, limit: limit);
  }
}

