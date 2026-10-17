import 'package:equatable/equatable.dart';

class ReviewCommentAuthor extends Equatable {
  final String displayName;
  final String type;

  const ReviewCommentAuthor({
    required this.displayName,
    required this.type,
  });

  factory ReviewCommentAuthor.fromJson(Map<String, dynamic> json) {
    return ReviewCommentAuthor(
      displayName: (json['display_name'] as String?)?.trim().isNotEmpty == true
          ? json['display_name'] as String
          : 'عميل',
      type: (json['type'] as String?) ?? 'customer',
    );
  }

  bool get isAdmin => type == 'admin';

  @override
  List<Object?> get props => [displayName, type];
}

class ReviewCommentModel extends Equatable {
  final int id;
  final String body;
  final bool isOfficialAdminReply;
  final ReviewCommentAuthor author;
  final DateTime? createdAt;

  const ReviewCommentModel({
    required this.id,
    required this.body,
    required this.isOfficialAdminReply,
    required this.author,
    this.createdAt,
  });

  factory ReviewCommentModel.fromJson(Map<String, dynamic> json) {
    return ReviewCommentModel(
      id: (json['id'] as num).toInt(),
      body: (json['body'] as String?) ?? '',
      isOfficialAdminReply: json['is_official_admin_reply'] == true,
      author: ReviewCommentAuthor.fromJson(
        json['author'] as Map<String, dynamic>? ?? const {},
      ),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
    );
  }

  @override
  List<Object?> get props =>
      [id, body, isOfficialAdminReply, author, createdAt];
}

class ProductReviewModel extends Equatable {
  final int id;
  final int rating;
  final String? title;
  final String? body;
  final bool isVerifiedPurchase;
  final String authorDisplayName;
  final int commentCount;
  final List<ReviewCommentModel> commentsPreview;
  final DateTime? createdAt;

  const ProductReviewModel({
    required this.id,
    required this.rating,
    this.title,
    this.body,
    required this.isVerifiedPurchase,
    required this.authorDisplayName,
    required this.commentCount,
    required this.commentsPreview,
    this.createdAt,
  });

  factory ProductReviewModel.fromJson(Map<String, dynamic> json) {
    final author = json['author'] as Map<String, dynamic>? ?? {};
    final previewRaw = json['comments_preview'];
    final preview = previewRaw is List
        ? previewRaw
            .whereType<Map<String, dynamic>>()
            .map(ReviewCommentModel.fromJson)
            .toList()
        : <ReviewCommentModel>[];

    return ProductReviewModel(
      id: (json['id'] as num).toInt(),
      rating: (json['rating'] as num?)?.toInt() ?? 0,
      title: json['title'] as String?,
      body: json['body'] as String?,
      isVerifiedPurchase: json['is_verified_purchase'] == true,
      authorDisplayName: (author['display_name'] as String?)?.trim().isNotEmpty ==
              true
          ? author['display_name'] as String
          : 'عميل',
      commentCount: (json['comment_count'] as num?)?.toInt() ?? 0,
      commentsPreview: preview,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
    );
  }

  @override
  List<Object?> get props => [
        id,
        rating,
        title,
        body,
        isVerifiedPurchase,
        authorDisplayName,
        commentCount,
        commentsPreview,
        createdAt,
      ];
}

class PaginatedReviewsResponse extends Equatable {
  final List<ProductReviewModel> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const PaginatedReviewsResponse({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  factory PaginatedReviewsResponse.fromJson(Map<String, dynamic> json) {
    final pagination = json['pagination'] as Map<String, dynamic>? ?? {};
    final itemsRaw = json['items'];
    final items = itemsRaw is List
        ? itemsRaw
            .whereType<Map<String, dynamic>>()
            .map(ProductReviewModel.fromJson)
            .toList()
        : <ProductReviewModel>[];

    return PaginatedReviewsResponse(
      items: items,
      currentPage: (pagination['current_page'] as num?)?.toInt() ?? 1,
      perPage: (pagination['per_page'] as num?)?.toInt() ?? 15,
      total: (pagination['total'] as num?)?.toInt() ?? items.length,
      lastPage: (pagination['last_page'] as num?)?.toInt() ?? 1,
      hasMore: pagination['has_more'] == true,
    );
  }

  @override
  List<Object?> get props =>
      [items, currentPage, perPage, total, lastPage, hasMore];
}

class PaginatedCommentsResponse extends Equatable {
  final List<ReviewCommentModel> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const PaginatedCommentsResponse({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  factory PaginatedCommentsResponse.fromJson(Map<String, dynamic> json) {
    final pagination = json['pagination'] as Map<String, dynamic>? ?? {};
    final itemsRaw = json['items'];
    final items = itemsRaw is List
        ? itemsRaw
            .whereType<Map<String, dynamic>>()
            .map(ReviewCommentModel.fromJson)
            .toList()
        : <ReviewCommentModel>[];

    return PaginatedCommentsResponse(
      items: items,
      currentPage: (pagination['current_page'] as num?)?.toInt() ?? 1,
      perPage: (pagination['per_page'] as num?)?.toInt() ?? 15,
      total: (pagination['total'] as num?)?.toInt() ?? items.length,
      lastPage: (pagination['last_page'] as num?)?.toInt() ?? 1,
      hasMore: pagination['has_more'] == true,
    );
  }

  @override
  List<Object?> get props =>
      [items, currentPage, perPage, total, lastPage, hasMore];
}
