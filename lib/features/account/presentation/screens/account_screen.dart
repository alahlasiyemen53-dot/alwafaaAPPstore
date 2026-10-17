import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/app_bottom_nav_bar.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/currency/presentation/widgets/currency_selector_sheet.dart';
import 'package:store/features/orders/cubit/orders_cubit.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/account/cubit/loyalty_cubit.dart';
import '../widgets/account_loyalty_card.dart';
import '../widgets/auth_modal_sheet.dart';
import '../widgets/edit_profile_sheet.dart';
import '../widgets/loyalty_sheet.dart';
import '../widgets/support_modal_sheet.dart';
import 'addresses_screen.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/features/discovery/presentation/screens/notifications_screen.dart';
import 'package:store/features/orders/presentation/screens/orders_screen.dart';
import 'package:store/features/support_chat/cubit/support_chat_badge_cubit.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadAccountStats();
    });
  }

  void _loadAccountStats() {
    if (!mounted) return;
    if (!context.read<AccountCubit>().state.isLoggedIn) return;
    context.read<OrdersCubit>().ensureOrdersLoaded();
    context.read<LoyaltyCubit>().fetchLoyalty();
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20.r),
        ),
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        titlePadding: EdgeInsets.fromLTRB(20.w, 24.h, 20.w, 8.h),
        contentPadding: EdgeInsets.fromLTRB(20.w, 8.h, 20.w, 20.h),
        actionsPadding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 16.h),
        icon: Container(
          width: 52.w,
          height: 52.w,
          decoration: BoxDecoration(
            color: AppColors.error.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(
            Icons.logout_rounded,
            color: AppColors.error,
            size: 26.w,
          ),
        ),
        title: Text(
          AppStrings.logoutConfirmTitle,
          textAlign: TextAlign.center,
          style: AppTypography.titleMedium.copyWith(
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        content: Text(
          AppStrings.logoutConfirmMessage,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 13.sp,
            color: AppColors.textSecondary,
          ),
        ),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.of(dialogCtx).pop(false),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: AppColors.border, width: 1),
                    padding: EdgeInsets.symmetric(vertical: 12.h),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                  ),
                  child: Text(
                    AppStrings.no,
                    style: TextStyle(
                      fontSize: 13.sp,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: ElevatedButton(
                  onPressed: () => Navigator.of(dialogCtx).pop(true),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.error,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: EdgeInsets.symmetric(vertical: 12.h),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                  ),
                  child: Text(
                    AppStrings.yes,
                    style: TextStyle(
                      fontSize: 13.sp,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      final messenger = ScaffoldMessenger.of(context);
      await context.read<AccountCubit>().logout();
      AppSnackBar.show(
        messenger,
        AppStrings.loggedOutSuccess,
        type: AppSnackBarType.success,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final currencyCubit = context.watch<CurrencyCubit>();
    final ordersCubit = context.watch<OrdersCubit>();
    final wishlistCubit = context.watch<WishlistCubit>();

    return BlocListener<AccountCubit, AccountState>(
      listenWhen: (previous, current) =>
          !previous.isLoggedIn && current.isLoggedIn,
      listener: (context, state) {
        context.read<OrdersCubit>().ensureOrdersLoaded(forceRefresh: true);
        context.read<LoyaltyCubit>().fetchLoyalty();
      },
      child: BlocBuilder<AccountCubit, AccountState>(
      builder: (context, state) {
        final user = state.user;

        return SafeArea(
          bottom: false,
          child: ListView(
            padding: EdgeInsets.only(
              left: 16.w,
              right: 16.w,
              top: 12.h,
              bottom: 120.h + MediaQuery.of(context).padding.bottom,
            ),
            children: [
            // Header
            Row(
              children: [
                Expanded(
                  child: Text(
                    AppStrings.myAccount,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.titleLarge.copyWith(
                      fontWeight: FontWeight.w800,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
                SizedBox(width: 8.w),
                GestureDetector(
                  onTap: () => CurrencySelectorSheet.show(context),
                  child: Container(
                    padding:
                        EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20.r),
                      border: Border.all(color: AppColors.border, width: 0.8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.language_rounded,
                            size: 14.w, color: AppColors.gold),
                        SizedBox(width: 4.w),
                        Text(
                          '${currencyCubit.state.selectedCurrency.code} (${currencyCubit.state.selectedCurrency.symbol})',
                          style: TextStyle(
                              fontSize: 10.sp,
                              fontWeight: FontWeight.bold,
                              color: AppColors.primaryDark),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            SizedBox(height: 14.h),

            // Customer profile / guest state
            if (user != null)
              Container(
                padding: EdgeInsets.all(16.w),
                decoration: BoxDecoration(
                  color: AppColors.primaryDark,
                  borderRadius: BorderRadius.circular(24.r),
                  border: Border.all(color: AppColors.primaryLight, width: 1.2),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primaryDark.withValues(alpha: 0.2),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 52.w,
                          height: 52.w,
                          decoration: BoxDecoration(
                            color: AppColors.gold,
                            borderRadius: BorderRadius.circular(16.r),
                            border: Border.all(
                                color: Colors.white.withValues(alpha: 0.3),
                                width: 1.5),
                          ),
                          child: Center(
                            child: Text(
                              user.name
                                  .split(' ')
                                  .map((w) => w.isNotEmpty ? w[0] : '')
                                  .take(2)
                                  .join('.'),
                              style: TextStyle(
                                fontSize: 16.sp,
                                fontWeight: FontWeight.w900,
                                color: AppColors.primaryDark,
                              ),
                            ),
                          ),
                        ),
                        SizedBox(width: 14.w),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      user.name,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontSize: 14.sp,
                                        fontWeight: FontWeight.bold,
                                        color: AppColors.textLight,
                                      ),
                                    ),
                                  ),
                                  if (user.tierName != null) ...[
                                    SizedBox(width: 8.w),
                                    Flexible(
                                      child: Container(
                                        padding: EdgeInsets.symmetric(
                                            horizontal: 6.w, vertical: 2.h),
                                        decoration: BoxDecoration(
                                          color: AppColors.gold
                                              .withValues(alpha: 0.2),
                                          borderRadius:
                                              BorderRadius.circular(10.r),
                                          border: Border.all(
                                              color: AppColors.gold
                                                  .withValues(alpha: 0.4)),
                                        ),
                                        child: Text(
                                          user.tierName!,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: TextStyle(
                                              fontSize: 8.5.sp,
                                              fontWeight: FontWeight.bold,
                                              color: AppColors.gold),
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              SizedBox(height: 2.h),
                              Text(user.phone,
                                  style: TextStyle(
                                      fontSize: 11.sp,
                                      color: AppColors.goldLight)),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () => EditProfileSheet.show(context),
                          icon: Icon(
                            Icons.edit_outlined,
                            color: AppColors.gold,
                            size: 20.w,
                          ),
                          tooltip: 'تعديل الملف الشخصي',
                        ),
                      ],
                    ),
                    Divider(
                        color: Colors.white.withValues(alpha: 0.12),
                        height: 24.h),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildStatItem('حالة الهاتف',
                            user.phoneVerified ? 'موثّق' : 'غير موثّق'),
                        Container(
                            width: 1,
                            height: 24.h,
                            color: Colors.white.withValues(alpha: 0.12)),
                        _buildStatItem('الطلبات النشطة',
                            '${ordersCubit.state.activeOrdersCount} طلب'),
                        Container(
                            width: 1,
                            height: 24.h,
                            color: Colors.white.withValues(alpha: 0.12)),
                        _buildStatItem('المفضلة',
                            '${wishlistCubit.state.productIds.length} عنصر'),
                      ],
                    ),
                  ],
                ),
              )
            else
              Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(24.r),
                  onTap: state.status == AuthStatus.unknown
                      ? null
                      : () => AuthModalSheet.show(context),
                  child: Ink(
                    padding: EdgeInsets.all(20.w),
                    decoration: BoxDecoration(
                      color: AppColors.primaryDark,
                      borderRadius: BorderRadius.circular(24.r),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.person_outline_rounded,
                            color: AppColors.gold),
                        SizedBox(width: 12.w),
                        Expanded(
                          child: Text(
                            state.status == AuthStatus.unknown
                                ? 'جارٍ التحقق من الجلسة...'
                                : 'سجّل الدخول للوصول إلى بيانات حسابك.',
                            style: TextStyle(
                              fontSize: 12.sp,
                              color: AppColors.textLight,
                            ),
                          ),
                        ),
                        if (state.status == AuthStatus.unknown)
                          SizedBox(
                            width: 16.w,
                            height: 16.w,
                            child: const CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.gold,
                            ),
                          )
                        else
                          Icon(
                            Icons.arrow_forward_ios_rounded,
                            size: 14.w,
                            color: AppColors.gold,
                          ),
                      ],
                    ),
                  ),
                ),
              ),

            SizedBox(height: 18.h),

            if (user != null) ...[
              const AccountLoyaltyCard(),
              SizedBox(height: 14.h),
            ],

            // Navigation Tiles
            Material(
              color: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20.r),
                side: BorderSide(color: AppColors.border, width: 0.8),
              ),
              clipBehavior: Clip.antiAlias,
              child: Column(
                children: [
                  _buildTile(
                    icon: Icons.local_shipping_outlined,
                    title: AppStrings.myOrders,
                    subtitle: 'متابعة طلباتك وتفاصيل الفواتير',
                    onTap: () async {
                      if (!state.isLoggedIn) {
                        final authenticated =
                            await AuthModalSheet.show(context);
                        if (!context.mounted || authenticated != true) return;
                      }
                      OrdersScreen.open(context);
                    },
                  ),
                  Divider(color: AppColors.borderLight, height: 1),
                  _buildTile(
                    icon: Icons.language_rounded,
                    title: AppStrings.currencySelector,
                    subtitle: 'تحديد عملة الدفع والأسعار المعتمدة',
                    onTap: () => CurrencySelectorSheet.show(context),
                  ),
                  Divider(color: AppColors.borderLight, height: 1),
                  _buildTile(
                    icon: Icons.pin_drop_outlined,
                    title: AppStrings.savedAddresses,
                    subtitle: 'إدارة عناوين الشحن والاستلام في اليمن',
                    onTap: () async {
                      if (!state.isLoggedIn) {
                        final authenticated =
                            await AuthModalSheet.show(context);
                        if (!context.mounted || authenticated != true) return;
                      }
                      AddressesScreen.open(context);
                    },
                  ),
                  Divider(color: AppColors.borderLight, height: 1),
                  BlocBuilder<SupportChatBadgeCubit, SupportChatBadgeState>(
                    builder: (context, badge) {
                      return _buildTile(
                        icon: Icons.support_agent_rounded,
                        title: AppStrings.customerSupport,
                        subtitle: badge.unreadCount > 0
                            ? 'لديك ${badge.unreadCount} رد جديد من الدعم'
                            : 'محادثة مباشرة، واتساب، أو اتصال',
                        badgeCount: badge.unreadCount,
                        onTap: () => SupportModalSheet.show(context),
                      );
                    },
                  ),
                  Divider(color: AppColors.borderLight, height: 1),
                  _buildTile(
                    icon: Icons.notifications_outlined,
                    title: 'الإشعارات',
                    subtitle: 'تنبيهات الطلبات والتحديثات المهمة',
                    onTap: () async {
                      if (!state.isLoggedIn) {
                        final authenticated =
                            await AuthModalSheet.show(context);
                        if (!context.mounted || authenticated != true) return;
                      }
                      NotificationsScreen.open(context);
                    },
                  ),
                  if (user == null) ...[
                    Divider(color: AppColors.borderLight, height: 1),
                    _buildTile(
                      icon: Icons.workspace_premium_outlined,
                      title: 'نقاط الولاء ومكافآت VIP',
                      subtitle: 'سجّل الدخول لمتابعة رصيدك ومكافآتك',
                      onTap: () async {
                        final authenticated =
                            await AuthModalSheet.show(context);
                        if (!context.mounted || authenticated != true) return;
                        LoyaltySheet.show(context);
                      },
                    ),
                  ],
                ],
              ),
            ),

            SizedBox(height: 16.h),

            // Logout — only when authenticated, placed before bottom-nav style option
            if (state.isLoggedIn) ...[
              Material(
                color: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16.r),
                  side: BorderSide(color: AppColors.border, width: 0.8),
                ),
                clipBehavior: Clip.antiAlias,
                child: ListTile(
                  onTap: state.isBusy
                      ? null
                      : () => _confirmLogout(context),
                  leading: Icon(
                    state.status == AuthStatus.loggingOut
                        ? Icons.hourglass_top_rounded
                        : Icons.logout_rounded,
                    color: AppColors.error,
                  ),
                  title: Text(
                    AppStrings.logout,
                    style: TextStyle(
                      fontSize: 12.sp,
                      fontWeight: FontWeight.bold,
                      color: AppColors.error,
                    ),
                  ),
                  trailing: const Icon(Icons.chevron_right_rounded, size: 18),
                ),
              ),
              SizedBox(height: 16.h),
            ],

            // Variations & Design Switcher
            BlocBuilder<AppShellCubit, AppShellState>(
              builder: (context, shellState) {
                final isClassic =
                    shellState.bottomNavStyle == BottomNavStyle.classic;
                return Container(
                  padding: EdgeInsets.all(16.w),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(18.r),
                    border: Border.all(color: AppColors.border, width: 0.8),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: EdgeInsets.all(8.w),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceSoft,
                              borderRadius: BorderRadius.circular(10.r),
                            ),
                            child: Icon(Icons.style_outlined,
                                color: AppColors.primaryDark, size: 20.w),
                          ),
                          SizedBox(width: 10.w),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'شريط التنقل السفلي',
                                  style: TextStyle(
                                    fontSize: 13.sp,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                SizedBox(height: 2.h),
                                Text(
                                  'اختر نمط العرض المفضل لشريط التنقل',
                                  style: TextStyle(
                                    fontSize: 10.5.sp,
                                    color: AppColors.textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      SizedBox(height: 14.h),
                      Row(
                        children: [
                          Expanded(
                            child: _buildNavStyleOption(
                              context: context,
                              title: 'كلاسيكي (افتراضي)',
                              icon: Icons.dock_rounded,
                              isSelected: isClassic,
                              onTap: () {
                                context
                                    .read<AppShellCubit>()
                                    .setBottomNavStyle(BottomNavStyle.classic);
                                context.showAppSnackBar(
                                  'تم تفعيل النمط الكلاسيكي لشريط التنقل',
                                  type: AppSnackBarType.info,
                                );
                              },
                            ),
                          ),
                          SizedBox(width: 10.w),
                          Expanded(
                            child: _buildNavStyleOption(
                              context: context,
                              title: 'عائم فاخر',
                              icon: Icons.filter_none_rounded,
                              isSelected: !isClassic,
                              onTap: () {
                                context
                                    .read<AppShellCubit>()
                                    .setBottomNavStyle(BottomNavStyle.floating);
                                context.showAppSnackBar(
                                  'تم تفعيل النمط العائم الفاخر لشريط التنقل',
                                  type: AppSnackBarType.info,
                                );
                              },
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
            SizedBox(height: 16.h),
          ],
        ),
      );
    },
    ),
  );
  }

  Widget _buildStatItem(String label, String value) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: 12.sp,
            fontWeight: FontWeight.w900,
            color: AppColors.textLight,
          ),
        ),
        SizedBox(height: 2.h),
        Text(
          label,
          style: TextStyle(fontSize: 9.5.sp, color: AppColors.goldLight),
        ),
      ],
    );
  }

  Widget _buildTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
    int badgeCount = 0,
  }) {
    return ListTile(
      onTap: onTap,
      leading: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            padding: EdgeInsets.all(8.w),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(12.r),
            ),
            child: Icon(icon, color: AppColors.primaryDark, size: 20.w),
          ),
          if (badgeCount > 0)
            Positioned(
              top: -2,
              left: -2,
              child: Container(
                padding: EdgeInsets.all(3.w),
                decoration: const BoxDecoration(
                  color: AppColors.discountBadge,
                  shape: BoxShape.circle,
                ),
                constraints: BoxConstraints(minWidth: 14.w, minHeight: 14.w),
                child: Center(
                  child: Text(
                    badgeCount > 9 ? '9+' : '$badgeCount',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 8.sp,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
      title: Text(
        title,
        style: TextStyle(
            fontSize: 12.sp,
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary),
      ),
      subtitle: Text(
        subtitle,
        style: TextStyle(fontSize: 10.sp, color: AppColors.textMuted),
      ),
      trailing: const Icon(Icons.chevron_right_rounded,
          size: 18, color: AppColors.textMuted),
    );
  }

  Widget _buildNavStyleOption({
    required BuildContext context,
    required String title,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12.r),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 10.h),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primaryDark : AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(
            color: isSelected ? AppColors.primaryDark : AppColors.border,
            width: isSelected ? 1.5 : 1.0,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 16.w,
              color: isSelected ? AppColors.gold : AppColors.textSecondary,
            ),
            SizedBox(width: 6.w),
            Flexible(
              child: Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11.sp,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                  color: isSelected ? Colors.white : AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
