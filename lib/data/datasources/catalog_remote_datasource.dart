import '../../core/network/api_endpoints.dart';
import '../../core/network/api_failure.dart';
import '../../core/network/dio_client.dart';
import '../../core/network/network_exceptions.dart';
import '../models/catalog/brand_api_model.dart';
import '../models/catalog/category_api_model.dart';
import '../models/catalog/product_detail_api_model.dart';
import '../models/catalog/product_list_item_model.dart';

abstract class CatalogRemoteDataSource {
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

class CatalogRemoteDataSourceImpl implements CatalogRemoteDataSource {
  final DioClient _dioClient;

  CatalogRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<List<CategoryApiModel>> getCategories() async {
    try {
      final response = await _dioClient.get(ApiEndpoints.categories);
      final rawData = response.data;

      if (rawData is Map<String, dynamic> && rawData['data'] is List) {
        final list = rawData['data'] as List;
        return list
            .whereType<Map<String, dynamic>>()
            .map((item) => CategoryApiModel.fromJson(item))
            .toList();
      }

      return [];
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<List<BrandApiModel>> getBrands() async {
    try {
      final response = await _dioClient.get(ApiEndpoints.brands);
      final rawData = response.data;

      if (rawData is Map<String, dynamic> && rawData['data'] is List) {
        final list = rawData['data'] as List;
        return list
            .whereType<Map<String, dynamic>>()
            .map((item) => BrandApiModel.fromJson(item))
            .toList();
      }

      return [];
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<CategoryApiModel> getCategoryDetail(dynamic idOrSlug) async {
    try {
      final response =
          await _dioClient.get(ApiEndpoints.categoryDetails(idOrSlug));
      final rawData = response.data;

      if (rawData is Map<String, dynamic> &&
          rawData['data'] is Map<String, dynamic>) {
        return CategoryApiModel.fromJson(
            rawData['data'] as Map<String, dynamic>);
      }

      throw NetworkException('Invalid category response format');
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
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
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'per_page': perPage,
      };

      final resolvedCategoryIds =
          categoryIds?.where((id) => id > 0).toList(growable: false);
      if (resolvedCategoryIds != null && resolvedCategoryIds.isNotEmpty) {
        queryParams['category_id'] = resolvedCategoryIds;
      }
      final resolvedBrandIds =
          brandIds?.where((id) => id > 0).toList(growable: false);
      if (resolvedBrandIds != null && resolvedBrandIds.isNotEmpty) {
        queryParams['brand_id'] = resolvedBrandIds;
      }
      if (badge != null && badge.isNotEmpty) {
        queryParams['badge'] = badge;
      }
      if (availability != null && availability.isNotEmpty) {
        queryParams['availability'] = availability;
      }
      if (sort != null && sort.isNotEmpty) {
        queryParams['sort'] = sort;
      }

      final response = await _dioClient.get(
        ApiEndpoints.products,
        queryParameters: queryParams,
      );

      final rawData = response.data;
      if (rawData is Map<String, dynamic> &&
          rawData['data'] is Map<String, dynamic>) {
        return PaginatedProductsResponse.fromJson(
            rawData['data'] as Map<String, dynamic>);
      }

      throw NetworkException('Invalid product list response format');
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
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
    try {
      final queryParams = <String, dynamic>{
        'q': query,
        'page': page,
        'per_page': perPage,
      };

      final resolvedCategoryIds =
          categoryIds?.where((id) => id > 0).toList(growable: false);
      if (resolvedCategoryIds != null && resolvedCategoryIds.isNotEmpty) {
        queryParams['category_id'] = resolvedCategoryIds;
      }
      final resolvedBrandIds =
          brandIds?.where((id) => id > 0).toList(growable: false);
      if (resolvedBrandIds != null && resolvedBrandIds.isNotEmpty) {
        queryParams['brand_id'] = resolvedBrandIds;
      }
      if (availability != null && availability.isNotEmpty) {
        queryParams['availability'] = availability;
      }
      if (sort != null && sort.isNotEmpty) {
        queryParams['sort'] = sort;
      }

      final response = await _dioClient.get(
        ApiEndpoints.productSearch,
        queryParameters: queryParams,
      );

      final rawData = response.data;
      if (rawData is Map<String, dynamic> &&
          rawData['data'] is Map<String, dynamic>) {
        return PaginatedProductsResponse.fromJson(
            rawData['data'] as Map<String, dynamic>);
      }

      throw NetworkException('Invalid search response format');
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<ProductDetailApiModel> getProductDetail(dynamic idOrSlug) async {
    try {
      final response =
          await _dioClient.get(ApiEndpoints.productDetails(idOrSlug));
      final rawData = response.data;

      if (rawData is Map<String, dynamic> &&
          rawData['data'] is Map<String, dynamic>) {
        return ProductDetailApiModel.fromJson(
            rawData['data'] as Map<String, dynamic>);
      }

      throw NetworkException('Invalid product detail response format');
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<List<ProductListItemModel>> getRelatedProducts(dynamic idOrSlug,
      {int limit = 8}) async {
    try {
      final response = await _dioClient.get(
        ApiEndpoints.relatedProducts(idOrSlug, limit),
      );
      final rawData = response.data;

      if (rawData is Map<String, dynamic> &&
          rawData['data'] is Map<String, dynamic> &&
          rawData['data']['items'] is List) {
        final list = rawData['data']['items'] as List;
        return list
            .whereType<Map<String, dynamic>>()
            .map((item) => ProductListItemModel.fromJson(item))
            .toList();
      }

      return [];
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }
}

