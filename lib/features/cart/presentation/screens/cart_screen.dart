import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/app_empty_state.dart';
import 'package:store/core/widgets/app_error_state.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/account/presentation/widgets/auth_modal_sheet.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';
import '../widgets/cart_item_tile.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  @override
  void initState() {
    super.initState();
    // Reconcile cart with server truth on entrance
    context.read<CartCubit>().fetchCart();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<CartCubit, CartState>(
      listener: (context, state) {
        if (state.errorMessage != null && state.items.isNotEmpty) {
          context.showAppSnackBar(
            state.errorMessage!,
            type: AppSnackBarType.error,
          );
        }
      },
      builder: (context, state) {
        if (state.status == CartStatus.loading && state.items.isEmpty) {
          return const CartListSkeleton();
        }

        if (state.status == CartStatus.error && state.items.isEmpty) {
          return AppErrorState(
            title: 'تعذر تحميل سلة المشتريات',
            message: state.errorMessage,
            icon: Icons.shopping_bag_outlined,
            onRetry: () => context.read<CartCubit>().fetchCart(),
          );
        }

        if (state.items.isEmpty) {
          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () => context.read<CartCubit>().fetchCart(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(height: 120.h),
                SizedBox(
                  height: 360.h,
                  child: AppEmptyState(
                    icon: Icons.shopping_bag_outlined,
                    title: AppStrings.cartEmpty,
                    message: AppStrings.cartEmptyDesc,
                    actionLabel: 'تصفح المنتجات',
                    onAction: () =>
                        context.read<AppShellCubit>().setTab(1),
                  ),
                ),
              ],
            ),
          );
        }

        final currencyState = context.watch<CurrencyCubit>().state;
        final formattedSubtotal = currencyState
            .formatProductBasePrice(state.checkoutSubtotalSAR);
        final formattedDiscount = currencyState
            .formatProductBasePrice(state.checkoutDiscountSAR);
        final formattedTotal =
            currencyState.formatProductBasePrice(state.checkoutTotalSAR);
        final isLoggedIn = context.watch<AccountCubit>().state.isLoggedIn;
        final canProceed = state.hasSelection &&
            (!isLoggedIn ||
                state.selectedCartItemIdsForCheckout == null ||
                state.selectedCartItemIdsForCheckout!.isNotEmpty);

        return RefreshIndicator(
          color: AppColors.primaryDark,
          onRefresh: () => context.read<CartCubit>().fetchCart(),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.only(
              left: 16.w,
              right: 16.w,
              top: 12.h,
              bottom: 120.h + MediaQuery.of(context).padding.bottom,
            ),
            children: [
              // Title Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      '${AppStrings.cartTitle} (${state.totalItemsCount})',
                      style: AppTypography.titleLarge.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark,
                      ),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: () => context.read<CartCubit>().clearCart(),
                    icon: Icon(Icons.delete_sweep_rounded,
                        size: 16.w, color: AppColors.error),
                    label: Text('تفريغ السلة',
                        style:
                            TextStyle(fontSize: 11.sp, color: AppColors.error)),
                  ),
                ],
              ),
              SizedBox(height: 8.h),

              _SelectionToolbar(state: state),
              SizedBox(height: 10.h),

              // Cart Items
              ...state.items.asMap().entries.map((entry) {
                final item = entry.value;
                return Padding(
                  padding: EdgeInsets.only(bottom: 6.h),
                  child: CartItemTile(
                    item: item,
                    index: entry.key,
                    selected: state.isItemSelected(item),
                    onSelectedChanged: (_) => context
                        .read<CartCubit>()
                        .toggleItemSelection(item.selectionKey),
                  ),
                );
              }),

              SizedBox(height: 16.h),

              // Server Authoritative Cost Summary Card
              Container(
                padding: EdgeInsets.all(16.w),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(20.r),
                  border: Border.all(color: AppColors.border, width: 0.8),
                ),
                child: Column(
                  children: [
                    _buildSummaryRow(
                      state.isPartialSelection
                          ? 'مجموع المحدد'
                          : AppStrings.subtotal,
                      formattedSubtotal,
                    ),
                    SizedBox(height: 8.h),
                    _buildSummaryRow(
                      AppStrings.shipping,
                      'يُحدد عند إتمام الطلب',
                      valueColor: AppColors.textMuted,
                    ),
                    if (state.checkoutDiscountSAR > 0) ...[
                      SizedBox(height: 8.h),
                      _buildSummaryRow(
                        AppStrings.discount,
                        '- $formattedDiscount',
                        valueColor: AppColors.discountBadge,
                      ),
                    ],
                    if (state.isPartialSelection && state.isPromoValid) ...[
                      SizedBox(height: 8.h),
                      Text(
                        'يُحسب الخصم النهائي على المنتجات المحددة عند إتمام الطلب',
                        style: TextStyle(
                          fontSize: 10.sp,
                          color: AppColors.textMuted,
                          height: 1.35,
                        ),
                      ),
                    ],
                    Divider(color: AppColors.border, height: 20.h),
                    _buildSummaryRow(
                      state.isPartialSelection ? 'إجمالي المحدد' : AppStrings.total,
                      formattedTotal,
                      isTotal: true,
                    ),
                  ],
                ),
              ),

              SizedBox(height: 20.h),

              // Proceed Button
              LuxuryButton(
                text: !state.hasSelection
                    ? 'اختر منتجاً للشراء'
                    : !isLoggedIn
                        ? 'تسجيل الدخول لإتمام الطلب'
                        : state.isPartialSelection
                            ? 'شراء المحدد (${state.selectedLinesCount})'
                            : AppStrings.proceedToCheckout,
                width: double.infinity,
                icon: const Icon(Icons.arrow_forward_rounded,
                    color: Colors.white, size: 18),
                onPressed: !canProceed
                    ? null
                    : () async {
                        final account = context.read<AccountCubit>();
                        final cartCubit = context.read<CartCubit>();
                        final wishlistCubit = context.read<WishlistCubit>();
                        if (!account.state.isLoggedIn) {
                          final authenticated =
                              await AuthModalSheet.show(context);
                          if (!context.mounted || authenticated != true) {
                            return;
                          }
                          await cartCubit.setAuthenticated(true);
                          await wishlistCubit.setAuthenticated(true);
                          await cartCubit.synchronizeGuestCart();
                          await wishlistCubit.synchronizeGuestWishlist();
                        }
                        if (!context.mounted) return;

                        final checkoutIds =
                            cartCubit.state.selectedCartItemIdsForCheckout;
                        if (checkoutIds != null && checkoutIds.isEmpty) {
                          context.showAppSnackBar(
                            'تعذر تجهيز المنتجات المحددة. حدّث السلة أو أعد الاختيار.',
                            type: AppSnackBarType.error,
                          );
                          return;
                        }
                        if (!cartCubit.state.hasSelection) {
                          context.showAppSnackBar(
                            'يرجى اختيار منتج واحد على الأقل.',
                            type: AppSnackBarType.error,
                          );
                          return;
                        }

                        context.read<AppShellCubit>().openCheckout();
                      },
              ),
              SizedBox(height: 16.h),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSummaryRow(
    String label,
    String value, {
    Color? valueColor,
    bool isTotal = false,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: isTotal ? 14.sp : 11.5.sp,
              fontWeight: isTotal ? FontWeight.w800 : FontWeight.w500,
              color: isTotal ? AppColors.primaryDark : AppColors.textSecondary,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isTotal ? 16.sp : 12.sp,
            fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
            color: valueColor ??
                (isTotal ? AppColors.primaryDark : AppColors.textPrimary),
          ),
        ),
      ],
    );
  }
}

