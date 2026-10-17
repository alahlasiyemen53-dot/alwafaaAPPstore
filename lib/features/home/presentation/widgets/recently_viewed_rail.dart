import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../data/models/product_model.dart';
import '../../../../features/app_shell/cubit/app_shell_cubit.dart';
import '../../../../features/catalog/cubit/catalog_cubit.dart';
import '../../../../features/catalog/presentation/widgets/product_card.dart';
import '../../../../features/discovery/cubit/recently_viewed_cubit.dart';

class RecentlyViewedRail extends StatelessWidget {
  final String title;
  final List<ProductModel>? products;

  const RecentlyViewedRail({
    super.key,
    this.title = 'شاهدتها مؤخراً',
    this.products,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<RecentlyViewedCubit, RecentlyViewedState>(
      builder: (context, state) {
        final list = products ?? state.products;
        if (list.isEmpty) return const SizedBox.shrink();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Text(
                title,
                style: AppTypography.titleMedium.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
            ),
            SizedBox(height: 12.h),
            SizedBox(
              height: 285.h,
              child: ListView.separated(
                padding: EdgeInsets.symmetric(horizontal: 16.w),
                scrollDirection: Axis.horizontal,
                itemCount: list.length,
                separatorBuilder: (_, __) => SizedBox(width: 14.w),
                itemBuilder: (context, index) {
                  final product = list[index];
                  return SizedBox(
                    width: 170.w,
                    child: ProductCard(
                      product: product,
                      variation: CardVariation.classic,
                      onTap: () {
                        context
                            .read<AppShellCubit>()
                            .openProductDetails(product);
                      },
                    ),
                  );
                },
              ),
            ),
            SizedBox(height: 20.h),
          ],
        );
      },
    );
  }
}
