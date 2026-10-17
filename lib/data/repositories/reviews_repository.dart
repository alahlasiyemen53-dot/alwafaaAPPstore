import '../datasources/reviews_remote_datasource.dart';
import '../models/catalog/product_review_models.dart';

abstract class ReviewsRepository {
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

class ReviewsRepositoryImpl implements ReviewsRepository {
  final ReviewsRemoteDataSource _remote;

  ReviewsRepositoryImpl({ReviewsRemoteDataSource? remote})
      : _remote = remote ?? ReviewsRemoteDataSourceImpl();

  @override
  Future<PaginatedReviewsResponse> getProductReviews(
    dynamic productIdOrSlug, {
    int page = 1,
    int perPage = 15,
  }) {
    return _remote.getProductReviews(
      productIdOrSlug,
      page: page,
      perPage: perPage,
    );
  }

  @override
  Future<PaginatedCommentsResponse> getReviewComments(
    int reviewId, {
    int page = 1,
    int perPage = 15,
  }) {
    return _remote.getReviewComments(
      reviewId,
      page: page,
      perPage: perPage,
    );
  }

  @override
  Future<ReviewCommentModel> createReviewComment({
    required int reviewId,
    required String body,
  }) {
    return _remote.createReviewComment(reviewId: reviewId, body: body);
  }
}
