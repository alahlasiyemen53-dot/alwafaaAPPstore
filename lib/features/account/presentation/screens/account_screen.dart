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
          final currency = currencyCubit.state.selectedCurrency;

          return ColoredBox(
            color: AppColors.background,
            child: SafeArea(
              bottom: false,
              child: ListView(
                padding: EdgeInsets.only(
                  left: 16.w,
                  right: 16.w,
                  top: 8.h,
                  bottom: 120.h + MediaQuery.of(context).padding.bottom,
                ),
                children: [
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
                            letterSpacing: -0.3,
                          ),
                        ),
                      ),
                      SizedBox(width: 8.w),
                      _CurrencyChip(
                        label: '${currency.code} (${currency.symbol})',
                        onTap: () => CurrencySelectorSheet.show(context),
                      ),
                    ],
                  ),
                  SizedBox(height: 16.h),
                  if (user != null)
                    _ProfileCard(
                      name: user.name,
                      phone: user.phone,
                      tierName: user.tierName,
                      phoneVerified: user.phoneVerified,
                      ordersLabel: '${ordersCubit.state.activeOrdersCount} طلب',
                      wishlistLabel:
                          '${wishlistCubit.state.productIds.length} عنصر',
                      onEdit: () => EditProfileSheet.show(context),
                    )
                  else
                    _GuestCard(
                      loading: state.status == AuthStatus.unknown,
                      onTap: state.status == AuthStatus.unknown
                          ? null
                          : () => AuthModalSheet.show(context),
                    ),
                  if (user != null) ...[
                    SizedBox(height: 14.h),
                    const AccountLoyaltyCard(),
                  ],
                  SizedBox(height: 18.h),
                  _MenuGroup(
                    children: [
                      _MenuTile(
                        icon: Icons.local_shipping_outlined,
                        title: AppStrings.myOrders,
                        subtitle: 'متابعة طلباتك وتفاصيل الفواتير',
                        onTap: () async {
                          if (!state.isLoggedIn) {
                            final authenticated =
                                await AuthModalSheet.show(context);
                            if (!context.mounted || authenticated != true) {
                              return;
                            }
                          }
                          OrdersScreen.open(context);
                        },
                      ),
                      _MenuTile(
                        icon: Icons.language_rounded,
                        title: AppStrings.currencySelector,
                        subtitle: 'تحديد عملة الدفع والأسعار المعتمدة',
                        onTap: () => CurrencySelectorSheet.show(context),
                      ),
                      _MenuTile(
                        icon: Icons.pin_drop_outlined,
                        title: AppStrings.savedAddresses,
                        subtitle: 'إدارة عناوين الشحن والاستلام في اليمن',
                        onTap: () async {
                          if (!state.isLoggedIn) {
                            final authenticated =
                                await AuthModalSheet.show(context);
                            if (!context.mounted || authenticated != true) {
                              return;
                            }
                          }
                          AddressesScreen.open(context);
                        },
                      ),
                    ],
                  ),
                  SizedBox(height: 12.h),
                  _MenuGroup(
                    children: [
                      BlocBuilder<SupportChatBadgeCubit, SupportChatBadgeState>(
                        builder: (context, badge) {
                          return _MenuTile(
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
                      _MenuTile(
                        icon: Icons.notifications_outlined,
                        title: 'الإشعارات',
                        subtitle: 'تنبيهات الطلبات والتحديثات المهمة',
                        onTap: () async {
                          if (!state.isLoggedIn) {
                            final authenticated =
                                await AuthModalSheet.show(context);
                            if (!context.mounted || authenticated != true) {
                              return;
                            }
                          }
                          NotificationsScreen.open(context);
                        },
                      ),
                      if (user == null)
                        _MenuTile(
                          icon: Icons.workspace_premium_outlined,
                          title: 'نقاط الولاء ومكافآت VIP',
                          subtitle: 'سجّل الدخول لمتابعة رصيدك ومكافآتك',
                          onTap: () async {
                            final authenticated =
                                await AuthModalSheet.show(context);
                            if (!context.mounted || authenticated != true) {
                              return;
                            }
                            LoyaltySheet.show(context);
                          },
                        ),
                    ],
                  ),
                  if (state.isLoggedIn) ...[
                    SizedBox(height: 12.h),
                    _LogoutRow(
                      busy: state.isBusy,
                      loggingOut: state.status == AuthStatus.loggingOut,
                      onTap: () => _confirmLogout(context),
                    ),
                  ],
                  SizedBox(height: 12.h),
                  const _NavStyleCard(),
                  SizedBox(height: 8.h),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _CurrencyChip extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _CurrencyChip({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(20.r),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20.r),
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 6.h),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.language_rounded, size: 14.w, color: AppColors.gold),
              SizedBox(width: 4.w),
              Text(
                label,
                style: TextStyle(
                  fontSize: 10.5.sp,
                  fontWeight: FontWeight.w800,
                  color: AppColors.primaryDark,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  final String name;
  final String phone;
  final String? tierName;
  final bool phoneVerified;
  final String ordersLabel;
  final String wishlistLabel;
  final VoidCallback onEdit;

  const _ProfileCard({
    required this.name,
    required this.phone,
    required this.tierName,
    required this.phoneVerified,
    required this.ordersLabel,
    required this.wishlistLabel,
    required this.onEdit,
  });

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty);
    return parts.take(2).map((w) => w.substring(0, 1)).join();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(14.w, 14.h, 14.w, 12.h),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22.r),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryDark.withValues(alpha: 0.04),
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
                width: 56.w,
                height: 56.w,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.primaryContainer,
                  borderRadius: BorderRadius.circular(18.r),
                  border: Border.all(
                    color: AppColors.gold.withValues(alpha: 0.35),
                  ),
                ),
                child: Text(
                  _initials,
                  style: TextStyle(
                    fontSize: 16.sp,
                    fontWeight: FontWeight.w900,
                    color: AppColors.goldDark,
                  ),
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 15.sp,
                              fontWeight: FontWeight.w800,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ),
                        if (tierName != null) ...[
                          SizedBox(width: 6.w),
                          Flexible(
                            child: Container(
                              padding: EdgeInsets.symmetric(
                                horizontal: 7.w,
                                vertical: 2.h,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.burgundyContainer,
                                borderRadius: BorderRadius.circular(20.r),
                              ),
                              child: Text(
                                tierName!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 9.sp,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.burgundy,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    SizedBox(height: 3.h),
                    Text(
                      phone,
                      style: TextStyle(
                        fontSize: 12.sp,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: onEdit,
                tooltip: 'تعديل الملف الشخصي',
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.surfaceSoft,
                  minimumSize: Size(40.w, 40.w),
                ),
                icon: Icon(
                  Icons.edit_outlined,
                  color: AppColors.primaryDark,
                  size: 18.w,
                ),
              ),
            ],
          ),
          SizedBox(height: 14.h),
          Row(
            children: [
              Expanded(
                child: _StatPill(
                  label: 'حالة الهاتف',
                  value: phoneVerified ? 'موثّق' : 'غير موثّق',
                  accent: phoneVerified ? AppColors.gold : AppColors.burgundy,
                ),
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: _StatPill(
                  label: 'الطلبات النشطة',
                  value: ordersLabel,
                  accent: AppColors.goldDark,
                ),
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: _StatPill(
                  label: 'المفضلة',
                  value: wishlistLabel,
                  accent: AppColors.burgundy,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatPill extends StatelessWidget {
  final String label;
  final String value;
  final Color accent;

  const _StatPill({
    required this.label,
    required this.value,
    required this.accent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 8.h),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(14.r),
      ),
      child: Column(
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11.5.sp,
              fontWeight: FontWeight.w800,
              color: accent,
            ),
          ),
          SizedBox(height: 2.h),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 9.sp,
              color: AppColors.textMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _GuestCard extends StatelessWidget {
  final bool loading;
  final VoidCallback? onTap;

  const _GuestCard({required this.loading, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(22.r),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22.r),
        child: Container(
          padding: EdgeInsets.all(16.w),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                width: 48.w,
                height: 48.w,
                decoration: BoxDecoration(
                  color: AppColors.primaryContainer,
                  borderRadius: BorderRadius.circular(16.r),
                ),
                child: Icon(
                  Icons.person_outline_rounded,
                  color: AppColors.goldDark,
                  size: 24.w,
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Text(
                  loading
                      ? 'جارٍ التحقق من الجلسة...'
                      : 'سجّل الدخول للوصول إلى بيانات حسابك.',
                  style: TextStyle(
                    fontSize: 13.sp,
                    height: 1.35,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primaryDark,
                  ),
                ),
              ),
              if (loading)
                SizedBox(
                  width: 18.w,
                  height: 18.w,
                  child: const CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.gold,
                  ),
                )
              else
                Icon(
                  Icons.chevron_left_rounded,
                  size: 22.w,
                  color: AppColors.goldDark,
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MenuGroup extends StatelessWidget {
  final List<Widget> children;

  const _MenuGroup({required this.children});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20.r),
        side: const BorderSide(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0)
              Divider(
                height: 1,
                indent: 64.w,
                color: AppColors.borderLight,
              ),
            children[i],
          ],
        ],
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final int badgeCount;

  const _MenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badgeCount = 0,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      contentPadding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 2.h),
      minVerticalPadding: 10.h,
      leading: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 40.w,
            height: 40.w,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(12.r),
            ),
            child: Icon(icon, color: AppColors.primaryDark, size: 20.w),
          ),
          if (badgeCount > 0)
            PositionedDirectional(
              top: -4,
              start: -4,
              child: Container(
                padding: EdgeInsets.all(3.w),
                constraints: BoxConstraints(minWidth: 16.w, minHeight: 16.w),
                decoration: const BoxDecoration(
                  color: AppColors.burgundy,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  badgeCount > 9 ? '9+' : '$badgeCount',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 8.sp,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
              ),
            ),
        ],
      ),
      title: Text(
        title,
        style: TextStyle(
          fontSize: 13.sp,
          fontWeight: FontWeight.w800,
          color: AppColors.textPrimary,
        ),
      ),
      subtitle: Padding(
        padding: EdgeInsets.only(top: 2.h),
        child: Text(
          subtitle,
          style: TextStyle(fontSize: 11.sp, color: AppColors.textMuted),
        ),
      ),
      trailing: Icon(
        Icons.chevron_left_rounded,
        size: 20.w,
        color: AppColors.textMuted,
      ),
    );
  }
}

class _LogoutRow extends StatelessWidget {
  final bool busy;
  final bool loggingOut;
  final VoidCallback onTap;

  const _LogoutRow({
    required this.busy,
    required this.loggingOut,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18.r),
        side: BorderSide(color: AppColors.error.withValues(alpha: 0.25)),
      ),
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        onTap: busy ? null : onTap,
        leading: Icon(
          loggingOut ? Icons.hourglass_top_rounded : Icons.logout_rounded,
          color: AppColors.error,
        ),
        title: Text(
          AppStrings.logout,
          style: TextStyle(
            fontSize: 13.sp,
            fontWeight: FontWeight.w800,
            color: AppColors.error,
          ),
        ),
        trailing: Icon(
          Icons.chevron_left_rounded,
          size: 20.w,
          color: AppColors.error.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}

class _NavStyleCard extends StatelessWidget {
  const _NavStyleCard();

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<AppShellCubit, AppShellState>(
      builder: (context, shellState) {
        final isClassic = shellState.bottomNavStyle == BottomNavStyle.classic;
        return Container(
          padding: EdgeInsets.all(14.w),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'شريط التنقل السفلي',
                style: TextStyle(
                  fontSize: 13.sp,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              SizedBox(height: 2.h),
              Text(
                'اختر نمط العرض المفضل لشريط التنقل',
                style: TextStyle(
                  fontSize: 11.sp,
                  color: AppColors.textMuted,
                ),
              ),
              SizedBox(height: 12.h),
              Row(
                children: [
                  Expanded(
                    child: _StyleOption(
                      title: 'كلاسيكي (افتراضي)',
                      icon: Icons.dock_rounded,
                      selected: isClassic,
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
                    child: _StyleOption(
                      title: 'عائم فاخر',
                      icon: Icons.filter_none_rounded,
                      selected: !isClassic,
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
    );
  }
}

class _StyleOption extends StatelessWidget {
  final String title;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  const _StyleOption({
    required this.title,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14.r),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 12.h),
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryDark : AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(14.r),
          border: Border.all(
            color: selected ? AppColors.primaryDark : AppColors.border,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 16.w,
              color: selected ? Colors.white : AppColors.textSecondary,
            ),
            SizedBox(width: 6.w),
            Flexible(
              child: Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11.sp,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                  color: selected ? Colors.white : AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
