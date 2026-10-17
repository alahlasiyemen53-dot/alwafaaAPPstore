import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/utils/extensions.dart';
import '../../../../core/widgets/skeletons/skeletons.dart';
import '../../../../data/models/orders/order_status_mapper.dart';
import '../../../../data/models/orders/order_tracking_model.dart';
import '../../../app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';

class OrderTrackingScreen extends StatefulWidget {
  final int? orderId;

  const OrderTrackingScreen({
    super.key,
    this.orderId,
  });

  static void open(BuildContext context, int orderId) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderTrackingScreen(orderId: orderId),
      ),
    );
  }

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  int? _resolvedOrderId;

  @override
  void initState() {
    super.initState();
    _resolveAndFetch();
  }

  void _resolveAndFetch() {
    if (widget.orderId != null) {
      _resolvedOrderId = widget.orderId;
      context.read<OrdersCubit>().fetchOrderTracking(widget.orderId!);
      return;
    }

    final cubit = context.read<OrdersCubit>();
    if (cubit.state.selectedOrderDetail != null) {
      _resolvedOrderId = cubit.state.selectedOrderDetail!.id;
      cubit.fetchOrderTracking(_resolvedOrderId!);
    } else if (cubit.state.orders.isNotEmpty) {
      _resolvedOrderId = cubit.state.orders.first.id;
      cubit.fetchOrderTracking(_resolvedOrderId!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: () {
            if (Navigator.of(context).canPop()) {
              Navigator.of(context).pop();
            } else {
              context.read<AppShellCubit>().closeOrderTracking();
            }
          },
        ),
        title: Text(
          AppStrings.trackingOrder,
          style: AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      body: BlocBuilder<OrdersCubit, OrdersState>(
        builder: (context, state) {
          if (state.isLoadingTracking && state.selectedTracking == null) {
            return const OrderDetailSkeleton();
          }

          if (state.trackingFailure != null && state.selectedTracking == null) {
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
                      state.errorMessage ?? 'تعذر تحميل بيانات التتبع.',
                      textAlign: TextAlign.center,
                      style: AppTypography.bodyMedium,
                    ),
                    SizedBox(height: 16.h),
                    if (_resolvedOrderId != null)
                      ElevatedButton.icon(
                        onPressed: () => context
                            .read<OrdersCubit>()
                            .fetchOrderTracking(_resolvedOrderId!),
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

          final tracking = state.selectedTracking;
          if (tracking == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 72.w,
                    height: 72.w,
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.local_shipping_outlined,
                      size: 36.sp,
                      color: AppColors.textMuted,
                    ),
                  ),
                  SizedBox(height: 16.h),
                  Text(
                    'لا توجد معلومات تتبع متاحة',
                    style: AppTypography.titleSmall.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  SizedBox(height: 6.h),
                  Text(
                    'اختر طلباً من قائمة طلباتي لعرض مسار الشحنة.',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            );
          }

          final currentStatus = OrderStatusMapper.getInfo(tracking.statusCode);

          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () async {
              if (_resolvedOrderId != null) {
                await context
                    .read<OrdersCubit>()
                    .fetchOrderTracking(_resolvedOrderId!);
              }
            },
            child: ListView(
              padding: EdgeInsets.fromLTRB(
                16.w,
                14.h,
                16.w,
                24.h + MediaQuery.paddingOf(context).bottom,
              ),
              children: [
                // Current Status Header Card
                Container(
                  padding: EdgeInsets.all(18.w),
                  decoration: BoxDecoration(
                    color: AppColors.primaryDark,
                    borderRadius: BorderRadius.circular(24.r),
                    border: Border.all(color: AppColors.primaryLight, width: 1.2),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primaryDark.withValues(alpha: 0.15),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  tracking.statusLabel,
                                  style: TextStyle(
                                    fontSize: 16.sp,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.textLight,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                SizedBox(height: 4.h),
                                Text(
                                  'رقم الطلب: ${tracking.orderNumber}',
                                  style: TextStyle(
                                    fontSize: 11.sp,
                                    color: AppColors.gold,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                          SizedBox(width: 8.w),
                          Container(
                            padding: EdgeInsets.all(10.w),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.12),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              currentStatus.icon,
                              color: AppColors.gold,
                              size: 24.sp,
                            ),
                          ),
                        ],
                      ),
                      if (tracking.occurredAt != null) ...[
                        Divider(
                          color: Colors.white.withValues(alpha: 0.15),
                          height: 22.h,
                        ),
                        Row(
                          children: [
                            Icon(
                              Icons.access_time_rounded,
                              size: 13.sp,
                              color: AppColors.goldLight,
                            ),
                            SizedBox(width: 6.w),
                            Expanded(
                              child: Text(
                                'آخر تحديث: ${DateFormat('d MMMM yyyy • h:mm a', 'ar').format(tracking.occurredAt!)}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 10.5.sp,
                                  color: AppColors.goldLight,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),

                SizedBox(height: 16.h),

                // Order Reference Info Box with Copy Button
                Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 16.w,
                    vertical: 12.h,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18.r),
                    border: Border.all(color: AppColors.border, width: 0.8),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'رقم الطلب',
                              style: TextStyle(
                                fontSize: 10.sp,
                                color: AppColors.textMuted,
                              ),
                            ),
                            SizedBox(height: 2.h),
                            Text(
                              tracking.orderNumber,
                              style: TextStyle(
                                fontSize: 13.sp,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primaryDark,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      SizedBox(width: 8.w),
                      ElevatedButton.icon(
                        onPressed: () {
                          Clipboard.setData(
                            ClipboardData(text: tracking.orderNumber),
                          );
                          context.showAppSnackBar(
                            'تم نسخ رقم الطلب إلى الحافظة',
                            type: AppSnackBarType.success,
                          );
                        },
                        icon: Icon(Icons.copy_rounded, size: 14.sp),
                        label: Text(
                          AppStrings.copyTracking,
                          style: TextStyle(fontSize: 10.5.sp),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.surfaceMuted,
                          foregroundColor: AppColors.primaryDark,
                          padding: EdgeInsets.symmetric(
                            horizontal: 10.w,
                            vertical: 6.h,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10.r),
                          ),
                          elevation: 0,
                        ),
                      ),
                    ],
                  ),
                ),

                SizedBox(height: 16.h),

                // Authoritative Timeline Card
                Container(
                  padding: EdgeInsets.all(16.w),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20.r),
                    border: Border.all(color: AppColors.border, width: 0.8),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(
                            Icons.timeline_rounded,
                            size: 18.sp,
                            color: AppColors.primaryDark,
                          ),
                          SizedBox(width: 8.w),
                          Text(
                            'سجل تتبع مراحل الشحنة',
                            style: AppTypography.titleSmall.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                      SizedBox(height: 16.h),
                      if (tracking.timeline.isEmpty)
                        Padding(
                          padding: EdgeInsets.symmetric(vertical: 12.h),
                          child: Text(
                            'لا توجد أحداث سابقة مسجلة للشحنة حتى الآن.',
                            style: AppTypography.bodySmall.copyWith(
                              color: AppColors.textMuted,
                            ),
                          ),
                        )
                      else
                        ...() {
                          final events = tracking.timeline.reversed.toList();
                          return List.generate(events.length, (index) {
                            final event = events[index];
                            final isLast = index == events.length - 1;
                            // Oldest first → completed history above; latest stage at bottom.
                            final isCurrent = isLast;
                            return _buildTimelineStep(
                              event: event,
                              isCurrent: isCurrent,
                              isCompleted: true,
                              isLast: isLast,
                            );
                          });
                        }(),
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

  Widget _buildTimelineStep({
    required OrderTrackingEventModel event,
    required bool isCurrent,
    required bool isCompleted,
    required bool isLast,
  }) {
    final statusInfo = OrderStatusMapper.getInfo(event.statusCode);
    final dateStr = event.occurredAt != null
        ? DateFormat('d MMMM yyyy • h:mm a', 'ar').format(event.occurredAt!)
        : null;
    final highlight = isCurrent || isCompleted;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              width: 22.w,
              height: 22.w,
              decoration: BoxDecoration(
                color: highlight
                    ? AppColors.primaryDark
                    : statusInfo.backgroundColor,
                shape: BoxShape.circle,
                border: Border.all(
                  color: highlight ? AppColors.primaryDark : statusInfo.color,
                  width: 1.5,
                ),
              ),
              child: Center(
                child: Icon(
                  highlight ? Icons.check_rounded : statusInfo.icon,
                  color: highlight ? Colors.white : statusInfo.color,
                  size: 12.sp,
                ),
              ),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 36.h,
                color: AppColors.primaryDark.withValues(alpha: 0.35),
              ),
          ],
        ),
        SizedBox(width: 12.w),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                event.statusLabel.isNotEmpty
                    ? event.statusLabel
                    : statusInfo.label,
                style: TextStyle(
                  fontSize: 12.sp,
                  fontWeight: isCurrent ? FontWeight.bold : FontWeight.w600,
                  color: isCurrent
                      ? AppColors.primaryDark
                      : AppColors.textPrimary,
                ),
              ),
              if (dateStr != null) ...[
                SizedBox(height: 2.h),
                Text(
                  dateStr,
                  style: TextStyle(
                    fontSize: 10.sp,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
              SizedBox(height: 14.h),
            ],
          ),
        ),
      ],
    );
  }
}
