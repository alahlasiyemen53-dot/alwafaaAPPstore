import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/skeletons/app_skeleton.dart';
import 'package:store/core/widgets/skeletons/product_skeletons.dart';

/// Home initial-load skeleton matching real section order.
class HomeSkeleton extends StatelessWidget {
  const HomeSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView(
        physics: const NeverScrollableScrollPhysics(),
        padding: EdgeInsets.only(bottom: 24.h),
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 0),
            child: AppSkeletonBox(
              width: double.infinity,
              height: 180.h,
              borderRadius: BorderRadius.circular(28.r),
            ),
          ),
          SizedBox(height: 20.h),
          _sectionTitle(),
          SizedBox(height: 12.h),
          SizedBox(
            height: 100.h,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const NeverScrollableScrollPhysics(),
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              itemCount: 5,
              separatorBuilder: (_, __) => SizedBox(width: 12.w),
              itemBuilder: (_, __) => SizedBox(
                width: 72.w,
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final imageSize = (constraints.maxHeight - 18).clamp(32.0, 64.0);
                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        AppSkeletonBox(
                          width: imageSize,
                          height: imageSize,
                          borderRadius: BorderRadius.circular(16.r),
                        ),
                        SizedBox(height: 6.h),
                        AppSkeletonText(width: imageSize * 0.75, height: 8),
                      ],
                    );
                  },
                ),
              ),
            ),
          ),
          SizedBox(height: 16.h),
          _sectionTitle(),
          SizedBox(height: 12.h),
          SizedBox(
            height: 100.h,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const NeverScrollableScrollPhysics(),
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              itemCount: 5,
              separatorBuilder: (_, __) => SizedBox(width: 12.w),
              itemBuilder: (_, __) => SizedBox(
                width: 72.w,
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final imageSize = (constraints.maxHeight - 18).clamp(32.0, 64.0);
                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        AppSkeletonCircle(size: imageSize),
                        SizedBox(height: 6.h),
                        AppSkeletonText(width: imageSize * 0.75, height: 8),
                      ],
                    );
                  },
                ),
              ),
            ),
          ),
          SizedBox(height: 20.h),
          _sectionTitle(),
          SizedBox(height: 12.h),
          const HorizontalProductRailSkeleton(),
          SizedBox(height: 20.h),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: AppSkeletonBox(
              width: double.infinity,
              height: 88.h,
              borderRadius: BorderRadius.circular(18.r),
            ),
          ),
          SizedBox(height: 20.h),
          _sectionTitle(),
          SizedBox(height: 12.h),
          const HorizontalProductRailSkeleton(itemCount: 3),
        ],
      ),
    );
  }

  Widget _sectionTitle() {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 16.w),
      child: AppSkeletonText(width: 120.w, height: 14.h),
    );
  }
}

class BrandsGridSkeleton extends StatelessWidget {
  final int itemCount;

