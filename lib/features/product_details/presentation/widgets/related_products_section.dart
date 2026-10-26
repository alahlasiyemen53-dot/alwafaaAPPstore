import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/widgets/skeletons/skeletons.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../app_shell/cubit/app_shell_cubit.dart';
import '../../../catalog/cubit/catalog_cubit.dart';
import '../../../catalog/presentation/widgets/product_card.dart';
import '../../cubit/related_products_cubit.dart';
import '../../cubit/related_products_state.dart';

class RelatedProductsSection extends StatelessWidget {
  final VoidCallback? onRetry;

  const RelatedProductsSection({
    super.key,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<RelatedProductsCubit, RelatedProductsState>(
      builder: (context, state) {
        if (state.isEmpty || (state.isSuccess && state.products.isEmpty)) {
          // Mandated rule: Hide section on legitimate empty results
          return const SizedBox.shrink();
        }

        if (state.isLoading) {
          return const RelatedProductsSkeleton();
        }

        if (state.isError) {
          // Product detail remains completely usable even if related products fail
          return Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(12.r),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  Icon(Icons.info_outline, size: 16.w, color: AppColors.textMuted),
                  SizedBox(width: 8.w),
                  Expanded(
                    child: Text(
                      'تعذر تحميل المنتجات ذات الصلة',
                      style: TextStyle(fontSize: 11.sp, color: AppColors.textMuted),
                    ),
                  ),
                  if (onRetry != null)
                    TextButton(
                      onPressed: onRetry,
                      child: const Text('إعادة المحاولة', style: TextStyle(fontSize: 11)),
                    ),
                ],
              ),
            ),
          );
        }

        if (state.isSuccess) {
          final products = state.products;
          return Padding(
            padding: EdgeInsets.only(top: 20.h, bottom: 8.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16.w),
                  child: Text(
                    AppStrings.relatedProducts,
                    style: AppTypography.titleMedium.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
                SizedBox(height: 12.h),
                SizedBox(
                  height: 295.h,
                  child: ListView.separated(
                    padding: EdgeInsets.symmetric(horizontal: 16.w),
                    scrollDirection: Axis.horizontal,
                    itemCount: products.length,
                    separatorBuilder: (_, _) => SizedBox(width: 14.w),
                    itemBuilder: (context, index) {
                      final item = products[index];
                      return SizedBox(
                        width: 170.w,
                        child: ProductCard(
                          product: item,
                          variation: CardVariation.classic,
                          onTap: () {
                            context.read<AppShellCubit>().openProductDetails(item);
                          },
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          );
        }

        return const SizedBox.shrink();
      },
    );
  }
}
