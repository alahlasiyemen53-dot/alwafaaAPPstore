import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/skeletons/app_skeleton.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';

/// Layout-matching skeleton for [ProductCard] classic variation.
class ProductCardSkeleton extends StatelessWidget {
  final CardVariation variation;

  const ProductCardSkeleton({
    super.key,
    this.variation = CardVariation.classic,
  });

  @override
  Widget build(BuildContext context) {
    if (variation == CardVariation.compact) {
      return Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(18.r),
          border: Border.all(color: AppColors.border, width: 0.8),
        ),
        padding: EdgeInsets.all(10.w),
        child: Row(
          children: [
            AppSkeletonBox(
              width: 80.w,
              height: 90.h,
              borderRadius: BorderRadius.circular(14.r),
            ),
            SizedBox(width: 12.w),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  AppSkeletonText(width: 56.w, height: 10.h),
                  SizedBox(height: 8.h),
                  AppSkeletonText(width: double.infinity, height: 12.h),
                  SizedBox(height: 6.h),
                  AppSkeletonText(width: 90.w, height: 12.h),
                  SizedBox(height: 10.h),
                  AppSkeletonText(width: 70.w, height: 14.h),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Expanded(
            flex: 11,
            child: AppSkeletonBox(
              width: double.infinity,
              borderRadius: BorderRadius.zero,
            ),
          ),
          Expanded(
            flex: 9,
            child: Padding(
              padding: EdgeInsets.all(8.w),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final line = (constraints.maxHeight / 6).clamp(6.0, 12.0);
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AppSkeletonText(width: 48, height: line),
                      SizedBox(height: line * 0.5),
                      AppSkeletonText(
                        width: constraints.maxWidth,
                        height: line,
                      ),
                      SizedBox(height: line * 0.4),
                      AppSkeletonText(width: 80, height: line),
                      const Spacer(),
                      Row(
                        children: [
                          const Expanded(
                            child: AppSkeletonText(width: 70, height: 12),
                          ),
                          AppSkeletonCircle(size: line * 2),
                        ],
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ProductGridSkeleton extends StatelessWidget {
  final int itemCount;
  final CardVariation variation;
  final bool shrinkWrap;

  const ProductGridSkeleton({
    super.key,
    this.itemCount = 6,
    this.variation = CardVariation.classic,
    this.shrinkWrap = false,
  });

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: GridView.builder(
        shrinkWrap: shrinkWrap,
        physics: shrinkWrap
            ? const NeverScrollableScrollPhysics()
            : const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 16.h),
        itemCount: itemCount,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 12.h,
          crossAxisSpacing: 12.w,
          childAspectRatio: 0.62,
        ),
        itemBuilder: (_, __) => ProductCardSkeleton(variation: variation),
      ),
    );
  }
}

/// Horizontal product rail skeleton used on Home / Related Products.
class HorizontalProductRailSkeleton extends StatelessWidget {
  final int itemCount;
  final double height;
  final double cardWidth;

  const HorizontalProductRailSkeleton({
    super.key,
    this.itemCount = 4,
    this.height = 285,
    this.cardWidth = 170,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height.h,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        padding: EdgeInsets.symmetric(horizontal: 16.w),
        itemCount: itemCount,
        separatorBuilder: (_, __) => SizedBox(width: 12.w),
        itemBuilder: (_, __) => SizedBox(
          width: cardWidth.w,
          child: const ProductCardSkeleton(),
        ),
      ),
    );
  }
}

/// Bottom pagination skeleton rows (keeps existing items visible).
class ProductPaginationSkeleton extends StatelessWidget {
  final CardVariation variation;
  final int count;

  const ProductPaginationSkeleton({
    super.key,
    this.variation = CardVariation.classic,
    this.count = 2,
  });

  @override
  Widget build(BuildContext context) {
    if (variation == CardVariation.compact) {
      return AppShimmer(
        child: Column(
          children: List.generate(
            count,
            (_) => Padding(
              padding: EdgeInsets.only(bottom: 12.h),
              child: ProductCardSkeleton(variation: variation),
            ),
          ),
        ),
      );
    }

    return AppShimmer(
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: count,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 12.h,
          crossAxisSpacing: 12.w,
          childAspectRatio: 0.62,
        ),
        itemBuilder: (_, __) => ProductCardSkeleton(variation: variation),
      ),
    );
  }
}