  const BrandsGridSkeleton({super.key, this.itemCount = 9});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 10.h),
            child: AppSkeletonBox(
              width: double.infinity,
              height: 48.h,
              borderRadius: BorderRadius.circular(16.r),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 8.h),
            child: AppSkeletonText(width: 100.w, height: 12.h),
          ),
          Expanded(
            child: GridView.builder(
              padding: EdgeInsets.fromLTRB(16.w, 4.h, 16.w, 24.h),
              itemCount: itemCount,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                mainAxisSpacing: 14.h,
                crossAxisSpacing: 12.w,
                childAspectRatio: 0.78,
              ),
              itemBuilder: (_, __) => Column(
                children: [
                  Expanded(
                    child: AppSkeletonBox(
                      width: double.infinity,
                      height: double.infinity,
                      borderRadius: BorderRadius.circular(18.r),
                    ),
                  ),
                  SizedBox(height: 8.h),
                  AppSkeletonText(width: 56.w, height: 11.h),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class CartListSkeleton extends StatelessWidget {
  final int itemCount;

  const CartListSkeleton({super.key, this.itemCount = 4});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView.separated(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 24.h),
        itemCount: itemCount,
        separatorBuilder: (_, __) => SizedBox(height: 12.h),
        itemBuilder: (_, __) => Container(
          padding: EdgeInsets.all(12.w),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              AppSkeletonBox(
                width: 70.w,
                height: 80.h,
                borderRadius: BorderRadius.circular(14.r),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    AppSkeletonText(width: double.infinity, height: 12.h),
                    SizedBox(height: 8.h),
                    AppSkeletonText(width: 90.w, height: 10.h),
                    SizedBox(height: 12.h),
                    Row(
                      children: [
                        AppSkeletonText(width: 70.w, height: 14.h),
                        const Spacer(),
                        AppSkeletonBox(
                          width: 88.w,
                          height: 28.h,
                          borderRadius: BorderRadius.circular(20.r),
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
}

class OrderCardSkeleton extends StatelessWidget {
  const OrderCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(14.w),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: AppSkeletonText(width: 120, height: 12)),
              AppSkeletonBox(
                width: 72,
                height: 22,
                borderRadius: BorderRadius.circular(20.r),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          const AppSkeletonText(width: 160, height: 10),
          SizedBox(height: 14.h),
          Row(
            children: [
              AppSkeletonBox(
                width: 44,
                height: 44,
                borderRadius: BorderRadius.circular(10.r),
              ),
              SizedBox(width: 8.w),
              AppSkeletonBox(
                width: 44,
                height: 44,
                borderRadius: BorderRadius.circular(10.r),
              ),
              const Spacer(),
              const AppSkeletonText(width: 70, height: 14),
            ],
          ),
        ],
      ),
    );
  }
}

class OrdersListSkeleton extends StatelessWidget {
  final int itemCount;

  const OrdersListSkeleton({super.key, this.itemCount = 5});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView.separated(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
        itemCount: itemCount,
        separatorBuilder: (_, __) => SizedBox(height: 12.h),
        itemBuilder: (_, __) => const OrderCardSkeleton(),
      ),
    );
  }
}

class OrderDetailSkeleton extends StatelessWidget {
  const OrderDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
        children: [
          AppSkeletonBox(
            width: double.infinity,
            height: 88.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 14.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 140.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 14.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 160.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 14.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 120.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
        ],
      ),
    );
  }
}

class CheckoutSkeleton extends StatelessWidget {
  const CheckoutSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
        children: [
          AppSkeletonBox(
            width: double.infinity,
            height: 100.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 16.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 90.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 16.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 90.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 16.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 140.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
        ],
      ),
    );
  }
}

class ReviewCardSkeleton extends StatelessWidget {
  const ReviewCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border.withValues(alpha: 0.6)),
        borderRadius: BorderRadius.circular(12.r),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppSkeletonText(width: 90.w, height: 12.h),
          SizedBox(height: 10.h),
          AppSkeletonText(width: 110.w, height: 12.h),
          SizedBox(height: 10.h),
          AppSkeletonText(width: double.infinity, height: 11.h),
          SizedBox(height: 6.h),
          AppSkeletonText(width: 180.w, height: 11.h),
          SizedBox(height: 12.h),
          AppSkeletonText(width: 80.w, height: 10.h),
        ],
      ),
    );
  }
}

class ReviewsSectionSkeleton extends StatelessWidget {
  final int itemCount;

  const ReviewsSectionSkeleton({super.key, this.itemCount = 2});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Column(
        children: List.generate(
          itemCount,
          (_) => Padding(
            padding: EdgeInsets.only(bottom: 18.h),
            child: const ReviewCardSkeleton(),
          ),
        ),
      ),
    );
  }
}

class ReviewCommentsSkeleton extends StatelessWidget {
  final int itemCount;

  const ReviewCommentsSkeleton({super.key, this.itemCount = 3});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Column(
        children: List.generate(
          itemCount,
          (_) => Padding(
            padding: EdgeInsets.only(bottom: 10.h, right: 8.w),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AppSkeletonText(width: 70.w, height: 10.h),
                SizedBox(height: 6.h),
                AppSkeletonText(width: double.infinity, height: 11.h),
                SizedBox(height: 4.h),
                AppSkeletonText(width: 140.w, height: 11.h),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class RelatedProductsSkeleton extends StatelessWidget {
  const RelatedProductsSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Padding(
        padding: EdgeInsets.only(top: 20.h, bottom: 12.h),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: AppSkeletonText(width: 140.w, height: 14.h),
            ),
            SizedBox(height: 12.h),
            const HorizontalProductRailSkeleton(
              height: 295,
              cardWidth: 170,
              itemCount: 3,
            ),
          ],
        ),
      ),
    );
  }
}

