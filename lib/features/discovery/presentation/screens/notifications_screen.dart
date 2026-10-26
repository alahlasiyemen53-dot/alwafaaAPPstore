import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';

import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/app_empty_state.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/data/models/customer/notification_model.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/discovery/cubit/notifications_cubit.dart';
import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import 'package:store/features/orders/presentation/screens/order_detail_screen.dart';
import 'package:store/features/support_chat/presentation/screens/support_chat_inbox_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  static void open(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
    );
  }

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    context.read<NotificationsCubit>().load();
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<NotificationsCubit>().loadMore();
    }
  }

  void _handleTap(CustomerNotificationModel notification) {
    context.read<NotificationsCubit>().markRead(notification.id);

    final type = notification.destinationType;
    final id = notification.destinationId;
    final upperType = notification.type.toUpperCase();

    if (type == 'support_chat' || upperType == 'SUPPORT_CHAT_MESSAGE') {
      SupportChatInboxScreen.open(
        context,
        conversationId: notification.destinationKey ??
            (id != null && id > 0 ? id.toString() : null),
        preferDirectThread: true,
      );
      return;
    }

    if (id == null || id <= 0) return;

    if (type == 'order') {
      OrderDetailScreen.open(
        context,
        id,
        openRating: notification.openRating,
      );
    } else if (type == 'product') {
      context.read<AppShellCubit>().openProductDetails(
            ProductModel(
              id: id.toString(),
              serverId: id,
              name: notification.title,
              brand: '',
              category: ProductCategory.all,
              priceSAR: 0,
              rating: 0,
              reviewsCount: 0,
              image: 'assets/images/placeholder.png',
              images: const [],
              description: '',
              inStock: true,
            ),
          );
      Navigator.of(context).pop();
    } else if (type == 'category') {
      context.read<CatalogCubit>().selectCategory(
            ProductCategory.all,
            serverId: id,
          );
      context.read<AppShellCubit>().setTab(1);
      Navigator.of(context).pop();
    } else if (type == 'collection') {
      CollectionDetailScreen.open(context, id);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'الإشعارات',
          style: AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
        actions: [
          BlocBuilder<NotificationsCubit, NotificationsState>(
            builder: (context, state) {
              if (state.unreadCount <= 0) return const SizedBox.shrink();
              return Padding(
                padding: EdgeInsetsDirectional.only(end: 4.w),
                child: TextButton(
                  onPressed: () =>
                      context.read<NotificationsCubit>().markAllRead(),
                  style: TextButton.styleFrom(
                    padding: EdgeInsets.symmetric(horizontal: 8.w),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: Text(
                    'قراءة الكل',
                    style: TextStyle(
                      fontSize: 11.sp,
                      fontWeight: FontWeight.w600,
                      color: AppColors.goldDark,
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
      body: BlocBuilder<NotificationsCubit, NotificationsState>(
        builder: (context, state) {
          if (state.status == NotificationsStatus.loading &&
              state.items.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (state.status == NotificationsStatus.error &&
              state.items.isEmpty) {
            return Center(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 24.w),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      state.errorMessage ?? 'تعذر تحميل الإشعارات.',
                      textAlign: TextAlign.center,
                      style: AppTypography.bodyMedium,
                    ),
                    SizedBox(height: 12.h),
                    ElevatedButton(
                      onPressed: () =>
                          context.read<NotificationsCubit>().load(),
                      child: const Text('إعادة المحاولة'),
                    ),
                  ],
                ),
              ),
            );
          }

          if (state.items.isEmpty) {
            return AppEmptyState(
              icon: Icons.notifications_none_rounded,
              title: 'صندوق الإشعارات هادئ',
              message:
                  'سننبّهك هنا بتحديثات الطلبات والعروض المهمة فور وصولها.',
              actionLabel: 'العودة للرئيسية',
              onAction: () {
                Navigator.of(context).pop();
                context.read<AppShellCubit>().setTab(0);
              },
            );
          }

          return RefreshIndicator(
            color: AppColors.primaryDark,
            onRefresh: () => context.read<NotificationsCubit>().load(),
            child: ListView.separated(
              controller: _scrollController,
              padding: EdgeInsets.fromLTRB(
                16.w,
                12.h,
                16.w,
                12.h + MediaQuery.paddingOf(context).bottom,
              ),
              itemCount: state.items.length + (state.isLoadingMore ? 1 : 0),
              separatorBuilder: (_, _) => SizedBox(height: 8.h),
              itemBuilder: (context, index) {
                if (index >= state.items.length) {
                  return Padding(
                    padding: EdgeInsets.all(16.w),
                    child: const Center(child: CircularProgressIndicator()),
                  );
                }
                final item = state.items[index];
                final dateStr = item.createdAt != null
                    ? DateFormat('d MMM yyyy • h:mm a', 'ar')
                        .format(item.createdAt!)
                    : '';

                return Material(
                  color: item.isUnread
                      ? AppColors.gold.withValues(alpha: 0.08)
                      : Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16.r),
                    side: BorderSide(color: AppColors.border, width: 0.8),
                  ),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16.r),
                    onTap: () => _handleTap(item),
                    child: Padding(
                      padding: EdgeInsets.all(14.w),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (item.isUnread)
                            Container(
                              width: 8.w,
                              height: 8.w,
                              margin: EdgeInsets.only(top: 6.h, left: 8.w),
                              decoration: const BoxDecoration(
                                color: AppColors.goldDark,
                                shape: BoxShape.circle,
                              ),
                            )
                          else
                            SizedBox(width: 16.w),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.title,
                                  style: AppTypography.bodyMedium.copyWith(
                                    fontWeight: item.isUnread
                                        ? FontWeight.w800
                                        : FontWeight.w600,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                if (item.body.isNotEmpty) ...[
                                  SizedBox(height: 4.h),
                                  Text(
                                    item.body,
                                    style: AppTypography.bodySmall.copyWith(
                                      color: AppColors.textSecondary,
                                    ),
                                    maxLines: 3,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                                if (dateStr.isNotEmpty) ...[
                                  SizedBox(height: 6.h),
                                  Text(
                                    dateStr,
                                    style: TextStyle(
                                      fontSize: 10.sp,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
