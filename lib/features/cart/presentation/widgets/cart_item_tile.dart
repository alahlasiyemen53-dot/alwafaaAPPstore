import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/cart_item_model.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';

class CartItemTile extends StatelessWidget {
  final CartItemModel item;
  final int index;
  final bool selected;
  final ValueChanged<bool>? onSelectedChanged;

  const CartItemTile({
    super.key,
    required this.item,
    required this.index,
    this.selected = true,
    this.onSelectedChanged,
  });

  @override
  Widget build(BuildContext context) {
    final currencyState = context.watch<CurrencyCubit>().state;
    final formattedPrice =
        currencyState.formatProductBasePrice(item.totalPriceSAR);
    final variantLabel = item.displaySpecs;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 10.h),
      decoration: BoxDecoration(
        color: selected
            ? AppColors.surface
            : AppColors.surfaceSoft.withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(14.r),
        border: Border.all(
          color: selected ? AppColors.primaryDark : AppColors.border,
          width: selected ? 1.4 : 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          _SelectionCheck(
            selected: selected,
            onChanged: onSelectedChanged,
          ),
          SizedBox(width: 8.w),
          GestureDetector(
            onTap: onSelectedChanged == null
                ? null
                : () => onSelectedChanged!(!selected),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10.r),
              child: SizedBox(
                width: 56.w,
                height: 56.w,
                child: Opacity(
                  opacity: selected ? 1 : 0.55,
                  child: ColoredBox(
                    color: AppColors.surfaceSoft,
                    child: CachedImageWidget(
                      imageUrl: item.product.image,
                      fit: BoxFit.contain,
                    ),
                  ),
                ),
              ),
            ),
          ),
          SizedBox(width: 10.w),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (item.product.brand.trim().isNotEmpty)
                            Text(
                              item.product.brand,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 9.sp,
                                fontWeight: FontWeight.w600,
                                color: AppColors.goldDark,
                                height: 1.2,
                              ),
                            ),
                          Text(
                            item.product.name,
                            style: TextStyle(
                              fontSize: 12.sp,
                              fontWeight: FontWeight.w700,
                              color: selected
                                  ? AppColors.textPrimary
                                  : AppColors.textMuted,
                              height: 1.25,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (variantLabel.isNotEmpty)
                            Text(
                              variantLabel,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 10.sp,
                                color: AppColors.textMuted,
                                height: 1.2,
                              ),
                            ),
                        ],
                      ),
                    ),
                    SizedBox(width: 4.w),
                    GestureDetector(
                      onTap: () => context.read<CartCubit>().removeItem(index),
                      behavior: HitTestBehavior.opaque,
                      child: Padding(
                        padding: EdgeInsets.all(2.w),
                        child: Icon(
                          Icons.delete_outline_rounded,
                          size: 18.w,
                          color: AppColors.error,
                        ),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 6.h),
                Text(
                  formattedPrice,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12.sp,
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                    height: 1.2,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 8.w),
          _QuantityStepper(
            quantity: item.quantity,
            onDecrement: () => context
                .read<CartCubit>()
                .updateQuantity(index, item.quantity - 1),
            onIncrement: () => context
                .read<CartCubit>()
                .updateQuantity(index, item.quantity + 1),
          ),
        ],
      ),
    );
  }
}

class _SelectionCheck extends StatelessWidget {
  final bool selected;
  final ValueChanged<bool>? onChanged;

  const _SelectionCheck({
    required this.selected,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onChanged == null ? null : () => onChanged!(!selected),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 24.w,
        height: 24.w,
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryDark : Colors.transparent,
          borderRadius: BorderRadius.circular(7.r),
          border: Border.all(
            color: selected ? AppColors.primaryDark : AppColors.borderDark,
            width: 1.5,
          ),
        ),
        child: selected
            ? Icon(Icons.check_rounded, size: 16.w, color: Colors.white)
            : null,
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  final int quantity;
  final VoidCallback onDecrement;
  final VoidCallback onIncrement;

  const _QuantityStepper({
    required this.quantity,
    required this.onDecrement,
    required this.onIncrement,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _StepperButton(
            icon: Icons.add_rounded,
            onTap: onIncrement,
            emphasize: true,
          ),
          Padding(
            padding: EdgeInsets.symmetric(vertical: 2.h),
            child: Text(
              '$quantity',
              style: TextStyle(
                fontSize: 13.sp,
                fontWeight: FontWeight.w800,
                height: 1.1,
                color: AppColors.primaryDark,
              ),
            ),
          ),
          _StepperButton(
            icon: Icons.remove_rounded,
            onTap: onDecrement,
          ),
        ],
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool emphasize;

  const _StepperButton({
    required this.icon,
    required this.onTap,
    this.emphasize = false,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: emphasize
          ? AppColors.primaryDark.withValues(alpha: 0.08)
          : Colors.transparent,
      borderRadius: BorderRadius.circular(10.r),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10.r),
        child: SizedBox(
          width: 36.w,
          height: 32.h,
          child: Icon(
            icon,
            size: 20.w,
            color: emphasize ? AppColors.primaryDark : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }
}