class ProductDetailSkeleton extends StatelessWidget {
  const ProductDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: SingleChildScrollView(
        physics: const NeverScrollableScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: AppSkeletonBox(
                width: double.infinity,
                borderRadius: BorderRadius.zero,
              ),
            ),
            Padding(
              padding: EdgeInsets.all(16.w),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AppSkeletonText(width: 160.w, height: 12.h),
                  SizedBox(height: 12.h),
                  AppSkeletonText(width: double.infinity, height: 18.h),
                  SizedBox(height: 8.h),
                  AppSkeletonText(width: 200.w, height: 18.h),
                  SizedBox(height: 14.h),
                  AppSkeletonText(width: 100.w, height: 20.h),
                  SizedBox(height: 18.h),
                  AppSkeletonBox(
                    width: double.infinity,
                    height: 44.h,
                    borderRadius: BorderRadius.circular(12.r),
                  ),
                  SizedBox(height: 16.h),
                  AppSkeletonText(width: double.infinity, height: 11.h),
                  SizedBox(height: 6.h),
                  AppSkeletonText(width: double.infinity, height: 11.h),
                  SizedBox(height: 6.h),
                  AppSkeletonText(width: 180.w, height: 11.h),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AddressesListSkeleton extends StatelessWidget {
  final int itemCount;

  const AddressesListSkeleton({super.key, this.itemCount = 4});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView.separated(
        padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
        itemCount: itemCount,
        separatorBuilder: (_, __) => SizedBox(height: 12.h),
        itemBuilder: (_, __) => Container(
          padding: EdgeInsets.all(16.w),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  AppSkeletonBox(
                    width: 36.w,
                    height: 36.w,
                    borderRadius: BorderRadius.circular(10.r),
                  ),
                  SizedBox(width: 10.w),
                  const Expanded(child: AppSkeletonText(width: 120, height: 12)),
                  AppSkeletonBox(
                    width: 52.w,
                    height: 22.h,
                    borderRadius: BorderRadius.circular(20.r),
                  ),
                ],
              ),
              SizedBox(height: 12.h),
              AppSkeletonText(width: double.infinity, height: 11.h),
              SizedBox(height: 6.h),
              AppSkeletonText(width: 180.w, height: 11.h),
              SizedBox(height: 12.h),
              Row(
                children: [
                  AppSkeletonBox(
                    width: 72.w,
                    height: 28.h,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  SizedBox(width: 8.w),
                  AppSkeletonBox(
                    width: 72.w,
                    height: 28.h,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class LoyaltySheetSkeleton extends StatelessWidget {
  const LoyaltySheetSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: ListView(
        children: [
          AppSkeletonBox(
            width: double.infinity,
            height: 140.h,
            borderRadius: BorderRadius.circular(20.r),
          ),
          SizedBox(height: 16.h),
          AppSkeletonBox(
            width: double.infinity,
            height: 88.h,
            borderRadius: BorderRadius.circular(16.r),
          ),
          SizedBox(height: 16.h),
          AppSkeletonText(width: 140.w, height: 14.h),
          SizedBox(height: 10.h),
          ...List.generate(
            3,
            (_) => Padding(
              padding: EdgeInsets.only(bottom: 10.h),
              child: AppSkeletonBox(
                width: double.infinity,
                height: 64.h,
                borderRadius: BorderRadius.circular(14.r),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class SupportSheetSkeleton extends StatelessWidget {
  const SupportSheetSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppSkeletonBox(
            width: double.infinity,
            height: 56.h,
            borderRadius: BorderRadius.circular(14.r),
          ),
          SizedBox(height: 16.h),
          ...List.generate(
            3,
            (_) => Padding(
              padding: EdgeInsets.only(bottom: 10.h),
              child: AppSkeletonBox(
                width: double.infinity,
                height: 52.h,
                borderRadius: BorderRadius.circular(14.r),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class FilterChipsSkeleton extends StatelessWidget {
  final int itemCount;

  const FilterChipsSkeleton({super.key, this.itemCount = 6});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Wrap(
        spacing: 8.w,
        runSpacing: 8.h,
        children: List.generate(
          itemCount,
          (index) => AppSkeletonBox(
            width: (64 + (index % 3) * 18).w,
            height: 34.h,
            borderRadius: BorderRadius.circular(20.r),
          ),
        ),
      ),
    );
  }
}

/// Compact shimmer used inside action buttons instead of a spinner.
class ActionButtonSkeleton extends StatelessWidget {
  const ActionButtonSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Opacity(
        opacity: 0.45,
        child: AppSkeletonBox(
          width: 96.w,
          height: 14.h,
          borderRadius: BorderRadius.circular(8.r),
        ),
      ),
    );
  }
}

