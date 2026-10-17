import 'package:equatable/equatable.dart';
import '../../../../data/models/catalog/product_review_models.dart';

enum ProductReviewsStatus { initial, loading, success, empty, error }

enum CommentSubmitStatus { idle, submitting, success, failure }

class ProductReviewsState extends Equatable {
  final ProductReviewsStatus status;
  final List<ProductReviewModel> reviews;
  final String? errorMessage;
  final CommentSubmitStatus submitStatus;
  final String? submitError;
  final int? expandedReviewId;
  final List<ReviewCommentModel> expandedComments;
  final bool expandedCommentsLoading;
  final String? expandedCommentsError;

  const ProductReviewsState({
    this.status = ProductReviewsStatus.initial,
    this.reviews = const [],
    this.errorMessage,
    this.submitStatus = CommentSubmitStatus.idle,
    this.submitError,
    this.expandedReviewId,
    this.expandedComments = const [],
    this.expandedCommentsLoading = false,
    this.expandedCommentsError,
  });

  const ProductReviewsState.initial()
      : this(status: ProductReviewsStatus.initial);

  bool get isLoading => status == ProductReviewsStatus.loading;
  bool get isSuccess => status == ProductReviewsStatus.success;
  bool get isEmpty => status == ProductReviewsStatus.empty;
  bool get isError => status == ProductReviewsStatus.error;
  bool get isSubmitting => submitStatus == CommentSubmitStatus.submitting;

  ProductReviewsState copyWith({
    ProductReviewsStatus? status,
    List<ProductReviewModel>? reviews,
    String? errorMessage,
    CommentSubmitStatus? submitStatus,
    String? submitError,
    int? expandedReviewId,
    List<ReviewCommentModel>? expandedComments,
    bool? expandedCommentsLoading,
    String? expandedCommentsError,
    bool clearExpanded = false,
    bool clearSubmitError = false,
  }) {
    return ProductReviewsState(
      status: status ?? this.status,
      reviews: reviews ?? this.reviews,
      errorMessage: errorMessage ?? this.errorMessage,
      submitStatus: submitStatus ?? this.submitStatus,
      submitError: clearSubmitError ? null : (submitError ?? this.submitError),
      expandedReviewId:
          clearExpanded ? null : (expandedReviewId ?? this.expandedReviewId),
      expandedComments: clearExpanded
          ? const []
          : (expandedComments ?? this.expandedComments),
      expandedCommentsLoading:
          expandedCommentsLoading ?? this.expandedCommentsLoading,
      expandedCommentsError: clearExpanded
          ? null
          : (expandedCommentsError ?? this.expandedCommentsError),
    );
  }

  @override
  List<Object?> get props => [
        status,
        reviews,
        errorMessage,
        submitStatus,
        submitError,
        expandedReviewId,
        expandedComments,
        expandedCommentsLoading,
        expandedCommentsError,
      ];
}
