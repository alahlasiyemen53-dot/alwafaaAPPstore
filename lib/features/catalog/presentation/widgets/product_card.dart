import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/network/connectivity_cubit.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/core/widgets/luxury_badge.dart';
import 'package:store/core/widgets/rating_stars.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;
  final CardVariation variation;
  final VoidCallback? onTap;

  const ProductCard({
    super.key,
    required this.product,
    this.variation = CardVariation.classic,
    this.onTap,
  });

  bool get _canQuickAdd =>
      product.selectedVariantId != null && !product.hasPriceRange;

  Future<void> _onQuickAdd(BuildContext context) async {
    if (context.read<ConnectivityCubit>().state.isOffline) {
      context.showAppSnackBar(
        'لا يوجد اتصال بالإنترنت',
        type: AppSnackBarType.error,
      );
      return;
    }

    if (_canQuickAdd) {
      final cartCubit = context.read<CartCubit>();
      final success = await cartCubit.addToCart(product);
      if (!context.mounted) return;
      if (success) {
        context.showAppSnackBar(
          'تمت إضافة ${product.name} إلى السلة',
          type: AppSnackBarType.success,
          duration: const Duration(milliseconds: 1200),
        );
      } else {
        context.showAppSnackBar(
          cartCubit.state.errorMessage ?? 'تعذر إضافة المنتج إلى السلة.',
          type: AppSnackBarType.error,
        );
      }
      return;
    }

    onTap?.call();
    if (!context.mounted) return;
    context.showAppSnackBar(
      'يرجى اختيار الحجم أو اللون أولاً',
      type: AppSnackBarType.info,
    );
  }

  Future<void> _onToggleWishlist(BuildContext context) async {
    await context
        .read<WishlistCubit>()
        .toggleWishlist(product.id, guestProduct: product);
  }

  @override
  Widget build(BuildContext context) {
    final activeVariation = variation;
    final isWishlisted =
        context.watch<WishlistCubit>().state.contains(product.id);
    final currencyCubit = context.watch<CurrencyCubit>();

    final formattedPrice =
        currencyCubit.state.formatProductBasePrice(product.priceSAR);
    final formattedOriginalPrice = product.originalPriceSAR != null
        ? currencyCubit.state.formatProductBasePrice(product.originalPriceSAR!)
        : null;

    if (activeVariation == CardVariation.compact) {
      return Semantics(
        button: true,
        label: '${product.brand} ${product.name}، $formattedPrice',
        child: GestureDetector(
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18.r),
              border: Border.all(color: AppColors.border, width: 0.8),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            padding: EdgeInsets.all(10.w),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(14.r),
                  child: SizedBox(
                    width: 80.w,
                    height: 90.h,
                    child: CachedImageWidget(imageUrl: product.image),
                  ),
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        product.brand,
                        style: GoogleFonts.cormorantGaramond(
                          fontSize: 11.sp,
                          fontWeight: FontWeight.w600,
                          color: AppColors.goldDark,
                          height: 1.1,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      SizedBox(height: 2.h),
                      Text(
                        product.name,
                        style: TextStyle(
                          fontSize: 12.sp,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                          height: 1.2,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      SizedBox(height: 2.h),
                      RatingStars(
                        rating: product.rating,
                        reviewsCount: product.reviewsCount,
                        starSize: 11.w,
                      ),
                      SizedBox(height: 4.h),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              formattedPrice,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 13.sp,
                                fontWeight: FontWeight.w900,
                                color: AppColors.primaryDark,
                                height: 1.1,
                              ),
                            ),
                          ),
                          SizedBox(width: 8.w),
                          _IconActionButton(
                            semanticLabel: 'إضافة ${product.name} إلى السلة',
                            onTap: () => _onQuickAdd(context),
                            background: AppColors.primaryDark,
                            child: Icon(Icons.add_rounded,
                                size: 18.w, color: Colors.white),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Semantics(
      button: true,
      label: '${product.brand} ${product.name}، $formattedPrice',
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(20.r),
            border: Border.all(color: AppColors.border, width: 0.8),
            boxShadow: [
              BoxShadow(
                color: AppColors.primaryDark.withValues(alpha: 0.04),
                blurRadius: 14,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  AspectRatio(
                    aspectRatio: 1.05,
                    child: Container(
                      color: AppColors.surfaceSoft,
                      child: CachedImageWidget(imageUrl: product.image),
                    ),
                  ),
                  Positioned(
                    top: 8.h,
                    right: 8.w,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (product.isExclusive) ...[
                          const LuxuryBadge(
                              text: 'حصري', type: LuxuryBadgeType.exclusive),
                          SizedBox(height: 4.h),
                        ],
                        if (product.discountPercentage != null &&
                            product.discountPercentage! > 0)
                          LuxuryBadge(
                            text: 'خصم ${product.discountPercentage}%',
                            type: LuxuryBadgeType.discount,
                          ),
                      ],
                    ),
                  ),
                  Positioned(
                    top: 4.h,
                    left: 4.w,
                    child: _IconActionButton(
                      semanticLabel: isWishlisted
                          ? 'إزالة ${product.name} من المفضلة'
                          : 'إضافة ${product.name} إلى المفضلة',
                      onTap: () => _onToggleWishlist(context),
                      background: Colors.white.withValues(alpha: 0.92),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.08),
                          blurRadius: 6,
                        ),
                      ],
                      child: Icon(
                        isWishlisted
                            ? Icons.favorite_rounded
                            : Icons.favorite_border_rounded,
                        size: 18.w,
                        color: isWishlisted
                            ? AppColors.discountBadge
                            : AppColors.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
              Expanded(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(10.w, 6.h, 10.w, 8.h),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        product.brand,
                        style: GoogleFonts.cormorantGaramond(
                          fontSize: 11.sp,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.5,
                          color: AppColors.goldDark,
                          height: 1.1,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      SizedBox(height: 2.h),
                      Expanded(
                        child: Text(
                          product.name,
                          style: TextStyle(
                            fontSize: 11.5.sp,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                            height: 1.2,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      SizedBox(height: 2.h),
                      RatingStars(
                        rating: product.rating,
                        reviewsCount: product.reviewsCount,
                        starSize: 11.w,
                      ),
                      SizedBox(height: 4.h),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (formattedOriginalPrice != null)
                                  Text(
                                    formattedOriginalPrice,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      fontSize: 9.5.sp,
                                      color: AppColors.textMuted,
                                      decoration: TextDecoration.lineThrough,
                                      height: 1.1,
                                    ),
                                  ),
                                Text(
                                  formattedPrice,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 13.sp,
                                    fontWeight: FontWeight.w900,
                                    color: AppColors.primaryDark,
                                    height: 1.1,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          SizedBox(width: 6.w),
                          _IconActionButton(
                            semanticLabel: 'إضافة ${product.name} إلى السلة',
                            onTap: () => _onQuickAdd(context),
                            background: AppColors.primaryDark,
                            borderRadius: BorderRadius.circular(10.r),
                            child: Icon(Icons.shopping_bag_rounded,
                                size: 18.w, color: Colors.white),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Ensures a minimum 48×48 logical touch target with optional visual inset.
class _IconActionButton extends StatelessWidget {
  final String semanticLabel;
  final VoidCallback onTap;
  final Widget child;
  final Color background;
  final BorderRadius? borderRadius;
  final List<BoxShadow>? boxShadow;

  const _IconActionButton({
    required this.semanticLabel,
    required this.onTap,
    required this.child,
    required this.background,
    this.borderRadius,
    this.boxShadow,
  });

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: semanticLabel,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          customBorder: borderRadius != null
              ? RoundedRectangleBorder(borderRadius: borderRadius!)
              : const CircleBorder(),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
            child: Center(
              child: Container(
                padding: EdgeInsets.all(8.w),
                decoration: BoxDecoration(
                  color: background,
                  shape: borderRadius == null
                      ? BoxShape.circle
                      : BoxShape.rectangle,
                  borderRadius: borderRadius,
                  boxShadow: boxShadow,
                ),
                child: child,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
