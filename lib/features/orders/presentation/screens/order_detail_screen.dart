import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/utils/extensions.dart';
import '../../../../core/widgets/cached_image_widget.dart';
import '../../../../core/widgets/skeletons/skeletons.dart';
import '../../../../data/models/orders/order_detail_model.dart';
import '../../../../data/models/orders/order_status_mapper.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';
import 'package:store/features/support_chat/presentation/screens/support_chat_inbox_screen.dart';
import '../widgets/order_rating_sheet.dart';
import 'order_tracking_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  final bool openRating;

  const OrderDetailScreen({
    super.key,
    required this.orderId,
    this.openRating = false,
  });

  static void open(
    BuildContext context,
    int orderId, {
    bool openRating = false,
  }) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderDetailScreen(
          orderId: orderId,
          openRating: openRating,
        ),
      ),
    );
  }

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  bool _ratingFlowStarted = false;

  @override
  void initState() {
    super.initState();
    context.read<OrdersCubit>().fetchOrderDetail(widget.orderId);
  }

  Future<void> _maybeStartRatingFlow(OrderDetailModel order) async {
    if (!widget.openRating || _ratingFlowStarted) return;
    _ratingFlowStarted = true;

    // Allow the first frame of order detail to paint before opening sheets.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    if (!mounted) return;

    var current = order;
    if (current.canConfirmDelivery) {
      final updated =
          await context.read<OrdersCubit>().confirmDelivery(current.id);
      if (!mounted) return;
      if (updated != null) {
        current = updated;
        context.showAppSnackBar(
          'تم تأكيد استلام الطلب بنجاح',
          type: AppSnackBarType.success,
        );
      }
    }

    if (!mounted) return;
    if (current.canRate) {
      await OrderRatingSheet.show(context, current);
      if (mounted) {
        context.read<OrdersCubit>().fetchOrderDetail(widget.orderId);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'تفاصيل الطلب',
          style: AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      body: BlocConsumer<OrdersCubit, OrdersState>(
        listenWhen: (previous, current) =>
            widget.openRating &&
            !_ratingFlowStarted &&
            current.selectedOrderDetail?.id == widget.orderId &&
            !current.isLoadingDetail,
        listener: (context, state) {
          final order = state.selectedOrderDetail;
          if (order != null) {
            unawaited(_maybeStartRatingFlow(order));
          }
        },
        builder: (context, state) {
          if (state.isLoadingDetail && state.selectedOrderDetail == null) {
            return const OrderDetailSkeleton();
          }

          if (state.detailFailure != null && state.selectedOrderDetail == null) {
            return Center(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 24.w),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.error_outline_rounded,
                      size: 48.sp,
                      color: AppColors.error,
                    ),
                    SizedBox(height: 12.h),
                    Text(
                      state.errorMessage ?? 'تعذر تحميل تفاصيل الطلب.',
                      textAlign: TextAlign.center,
                      style: AppTypography.bodyMedium,
                    ),
                    SizedBox(height: 16.h),
                    ElevatedButton.icon(
                      onPressed: () => context
                          .read<OrdersCubit>()
                          .fetchOrderDetail(widget.orderId),
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('إعادة المحاولة'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primaryDark,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            );
          }

          final order = state.selectedOrderDetail;
          if (order == null) {
            return const SizedBox.shrink();
          }

          final statusInfo = OrderStatusMapper.getInfo(order.statusCode);
          final createdDateStr = order.createdAt != null
              ? DateFormat('d MMMM yyyy • h:mm a', 'ar').format(order.createdAt!)
              : null;
          final confirmedDateStr = order.confirmedAt != null
              ? DateFormat('d MMMM yyyy • h:mm a', 'ar')
                  .format(order.confirmedAt!)
              : null;

          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () =>
                context.read<OrdersCubit>().fetchOrderDetail(widget.orderId),
            child: ListView(
              padding: EdgeInsets.fromLTRB(
                16.w,
                14.h,
                16.w,
                24.h + MediaQuery.paddingOf(context).bottom,
              ),
              children: [
                // Top Header Card (Order Number & Status)
                Container(
                  padding: EdgeInsets.all(16.w),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20.r),
                    border: Border.all(color: AppColors.border, width: 0.8),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'رقم الطلب المرجعي',
                                  style: AppTypography.labelSmall.copyWith(
                                    color: AppColors.textMuted,
                                  ),
                                ),
                                SizedBox(height: 2.h),
                                Row(
                                  children: [
                                    Flexible(
                                      child: Text(
                                        order.orderNumber,
                                        style: AppTypography.titleMedium.copyWith(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.primaryDark,
                                        ),
                                      ),
                                    ),
                                    IconButton(
                                      icon: Icon(
                                        Icons.copy_rounded,
                                        size: 16.sp,
                                        color: AppColors.textMuted,
                                      ),
                                      visualDensity: VisualDensity.compact,
                                      padding: EdgeInsets.zero,
                                      constraints: const BoxConstraints(),
                                      onPressed: () {
                                        Clipboard.setData(
                                          ClipboardData(text: order.orderNumber),
                                        );
                                        context.showAppSnackBar(
                                          'تم نسخ رقم الطلب إلى الحافظة',
                                          type: AppSnackBarType.success,
                                        );
                                      },
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: EdgeInsets.symmetric(
                              horizontal: 12.w,
                              vertical: 6.h,
                            ),
                            decoration: BoxDecoration(
                              color: statusInfo.backgroundColor,
                              borderRadius: BorderRadius.circular(12.r),
                              border: Border.all(
                                color: statusInfo.color.withValues(alpha: 0.3),
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  statusInfo.icon,
                                  size: 15.sp,
                                  color: statusInfo.color,
                                ),
                                SizedBox(width: 5.w),
                                Text(
                                  order.statusLabel,
                                  style: AppTypography.labelSmall.copyWith(
                                    color: statusInfo.color,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      if (createdDateStr != null) ...[
                        SizedBox(height: 8.h),
                        Row(
                          children: [
                            Icon(
                              Icons.access_time_rounded,
                              size: 14.sp,
                              color: AppColors.textMuted,
                            ),
                            SizedBox(width: 4.w),
                            Expanded(
                              child: Text(
                                'تاريخ الإنشاء: $createdDateStr',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.labelSmall.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      if (confirmedDateStr != null) ...[
                        SizedBox(height: 4.h),
                        Row(
                          children: [
                            Icon(
                              Icons.check_circle_outline_rounded,
                              size: 14.sp,
                              color: AppColors.success,
                            ),
                            SizedBox(width: 4.w),
                            Expanded(
                              child: Text(
                                'تاريخ التأكيد: $confirmedDateStr',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.labelSmall.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      SizedBox(height: 14.h),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) =>
                                    OrderTrackingScreen(orderId: order.id),
                              ),
                            );
                          },
                          icon: const Icon(Icons.local_shipping_outlined),
                          label: const Text('تتبع الطلب'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primaryDark,
                            foregroundColor: Colors.white,
                            padding: EdgeInsets.symmetric(vertical: 12.h),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14.r),
                            ),
                          ),
                        ),
                      ),
                      SizedBox(height: 10.h),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: state.isReordering
                              ? null
                              : () => _handleReorder(context, order.id),
                          icon: state.isReordering
                              ? SizedBox(
                                  width: 16.w,
                                  height: 16.w,
                                  child: const CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.replay_rounded),
                          label: Text(
                            state.isReordering
                                ? 'جاري إعادة الطلب...'
                                : 'إعادة الطلب',
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.primaryDark,
                            side: BorderSide(color: AppColors.primaryDark),
                            padding: EdgeInsets.symmetric(vertical: 12.h),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14.r),
                            ),
                          ),
                        ),
                      ),
                      if (order.canConfirmDelivery) ...[
                        SizedBox(height: 10.h),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: state.isLoadingDetail
                                ? null
                                : () => _handleConfirmDelivery(context, order),
                            icon: const Icon(Icons.done_all_rounded),
                            label: const Text('تأكيد استلام الطلب'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.success,
                              foregroundColor: Colors.white,
                              padding: EdgeInsets.symmetric(vertical: 12.h),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14.r),
                              ),
                            ),
                          ),
                        ),
                        if (order.customerConfirmDeadlineAt != null) ...[
                          SizedBox(height: 6.h),
                          Text(
                            'إن لم تؤكد خلال المهلة المحددة سيتم إكمال الطلب تلقائياً.',
                            style: AppTypography.labelSmall.copyWith(
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      ],
                      if (order.canRate) ...[
                        SizedBox(height: 10.h),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: () async {
                              final rated = await OrderRatingSheet.show(
                                context,
                                order,
                              );
                              if (rated == true && context.mounted) {
                                context
                                    .read<OrdersCubit>()
                                    .fetchOrderDetail(order.id);
                              }
                            },
                            icon: const Icon(Icons.star_rate_rounded),
                            label: const Text('تقييم الأصناف والمندوب'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.goldDark,
                              side: BorderSide(color: AppColors.gold),
                              padding: EdgeInsets.symmetric(vertical: 12.h),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14.r),
                              ),
                            ),
                          ),
                        ),
                      ],
                      SizedBox(height: 10.h),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () {
                            final ref = order.orderNumber.trim();
                            final seed = ref.isNotEmpty
                                ? 'أحتاج مساعدة بخصوص الطلب رقم $ref'
                                : 'أحتاج مساعدة بخصوص طلبي رقم ${order.id}';
                            SupportChatInboxScreen.open(
                              context,
                              preferDirectThread: true,
                              relatedOrderId: order.id,
                              seedText: seed,
                            );
                          },
                          icon: const Icon(Icons.support_agent_rounded),
                          label: const Text('اسأل الدعم عن هذا الطلب'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.primaryDark,
                            side: BorderSide(color: AppColors.primaryDark),
                            padding: EdgeInsets.symmetric(vertical: 12.h),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14.r),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                SizedBox(height: 14.h),

                // Delivery Address Card
                if (order.deliveryAddress != null) ...[
                  _buildSectionCard(
                    title: 'عنوان التوصيل',
                    icon: Icons.location_on_outlined,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (order.deliveryAddress!.contactName != null) ...[
                          Text(
                            order.deliveryAddress!.contactName!,
                            style: AppTypography.bodyMedium.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (order.deliveryAddress!.contactPhone != null) ...[
                            SizedBox(height: 2.h),
                            Text(
                              order.deliveryAddress!.contactPhone!,
                              style: AppTypography.bodySmall.copyWith(
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                          SizedBox(height: 6.h),
                        ],
                        Text(
                          order.deliveryAddress!.formattedAddress ?? '',
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  SizedBox(height: 14.h),
                ],

                // Payment & Shipping Method Card
                _buildSectionCard(
                  title: 'تفاصيل الشحن والدفع',
                  icon: Icons.payment_rounded,
                  child: Row(
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
                                color: AppColors.textPrimary,
                              ),
                            ),
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
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                // Gift Wrapping Card (if requested)
                if (order.giftWrapping.requested) ...[
                  SizedBox(height: 14.h),
                  _buildSectionCard(
                    title: 'تغليف الهدايا',
                    icon: Icons.card_giftcard_rounded,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'تغليف هدية فاخر مع بطاقة إهداء مخصصة',
                          style: AppTypography.bodySmall.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.primaryDark,
                          ),
                        ),
                        if (order.giftWrapping.message != null &&
                            order.giftWrapping.message!.trim().isNotEmpty) ...[
                          SizedBox(height: 6.h),
                          Container(
                            width: double.infinity,
                            padding: EdgeInsets.all(10.w),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceSoft,
                              borderRadius: BorderRadius.circular(10.r),
                            ),
                            child: Text(
                              '"${order.giftWrapping.message}"',
                              style: AppTypography.bodySmall.copyWith(
                                fontStyle: FontStyle.italic,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],

                SizedBox(height: 14.h),

                // Order Items Card (Historical snapshot)
                _buildSectionCard(
                  title: 'المنتجات المطلوبة (${order.items.length})',
                  icon: Icons.shopping_bag_outlined,
                  child: Column(
                    children: [
                      ...order.items.map((item) => _buildItemRow(item, order.currencySymbol)),
                    ],
                  ),
                ),

                SizedBox(height: 14.h),

                // Pricing Card
                _buildSectionCard(
                  title: 'ملخص الحساب والفاتورة',
                  icon: Icons.receipt_long_rounded,
                  child: Column(
                    children: [
                      _buildPriceRow(
                        'المجموع الفرعي',
                        '${order.pricing.subtotal.toStringAsFixed(0)} ${order.currencySymbol}',
                      ),
                      if (order.pricing.discountAmount > 0) ...[
                        SizedBox(height: 6.h),
                        _buildPriceRow(
                          'قيمة الخصم',
                          '- ${order.pricing.discountAmount.toStringAsFixed(0)} ${order.currencySymbol}',
                          isDiscount: true,
                        ),
                      ],
                      SizedBox(height: 6.h),
                      _buildPriceRow(
                        'رسوم التوصيل',
                        order.pricing.deliveryFee == 0
                            ? 'مجاناً'
                            : '${order.pricing.deliveryFee.toStringAsFixed(0)} ${order.currencySymbol}',
                      ),
                      Divider(color: AppColors.border, height: 20.h),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'المبلغ الإجمالي',
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
                              '${order.pricing.total.toStringAsFixed(0)} ${order.currencySymbol}',
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

                SizedBox(height: 24.h),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildSectionCard({
    required String title,
    required IconData icon,
    required Widget child,
  }) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18.sp, color: AppColors.primaryDark),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.labelMedium.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          Divider(color: AppColors.border, height: 20.h),
          child,
        ],
      ),
    );
  }

  Widget _buildItemRow(OrderDetailItemModel item, String currencySymbol) {
    final hasDiscount = item.originalUnitPrice > item.unitPrice;
    final imageUrl = item.primaryImageUrl?.trim() ?? '';

    return Padding(
      padding: EdgeInsets.symmetric(vertical: 8.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10.r),
            child: SizedBox(
              width: 52.w,
              height: 52.w,
              child: imageUrl.isNotEmpty
                  ? CachedImageWidget(
                      imageUrl: imageUrl,
                      width: 52.w,
                      height: 52.w,
                      fit: BoxFit.cover,
                    )
                  : ColoredBox(
                      color: AppColors.surfaceSoft,
                      child: Icon(
                        Icons.image_outlined,
                        color: AppColors.textSecondary,
                        size: 22.sp,
                      ),
                    ),
            ),
          ),
          SizedBox(width: 10.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.productName,
                  style: AppTypography.bodySmall.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (item.variantName.isNotEmpty) ...[
                  SizedBox(height: 2.h),
                  Text(
                    item.variantName,
                    style: AppTypography.labelSmall.copyWith(
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
                SizedBox(height: 4.h),
                Text(
                  '${item.quantity.toInt()} × ${item.unitPrice.toStringAsFixed(0)} $currencySymbol',
                  style: AppTypography.labelSmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 12.w),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '${item.lineTotal.toStringAsFixed(0)} $currencySymbol',
                style: AppTypography.bodyMedium.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primaryDark,
                ),
              ),
              if (hasDiscount) ...[
                SizedBox(height: 2.h),
                Text(
                  '${(item.originalUnitPrice * item.quantity).toStringAsFixed(0)} $currencySymbol',
                  style: AppTypography.labelSmall.copyWith(
                    decoration: TextDecoration.lineThrough,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ],
          ),
        ],
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

  Future<void> _handleReorder(BuildContext context, int orderId) async {
    final report = await context.read<OrdersCubit>().reorder(orderId);
    if (!context.mounted || report == null) return;

    final message = report.message ??
        (report.addedCount > 0
            ? (report.skippedCount > 0
                ? 'تمت إضافة المنتجات المتاحة إلى السلة. بعض المنتجات لم تعد متاحة.'
                : 'تمت إضافة المنتجات المتاحة إلى السلة.')
            : 'تعذر إضافة منتجات هذا الطلب إلى السلة.');

    context.showAppSnackBar(
      message,
      type: report.addedCount > 0
          ? AppSnackBarType.success
          : AppSnackBarType.error,
    );

    if (report.addedCount > 0) {
      try {
        await context.read<CartCubit>().fetchCart();
      } catch (_) {}
    }
  }

  Future<void> _handleConfirmDelivery(
    BuildContext context,
    OrderDetailModel order,
  ) async {
    final updated =
        await context.read<OrdersCubit>().confirmDelivery(order.id);
    if (!context.mounted || updated == null) return;

    context.showAppSnackBar(
      'تم تأكيد استلام الطلب بنجاح',
      type: AppSnackBarType.success,
    );

    if (updated.canRate) {
      await OrderRatingSheet.show(context, updated);
      if (context.mounted) {
        context.read<OrdersCubit>().fetchOrderDetail(order.id);
      }
    }
  }
}
