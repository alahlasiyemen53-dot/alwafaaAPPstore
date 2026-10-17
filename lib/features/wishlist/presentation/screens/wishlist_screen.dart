import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/app_empty_state.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/product_card.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';

class WishlistScreen extends StatefulWidget {
  const WishlistScreen({super.key});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  @override
  void initState() {
    super.initState();
    // Reconcile wishlist with server on screen entrance
    context.read<WishlistCubit>().fetchWishlist();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<WishlistCubit, WishlistState>(
      listener: (context, state) {
        if (state.errorMessage != null && state.items.isNotEmpty) {
          context.showAppSnackBar(
            state.errorMessage!,
            type: AppSnackBarType.error,
          );
        }
      },
      builder: (context, state) {
        if (state.status == WishlistStatus.loading && state.items.isEmpty) {
          return const ProductGridSkeleton();
        }

        if (state.status == WishlistStatus.error && state.items.isEmpty) {
          return Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline_rounded,
                      size: 48.w, color: AppColors.error),
                  SizedBox(height: 16.h),
                  Text(
                    state.errorMessage ?? 'تعذر تحميل قائمة المفضلة',
                    style: AppTypography.titleMedium.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  SizedBox(height: 16.h),
                  LuxuryButton(
                    text: 'إعادة المحاولة',
                    onPressed: () =>
                        context.read<WishlistCubit>().fetchWishlist(),
                  ),
                ],
              ),
            ),
          );
        }

        final products = state.wishlistProducts;

        if (products.isEmpty) {
          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () => context.read<WishlistCubit>().fetchWishlist(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(height: 120.h),
                SizedBox(
                  height: 360.h,
                  child: AppEmptyState(
                    icon: Icons.favorite_border_rounded,
                    title: 'مفضلتك بانتظار اختيارك',
                    message:
                        'احفظي العطور والمستحضرات التي تحبينها للعودة إليها لاحقاً.',
                    actionLabel: 'استكشف المنتجات',
                    onAction: () =>
                        context.read<AppShellCubit>().setTab(1),
                    accentColor: AppColors.discountBadge,
                  ),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          color: AppColors.primaryDark,
          onRefresh: () => context.read<WishlistCubit>().fetchWishlist(),
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        'قائمة المفضلة (${products.length})',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.titleLarge.copyWith(
                          fontWeight: FontWeight.w800,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                    Flexible(
                      child: Text(
                        'حفظ دائم لتفضيلاتك',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 11.sp, color: AppColors.textMuted),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: GridView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: EdgeInsets.only(
                    left: 16.w,
                    right: 16.w,
                    bottom: 120.h + MediaQuery.of(context).padding.bottom,
                  ),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    childAspectRatio: 0.62,
                    crossAxisSpacing: 12.w,
                    mainAxisSpacing: 12.h,
                  ),
                  itemCount: products.length,
                  itemBuilder: (context, index) {
                    final product = products[index];
                    return ProductCard(
                      product: product,
                      variation: CardVariation.classic,
                      onTap: () {
                        context
                            .read<AppShellCubit>()
                            .openProductDetails(product);
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
