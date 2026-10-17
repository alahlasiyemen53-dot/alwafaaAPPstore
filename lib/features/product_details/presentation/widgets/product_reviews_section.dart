import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/rating_stars.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/data/models/catalog/product_review_models.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/account/presentation/widgets/auth_modal_sheet.dart';
import 'package:store/features/product_details/cubit/product_reviews_cubit.dart';
import 'package:store/features/product_details/cubit/product_reviews_state.dart';

/// Host widget that owns load + retry wiring for a product's reviews.
class ProductReviewsHost extends StatefulWidget {
  final dynamic productId;

  const ProductReviewsHost({super.key, required this.productId});

  @override
  State<ProductReviewsHost> createState() => _ProductReviewsHostState();
}

class _ProductReviewsHostState extends State<ProductReviewsHost> {
  late final ProductReviewsCubit _cubit;

  @override
  void initState() {
    super.initState();
    _cubit = ProductReviewsCubit()..loadReviews(widget.productId);
  }

  @override
  void didUpdateWidget(covariant ProductReviewsHost oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.productId != widget.productId) {
      _cubit.loadReviews(widget.productId, force: true);
    }
  }

  @override
  void dispose() {
    _cubit.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.productId == null) {
      return const SizedBox.shrink();
    }

    return BlocProvider.value(
      value: _cubit,
      child: BlocBuilder<ProductReviewsCubit, ProductReviewsState>(
        builder: (context, state) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'آراء العملاء',
                style: AppTypography.titleMedium.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primaryDark,
                  fontSize: 16.sp,
                ),
              ),
              SizedBox(height: 12.h),
              if (state.isLoading)
                const ReviewsSectionSkeleton()
              else if (state.isError)
                _ErrorBox(
                  message: 'تعذر تحميل الآراء',
                  onRetry: () =>
                      _cubit.loadReviews(widget.productId, force: true),
                )
              else if (state.isEmpty)
                Text(
                  'لا توجد آراء معتمدة لهذا المنتج بعد.',
                  style: AppTypography.bodyMedium.copyWith(
                    color: AppColors.textMuted,
                  ),
                )
              else
                ...state.reviews.map(
                  (review) => Padding(
                    padding: EdgeInsets.only(bottom: 18.h),
                    child: _ReviewCard(review: review),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _ReviewCard extends StatelessWidget {
  final ProductReviewModel review;

  const _ReviewCard({required this.review});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<ProductReviewsCubit>().state;
    final isExpanded = state.expandedReviewId == review.id;

    return Container(
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border.withValues(alpha: 0.6)),
        borderRadius: BorderRadius.circular(12.r),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RatingStars(rating: review.rating.toDouble(), starSize: 14.w),
          SizedBox(height: 8.h),
          Wrap(
            spacing: 8.w,
            runSpacing: 4.h,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                maskCustomerName(review.authorDisplayName),
                style: AppTypography.bodyMedium.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
              if (review.isVerifiedPurchase)
                Text(
                  '✓ مشتري موثّق',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.success,
                    fontWeight: FontWeight.w600,
                  ),
                ),
            ],
          ),
          if ((review.body ?? '').trim().isNotEmpty) ...[
            SizedBox(height: 8.h),
            Text(
              review.body!,
              style: AppTypography.bodyMedium.copyWith(
                color: AppColors.textPrimary,
                height: 1.45,
              ),
            ),
          ],
          SizedBox(height: 10.h),
          Text(
            '💬 ${review.commentCount} تعليقات',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (!isExpanded && review.commentsPreview.isNotEmpty) ...[
            SizedBox(height: 8.h),
            ...review.commentsPreview.map(
              (c) => Padding(
                padding: EdgeInsets.only(bottom: 8.h, right: 8.w),
                child: _CommentTile(comment: c),
              ),
            ),
          ],
          if (isExpanded) ...[
            SizedBox(height: 8.h),
            if (state.expandedCommentsLoading)
              const ReviewCommentsSkeleton()
            else if (state.expandedCommentsError != null)
              _ErrorBox(
                message: 'تعذر تحميل التعليقات',
                onRetry: () => context
                    .read<ProductReviewsCubit>()
                    .loadAllComments(review.id),
              )
            else if (state.expandedComments.isEmpty)
              Text(
                'لا توجد تعليقات ظاهرة.',
                style: AppTypography.bodySmall
                    .copyWith(color: AppColors.textMuted),
              )
            else
              ...state.expandedComments.map(
                (c) => Padding(
                  padding: EdgeInsets.only(bottom: 8.h, right: 8.w),
                  child: _CommentTile(comment: c),
                ),
              ),
          ],
          SizedBox(height: 8.h),
          Wrap(
            spacing: 8.w,
            runSpacing: 8.h,
            children: [
              TextButton(
                onPressed: () => context
                    .read<ProductReviewsCubit>()
                    .loadAllComments(review.id),
                child: Text(
                  isExpanded ? 'تحديث التعليقات' : 'عرض كل التعليقات',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.goldDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              TextButton(
                onPressed: () => _openAddComment(context, review.id),
                child: Text(
                  'أضف تعليقًا',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.primaryDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _openAddComment(BuildContext context, int reviewId) async {
    final account = context.read<AccountCubit>();
    if (!account.state.isLoggedIn) {
      final authenticated = await AuthModalSheet.show(context);
      if (authenticated != true || !context.mounted) return;
    }

    if (!context.mounted) return;
    final cubit = context.read<ProductReviewsCubit>();

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16.r)),
      ),
      builder: (_) => _AddCommentSheet(
        cubit: cubit,
        reviewId: reviewId,
      ),
    );

    cubit.resetSubmitStatus();
  }
}

class _AddCommentSheet extends StatefulWidget {
  final ProductReviewsCubit cubit;
  final int reviewId;

  const _AddCommentSheet({
    required this.cubit,
    required this.reviewId,
  });

  @override
  State<_AddCommentSheet> createState() => _AddCommentSheetState();
}

class _AddCommentSheetState extends State<_AddCommentSheet> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottomSafeArea = MediaQuery.paddingOf(context).bottom;
    final keyboardInset = MediaQuery.viewInsetsOf(context).bottom;
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.only(
          left: 16.w,
          right: 16.w,
          top: 16.h,
          bottom: keyboardInset > 0
              ? keyboardInset + 16.h
              : bottomSafeArea + 16.h,
        ),
        child: SingleChildScrollView(
          child: BlocProvider.value(
            value: widget.cubit,
            child: BlocBuilder<ProductReviewsCubit, ProductReviewsState>(
              builder: (context, state) {
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'أضف تعليقًا',
                      style: AppTypography.titleMedium.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 12.h),
                    TextField(
                      controller: _controller,
                      maxLines: 4,
                      maxLength: 1000,
                      enabled: !state.isSubmitting,
                      decoration: const InputDecoration(
                        hintText: 'اكتب تعليقك هنا...',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    if (state.submitError != null) ...[
                      SizedBox(height: 8.h),
                      Text(
                        state.submitError!,
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.error,
                        ),
                      ),
                    ],
                    SizedBox(height: 12.h),
                    ElevatedButton(
                      onPressed: state.isSubmitting
                          ? null
                          : () async {
                              final ok = await widget.cubit.submitComment(
                                reviewId: widget.reviewId,
                                body: _controller.text,
                              );
                              if (ok && context.mounted) {
                                FocusScope.of(context).unfocus();
                                Navigator.of(context).pop();
                              }
                            },
                      child: state.isSubmitting
                          ? SizedBox(
                              width: 18.w,
                              height: 18.w,
                              child: const CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('إرسال'),
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _CommentTile extends StatelessWidget {
  final ReviewCommentModel comment;

  const _CommentTile({required this.comment});

  @override
  Widget build(BuildContext context) {
    final isAdmin = comment.isOfficialAdminReply || comment.author.isAdmin;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          isAdmin ? 'إدارة لمّة ✓' : maskCustomerName(comment.author.displayName),
          style: AppTypography.bodySmall.copyWith(
            fontWeight: FontWeight.w700,
            color: isAdmin ? AppColors.goldDark : AppColors.primaryDark,
          ),
        ),
        SizedBox(height: 2.h),
        Text(
          comment.body,
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textPrimary,
            height: 1.4,
          ),
        ),
      ],
    );
  }
}

/// أفضل ممارسات خصوصية وتشفير أسماء العملاء في المراجعات
/// مثل: "براء" -> "بر****اء", "محمد أحمد" -> "مح****مد أح****مد"
String maskCustomerName(String? rawName) {
  final name = (rawName ?? '').trim();
  if (name.isEmpty) return 'عميل';
  if (name == 'إدارة لمّة' || name == 'عميل' || name.contains('*')) {
    return name;
  }

  final words = name.split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
  if (words.isEmpty) return 'عميل';

  return words.map(_maskWord).join(' ');
}

String _maskWord(String word) {
  final chars = word.characters.toList();
  if (chars.length <= 2) {
    return '${chars.first}***';
  }
  if (chars.length == 3) {
    return '${chars.first}***${chars.last}';
  }
  // 4 أحرف أو أكثر: أول حرفين + **** + آخر حرفين
  final start = chars.take(2).join();
  final end = chars.skip(chars.length - 2).join();
  return '$start****$end';
}

class _ErrorBox extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorBox({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(10.r),
      ),
      child: Column(
        children: [
          Text(
            message,
            style: AppTypography.bodySmall.copyWith(color: AppColors.error),
          ),
          TextButton(
            onPressed: onRetry,
            child: const Text('إعادة المحاولة'),
          ),
        ],
      ),
    );
  }
}
