import '../../core/network/api_endpoints.dart';
import '../../core/network/api_failure.dart';
import '../../core/network/dio_client.dart';
import '../../core/network/network_exceptions.dart';
import '../models/catalog/product_review_models.dart';

abstract class ReviewsRemoteDataSource {
  Future<PaginatedReviewsResponse> getProductReviews(
    dynamic productIdOrSlug, {
    int page = 1,
    int perPage = 15,
  });

  Future<PaginatedCommentsResponse> getReviewComments(
    int reviewId, {
    int page = 1,
    int perPage = 15,
  });

  Future<ReviewCommentModel> createReviewComment({
    required int reviewId,
    required String body,
  });
}

class ReviewsRemoteDataSourceImpl implements ReviewsRemoteDataSource {
  final DioClient _dioClient;

  ReviewsRemoteDataSourceImpl({DioClient? dioClient})
      : _dioClient = dioClient ?? DioClient();

  @override
  Future<PaginatedReviewsResponse> getProductReviews(
    dynamic productIdOrSlug, {
    int page = 1,
    int perPage = 15,
  }) async {
    try {
      final response = await _dioClient.get(
        ApiEndpoints.productReviews(productIdOrSlug),
        queryParameters: {
          'page': page,
          'per_page': perPage,
        },
      );
      final data = response.data['data'];
      if (data is Map<String, dynamic>) {
        return PaginatedReviewsResponse.fromJson(data);
      }
      return const PaginatedReviewsResponse(
        items: [],
        currentPage: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        hasMore: false,
      );
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<PaginatedCommentsResponse> getReviewComments(
    int reviewId, {
    int page = 1,
    int perPage = 15,
  }) async {
    try {
      final response = await _dioClient.get(
        ApiEndpoints.reviewComments(reviewId),
        queryParameters: {
          'page': page,
          'per_page': perPage,
        },
      );
      final data = response.data['data'];
      if (data is Map<String, dynamic>) {
        return PaginatedCommentsResponse.fromJson(data);
      }
      return const PaginatedCommentsResponse(
        items: [],
        currentPage: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        hasMore: false,
      );
    } on ApiFailure {
      rethrow;
    } catch (e) {
      throw NetworkException(e.toString());
    }
  }

  @override
  Future<ReviewCommentModel> createReviewComment({
    required int reviewId,
    required String body,
  }) async {
    try {
      final response = await _dioClient.post(
        ApiEndpoints.reviewComments(reviewId),
        data: {'body': body},
      );
      final data = response.data['data'];
      if (data is Map<String, dynamic>) {
        return ReviewCommentModel.fromJson(data);
      }
      throw NetworkException('استجابة غير صالحة من الخادم.');
    } on ApiFailure {
      rethrow;
    } catch (e) {
      if (e is NetworkException) rethrow;
      throw NetworkException(e.toString());
    }
  }
}
