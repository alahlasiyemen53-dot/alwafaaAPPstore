import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/data/models/orders/order_detail_model.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';

class OrderRatingSheet extends StatefulWidget {
  final OrderDetailModel order;

  const OrderRatingSheet({super.key, required this.order});

  static Future<bool?> show(BuildContext context, OrderDetailModel order) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider.value(
        value: context.read<OrdersCubit>(),
        child: OrderRatingSheet(order: order),
      ),
    );
  }

  @override
  State<OrderRatingSheet> createState() => _OrderRatingSheetState();
}

class _OrderRatingSheetState extends State<OrderRatingSheet> {
  late final Map<int, int> _productRatings;
  int _driverRating = 5;
  final _driverCommentController = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _productRatings = {
      for (final item in widget.order.items)
        if (item.productId != null) item.productId!: 5,
    };
  }

  @override
  void dispose() {
    _driverCommentController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final products = _productRatings.entries
        .map((e) => {'product_id': e.key, 'rating': e.value})
        .toList();
    final ok = await context.read<OrdersCubit>().submitOrderRatings(
          orderId: widget.order.id,
          products: products,
          driverRating: _driverRating,
          driverComment: _driverCommentController.text.trim().isEmpty
              ? null
              : _driverCommentController.text.trim(),
        );
    if (!mounted) return;
    setState(() => _submitting = false);
    if (ok) {
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('شكراً لتقييمك')),
      );
    }
  }

  Widget _stars({
    required int value,
    required ValueChanged<int> onChanged,
  }) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(5, (i) {
        final star = i + 1;
        return IconButton(
          onPressed: () => onChanged(star),
          visualDensity: VisualDensity.compact,
          padding: EdgeInsets.zero,
          constraints: BoxConstraints(minWidth: 28.w, minHeight: 28.w),
          icon: Icon(
            star <= value ? Icons.star_rounded : Icons.star_border_rounded,
            color: star <= value
                ? AppColors.ratingStar
                : AppColors.ratingStarEmpty,
            size: 26.w,
          ),
        );
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    final systemBottom =
        MediaQueryData.fromView(View.of(context)).viewPadding.bottom;
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.85,
      ),
      padding: EdgeInsets.fromLTRB(
        20.w,
        16.h,
        20.w,
        20.h + keyboard + (keyboard > 0 ? 0 : systemBottom),
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24.r)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 44.w,
              height: 4.h,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(4.r),
              ),
            ),
          ),
          SizedBox(height: 14.h),
          Text(
            'قيّم طلبك',
            style: AppTypography.titleLarge.copyWith(
              fontWeight: FontWeight.w800,
              color: AppColors.primaryDark,
            ),
          ),
          SizedBox(height: 4.h),
          Text(
            'تقييم الأصناف ومندوب التوصيل يساعدنا على تحسين الخدمة',
            style: AppTypography.bodySmall.copyWith(color: AppColors.textMuted),
          ),
          SizedBox(height: 14.h),
          Expanded(
            child: ListView(
              children: [
                ...widget.order.items.map((item) {
                  final productId = item.productId;
                  if (productId == null) return const SizedBox.shrink();
                  return Padding(
                    padding: EdgeInsets.only(bottom: 12.h),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.productName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.bodySmall
                                .copyWith(fontWeight: FontWeight.w700),
                          ),
                        ),
                        _stars(
                          value: _productRatings[productId] ?? 5,
                          onChanged: (v) =>
                              setState(() => _productRatings[productId] = v),
                        ),
                      ],
                    ),
                  );
                }),
                Divider(height: 24.h, color: AppColors.border),
                Text(
                  'تقييم مندوب التوصيل',
                  style: AppTypography.titleSmall
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 8.h),
                _stars(
                  value: _driverRating,
                  onChanged: (v) => setState(() => _driverRating = v),
                ),
                SizedBox(height: 10.h),
                TextField(
                  controller: _driverCommentController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    hintText: 'ملاحظة اختيارية عن المندوب',
                    filled: true,
                    fillColor: AppColors.surfaceSoft,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: 12.h),
          SizedBox(
            width: double.infinity,
            height: 48.h,
            child: ElevatedButton(
              onPressed: _submitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryDark,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14.r),
                ),
              ),
              child: _submitting
                  ? SizedBox(
                      width: 20.w,
                      height: 20.w,
                      child: const CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text(
                      'إرسال التقييم',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
