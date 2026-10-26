import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_empty_state.dart';
import '../../../../core/widgets/skeletons/skeletons.dart';
import '../../../../data/models/orders/order_status_mapper.dart';
import '../../../../data/models/orders/order_summary_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';
import 'order_detail_screen.dart';
import 'order_tracking_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  static void open(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const OrdersScreen()),
    );
  }

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    context.read<OrdersCubit>().fetchOrders(refresh: true);
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<OrdersCubit>().loadMoreOrders();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          AppStrings.myOrders,
          style: AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      body: BlocBuilder<OrdersCubit, OrdersState>(
        builder: (context, state) {
          if (state.isLoading && state.orders.isEmpty) {
            return const OrdersListSkeleton();
          }

          if (state.status == OrdersStatus.error && state.orders.isEmpty) {
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
                      state.errorMessage ?? 'تعذر تحميل الطلبات.',
                      textAlign: TextAlign.center,
                      style: AppTypography.bodyMedium,
                    ),
                    SizedBox(height: 16.h),
                    ElevatedButton.icon(
                      onPressed: () =>
                          context.read<OrdersCubit>().fetchOrders(refresh: true),
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

          if (state.orders.isEmpty) {
            return AppEmptyState(
              icon: Icons.receipt_long_outlined,
              title: 'لا طلبات بعد',
              message: 'عند إتمام أول طلب ستظهر تفاصيله وتتبعه هنا.',
              actionLabel: 'تسوّق الآن',
              onAction: () {
                Navigator.of(context).pop();
                context.read<AppShellCubit>().setTab(1);
              },
            );
          }

          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () =>
                context.read<OrdersCubit>().fetchOrders(refresh: true),
            child: ListView.separated(
              controller: _scrollController,
              padding: EdgeInsets.fromLTRB(
                16.w,
                14.h,
                16.w,
                14.h + MediaQuery.paddingOf(context).bottom,
              ),
              itemCount: state.orders.length + (state.isLoadingMore ? 2 : 0),
              separatorBuilder: (_, _) => SizedBox(height: 12.h),
              itemBuilder: (context, index) {
                if (index >= state.orders.length) {
                  return const AppShimmer(child: OrderCardSkeleton());
                }

                final order = state.orders[index];
                return _buildOrderCard(context, order);
              },
            ),
          );
        },
      ),
    );
  }

  Widget _buildOrderCard(BuildContext context, OrderSummaryModel order) {
    final statusInfo = OrderStatusMapper.getInfo(order.status);
    final dateStr = order.createdAt != null
        ? DateFormat('d MMMM yyyy • h:mm a', 'ar').format(order.createdAt!)
        : '';

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
          // Order Number & Status Chip
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.orderNumber,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.titleSmall.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    if (dateStr.isNotEmpty) ...[
                      SizedBox(height: 2.h),
                      Text(
                        dateStr,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.labelSmall.copyWith(
                          color: AppColors.textMuted,
                          fontSize: 11.sp,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              SizedBox(width: 8.w),
              Container(
                padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
                decoration: BoxDecoration(
                  color: statusInfo.backgroundColor,
                  borderRadius: BorderRadius.circular(10.r),
                  border: Border.all(
                    color: statusInfo.color.withValues(alpha: 0.3),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(statusInfo.icon, size: 14.sp, color: statusInfo.color),
                    SizedBox(width: 4.w),
                    Text(
                      statusInfo.label,
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

          Divider(color: AppColors.border, height: 20.h),

          // Items summary
          if (order.items.isNotEmpty) ...[
            ...order.items.take(2).map((item) => Padding(
                  padding: EdgeInsets.only(bottom: 4.h),
                  child: Row(
                    children: [
                      Text(
                        '${item.quantity.toInt()} × ',
                        style: AppTypography.bodySmall.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Expanded(
                        child: Text(
                          '${item.productName} (${item.variantName})',
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                )),
            if (order.itemsCount > 2)
              Padding(
                padding: EdgeInsets.only(top: 2.h),
                child: Text(
                  '+ ${order.itemsCount - 2} أصناف أخرى',
                  style: AppTypography.labelSmall.copyWith(
                    color: AppColors.textMuted,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
            SizedBox(height: 8.h),
          ],

          // Total Price
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'الإجمالي',
                      style: AppTypography.labelSmall.copyWith(
                        color: AppColors.textMuted,
                      ),
                    ),
                    Text(
                      CurrencyFormatter.format(
                        order.total,
                        symbol: order.currencySymbol,
                        code: order.currencyCode,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.titleSmall.copyWith(
                        fontWeight: FontWeight.w900,
                        color: AppColors.primaryDark,
                        fontFamily: AppTypography.titleSmall.fontFamily,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) =>
                            OrderTrackingScreen(orderId: order.id),
                      ),
                    );
                  },
                  style: OutlinedButton.styleFrom(
                    padding: EdgeInsets.symmetric(vertical: 10.h),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                    side: const BorderSide(color: AppColors.border),
                  ),
                  child: Text(
                    'التتبع',
                    style: AppTypography.labelSmall.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) =>
                            OrderDetailScreen(orderId: order.id),
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primaryDark,
                    foregroundColor: Colors.white,
                    padding: EdgeInsets.symmetric(vertical: 10.h),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                  ),
                  child: Text(
                    'التفاصيل',
                    style: AppTypography.labelSmall.copyWith(
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
