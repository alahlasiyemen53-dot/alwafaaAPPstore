import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/luxury_button.dart';
import '../../../../data/models/checkout/customer_order_model.dart';
import '../../../app_shell/cubit/app_shell_cubit.dart';
import '../../cubit/checkout_cubit.dart';
import '../../../orders/presentation/screens/order_detail_screen.dart';

class OrderSuccessScreen extends StatelessWidget {
  final CustomerOrderModel order;

  const OrderSuccessScreen({
    super.key,
    required this.order,
  });

  void _closeCheckout(BuildContext context) {
    context.read<CheckoutCubit>().reset();
    context.read<AppShellCubit>().closeCheckout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(
          'تم تأكيد الطلب',
          style: AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.close_rounded),
            onPressed: () {
              _closeCheckout(context);
              context.read<AppShellCubit>().setTab(0);
            },
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 16.h),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              SizedBox(height: 12.h),

              // Success Icon Animation / Container
              Container(
                width: 80.w,
                height: 80.w,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.success.withValues(alpha: 0.3),
                    width: 2,
                  ),
                ),
                child: Center(
                  child: Icon(
                    Icons.check_circle_rounded,
                    size: 46.sp,
                    color: AppColors.success,
                  ),
                ),
              ),

              SizedBox(height: 16.h),

              Text(
                'شكراً لتسوقك معنا!',
                style: AppTypography.titleLarge.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),

              SizedBox(height: 6.h),

              Text(
                'تم استلام وتأكيد طلبك بنجاح وجاري تجهيزه للتوصيل.',
                textAlign: TextAlign.center,
                style: AppTypography.bodyMedium.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),

              SizedBox(height: 24.h),

              // Order Details Card
              Container(
                width: double.infinity,
                padding: EdgeInsets.all(18.w),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20.r),
                  border: Border.all(color: AppColors.border, width: 0.8),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Order Number & Status
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'رقم الطلب',
                                style: AppTypography.labelSmall.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                              SizedBox(height: 2.h),
                              Text(
                                order.orderNumber,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.titleSmall.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primaryDark,
                                ),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: 12.w,
                            vertical: 6.h,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(12.r),
                            border: Border.all(
                              color: AppColors.success.withValues(alpha: 0.3),
                            ),
                          ),
                          child: Text(
                            order.statusLabel,
                            style: AppTypography.labelMedium.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.success,
                            ),
                          ),
                        ),
                      ],
                    ),

                    Divider(color: AppColors.border, height: 24.h),

                    // Delivery Address Snapshot
                    if (order.deliveryAddress != null) ...[
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 20.sp,
                            color: AppColors.primaryDark,
                          ),
                          SizedBox(width: 8.w),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'عنوان التوصيل',
                                  style: AppTypography.labelMedium.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                SizedBox(height: 2.h),
                                Text(
                                  order.deliveryAddress!.formattedAddress ??
                                      [
                                        order.deliveryAddress!.governorate,
                                        order.deliveryAddress!.street,
                                      ]
                                          .where((part) =>
                                              part != null &&
                                              part.trim().isNotEmpty)
                                          .join('، '),
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                if (order.deliveryAddress!.contactName != null) ...[
                                  SizedBox(height: 2.h),
                                  Text(
                                    'المستلم: ${order.deliveryAddress!.contactName!} (${order.deliveryAddress!.contactPhone ?? ''})',
                                    style: AppTypography.bodySmall.copyWith(
                                      color: AppColors.textMuted,
                                      fontSize: 11.sp,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                      Divider(color: AppColors.border, height: 24.h),
                    ],

                    // Payment Method & Shipping Method
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'طريقة الدفع',
                                style: AppTypography.labelSmall.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                              SizedBox(height: 2.h),
                              Text(
                                order.paymentMethodLabel,
                                style: AppTypography.bodySmall.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if (order.paymentStatus != null &&
                                  order.paymentStatus!.isNotEmpty) ...[
                                SizedBox(height: 2.h),
                                Text(
                                  order.paymentStatus == 'paid'
                                      ? 'مدفوع'
                                      : order.paymentStatus == 'unpaid'
                                          ? 'غير مدفوع'
                                          : order.paymentStatus!,
                                  style: AppTypography.labelSmall.copyWith(
                                    color: order.paymentStatus == 'paid'
                                        ? AppColors.success
                                        : AppColors.textMuted,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'طريقة التوصيل',
                                style: AppTypography.labelSmall.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                              SizedBox(height: 2.h),
                              Text(
                                order.shippingMethodLabel,
                                style: AppTypography.bodySmall.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    if (order.giftWrappingRequested) ...[
                      SizedBox(height: 12.h),
                      Row(
                        children: [
                          Icon(
                            Icons.card_giftcard_rounded,
                            size: 18.sp,
                            color: AppColors.primaryDark,
                          ),
                          SizedBox(width: 8.w),
                          Text(
                            'تغليف هدية فاخر (مجاناً)',
                            style: AppTypography.bodySmall.copyWith(
                              fontWeight: FontWeight.w600,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                      if (order.giftMessage != null &&
                          order.giftMessage!.trim().isNotEmpty) ...[
                        Padding(
                          padding: EdgeInsets.only(right: 26.w, top: 4.h),
                          child: Text(
                            '"${order.giftMessage}"',
                            style: AppTypography.bodySmall.copyWith(
                              fontStyle: FontStyle.italic,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ],

                    Divider(color: AppColors.border, height: 24.h),

                    // Items list summary
                    Text(
                      'المنتجات (${order.items.length})',
                      style: AppTypography.labelMedium.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 8.h),
                    ...order.items.map((item) => Padding(
                          padding: EdgeInsets.symmetric(vertical: 4.h),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  '${item.quantity.toInt()} × ${item.productName} (${item.variantName})',
                                  style: AppTypography.bodySmall,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              SizedBox(width: 8.w),
                              Text(
                                '${item.lineTotal.toStringAsFixed(0)} ${order.currency.symbol}',
                                style: AppTypography.bodySmall.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        )),

                    Divider(color: AppColors.border, height: 24.h),

                    // Pricing Totals
                    _buildPriceRow(
                      'المجموع الفرعي',
                      '${order.pricing.subtotal.toStringAsFixed(0)} ${order.currency.symbol}',
                    ),
                    if (order.pricing.discountAmount > 0) ...[
                      SizedBox(height: 6.h),
                      _buildPriceRow(
                        'إجمالي الخصم',
                        '- ${order.pricing.discountAmount.toStringAsFixed(0)} ${order.currency.symbol}',
                        isDiscount: true,
                      ),
                    ],
                    SizedBox(height: 6.h),
                    _buildPriceRow(
                      'رسوم التوصيل',
                      order.pricing.deliveryFee == 0
                          ? 'مجاناً'
                          : '${order.pricing.deliveryFee.toStringAsFixed(0)} ${order.currency.symbol}',
                    ),
                    Divider(color: AppColors.border, height: 16.h),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'المبلغ الإجمالي للدفع',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.titleSmall.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Flexible(
                          child: Text(
                            '${order.pricing.total.toStringAsFixed(0)} ${order.currency.symbol}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.end,
                            style: AppTypography.titleMedium.copyWith(
                              fontWeight: FontWeight.w900,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              SizedBox(height: 28.h),

              // Action Buttons
              LuxuryButton(
                text: 'عرض تفاصيل الطلب والتتبع',
                width: double.infinity,
                onPressed: () {
                  _closeCheckout(context);
                  OrderDetailScreen.open(context, order.id);
                },
              ),

              SizedBox(height: 12.h),

              OutlinedButton(
                onPressed: () {
                  _closeCheckout(context);
                  context.read<AppShellCubit>().setTab(0);
                },
                style: OutlinedButton.styleFrom(
                  minimumSize: Size(double.infinity, 48.h),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16.r),
                  ),
                  side: const BorderSide(color: AppColors.border),
                ),
                child: Text(
                  'متابعة التسوق',
                  style: AppTypography.labelLarge.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),

              SizedBox(height: 12.h),

              TextButton(
                onPressed: () {
                  _closeCheckout(context);
                  context.read<AppShellCubit>().setTab(4);
                },
                child: Text(
                  'الذهاب إلى حسابي',
                  style: AppTypography.labelMedium.copyWith(
                    color: AppColors.textMuted,
                  ),
                ),
              ),

              SizedBox(height: 20.h),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPriceRow(String label, String value, {bool isDiscount = false}) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ),
        SizedBox(width: 8.w),
        Flexible(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.end,
            style: AppTypography.bodySmall.copyWith(
              fontWeight: FontWeight.w600,
              color: isDiscount ? AppColors.discountBadge : AppColors.textPrimary,
            ),
          ),
        ),
      ],
    );
  }
}