class _SelectionToolbar extends StatelessWidget {
  final CartState state;

  const _SelectionToolbar({required this.state});

  @override
  Widget build(BuildContext context) {
    final allSelected = state.isAllSelected;
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(14.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => context.read<CartCubit>().toggleSelectAll(),
            behavior: HitTestBehavior.opaque,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  width: 22.w,
                  height: 22.w,
                  decoration: BoxDecoration(
                    color: allSelected
                        ? AppColors.primaryDark
                        : (state.hasSelection
                            ? AppColors.primaryDark.withValues(alpha: 0.12)
                            : Colors.transparent),
                    borderRadius: BorderRadius.circular(6.r),
                    border: Border.all(
                      color: (allSelected || state.hasSelection)
                          ? AppColors.primaryDark
                          : AppColors.borderDark,
                      width: 1.5,
                    ),
                  ),
                  child: allSelected
                      ? Icon(Icons.check_rounded,
                          size: 14.w, color: Colors.white)
                      : (state.hasSelection
                          ? Icon(Icons.remove_rounded,
                              size: 14.w, color: AppColors.primaryDark)
                          : null),
                ),
                SizedBox(width: 8.w),
                Text(
                  allSelected ? 'إلغاء التحديد' : 'تحديد الكل',
                  style: TextStyle(
                    fontSize: 12.sp,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                ),
              ],
            ),
          ),
          const Spacer(),
          Text(
            state.hasSelection
                ? 'محدد ${state.selectedLinesCount} من ${state.items.length}'
                : 'لم يُحدد أي منتج',
            style: TextStyle(
              fontSize: 11.sp,
              fontWeight: FontWeight.w600,
              color: state.hasSelection
                  ? AppColors.textSecondary
                  : AppColors.error,
            ),
          ),
        ],
      ),
    );
  }
}
