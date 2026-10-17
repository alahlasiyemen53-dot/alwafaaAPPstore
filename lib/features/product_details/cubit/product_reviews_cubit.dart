import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../data/models/catalog/product_review_models.dart';
import '../../../../data/repositories/reviews_repository.dart';
import 'product_reviews_state.dart';

class ProductReviewsCubit extends Cubit<ProductReviewsState> {
  final ReviewsRepository _repository;
  int _requestId = 0;
  dynamic _productKey;

  ProductReviewsCubit({ReviewsRepository? repository})
      : _repository = repository ?? ReviewsRepositoryImpl(),
        super(const ProductReviewsState.initial());

  @override
  void emit(ProductReviewsState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<void> loadReviews(dynamic productIdOrSlug, {bool force = false}) async {
    if (productIdOrSlug == null || isClosed) return;
    if (!force &&
        _productKey == productIdOrSlug &&
        (state.isSuccess || state.isEmpty)) {
      return;
    }

    final requestId = ++_requestId;
    _productKey = productIdOrSlug;
    emit(state.copyWith(
      status: ProductReviewsStatus.loading,
      errorMessage: null,
      clearExpanded: true,
    ));

    try {
      final response = await _repository.getProductReviews(productIdOrSlug);
      if (requestId != _requestId || isClosed) return;

      if (response.items.isEmpty) {
        emit(state.copyWith(status: ProductReviewsStatus.empty, reviews: []));
      } else {
        emit(state.copyWith(
          status: ProductReviewsStatus.success,
          reviews: response.items,
        ));
      }
    } catch (e) {
      if (requestId != _requestId || isClosed) return;
      emit(state.copyWith(
        status: ProductReviewsStatus.error,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> loadAllComments(int reviewId) async {
    if (isClosed) return;
    emit(state.copyWith(
      expandedReviewId: reviewId,
      expandedCommentsLoading: true,
      expandedCommentsError: null,
      expandedComments: const [],
    ));

    try {
      final response = await _repository.getReviewComments(reviewId, perPage: 50);
      if (state.expandedReviewId != reviewId || isClosed) return;

      emit(state.copyWith(
        expandedComments: response.items,
        expandedCommentsLoading: false,
      ));
    } catch (e) {
      if (state.expandedReviewId != reviewId || isClosed) return;
      emit(state.copyWith(
        expandedCommentsLoading: false,
        expandedCommentsError: e.toString(),
      ));
    }
  }

  Future<bool> submitComment({
    required int reviewId,
    required String body,
  }) async {
    if (isClosed || state.isSubmitting) return false;

    final trimmed = body.trim();
    if (trimmed.isEmpty) {
      emit(state.copyWith(
        submitStatus: CommentSubmitStatus.failure,
        submitError: 'نص التعليق مطلوب.',
      ));
      return false;
    }

    emit(state.copyWith(
      submitStatus: CommentSubmitStatus.submitting,
      clearSubmitError: true,
    ));

    try {
      final created = await _repository.createReviewComment(
        reviewId: reviewId,
        body: trimmed,
      );
      if (isClosed) return false;

      final updatedReviews = state.reviews.map((review) {
        if (review.id != reviewId) return review;
        final preview = [...review.commentsPreview, created];
        final bounded = preview.length > 2 ? preview.sublist(preview.length - 2) : preview;
        return ProductReviewModel(
          id: review.id,
          rating: review.rating,
          title: review.title,
          body: review.body,
          isVerifiedPurchase: review.isVerifiedPurchase,
          authorDisplayName: review.authorDisplayName,
          commentCount: review.commentCount + 1,
          commentsPreview: bounded,
          createdAt: review.createdAt,
        );
      }).toList();

      final expanded = state.expandedReviewId == reviewId
          ? [...state.expandedComments, created]
          : state.expandedComments;

      emit(state.copyWith(
        reviews: updatedReviews,
        status: updatedReviews.isEmpty
            ? ProductReviewsStatus.empty
            : ProductReviewsStatus.success,
        submitStatus: CommentSubmitStatus.success,
        expandedComments: expanded,
      ));
      return true;
    } catch (e) {
      emit(state.copyWith(
        submitStatus: CommentSubmitStatus.failure,
        submitError: e.toString(),
      ));
      return false;
    }
  }

  void resetSubmitStatus() {
    if (state.submitStatus != CommentSubmitStatus.idle) {
      emit(state.copyWith(
        submitStatus: CommentSubmitStatus.idle,
        clearSubmitError: true,
      ));
    }
  }
}
