import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/utils/extensions.dart';
import '../../../../core/widgets/skeletons/screen_skeletons.dart';
import '../../../support_chat/presentation/screens/support_chat_inbox_screen.dart';
import '../../cubit/account_cubit.dart';
import '../../cubit/support_cubit.dart';
import 'auth_modal_sheet.dart';

class SupportModalSheet extends StatefulWidget {
  const SupportModalSheet({super.key});

  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => MultiBlocProvider(
        providers: [
          BlocProvider.value(
            value: context.read<SupportCubit>()..fetchSupport(),
          ),
          BlocProvider.value(value: context.read<AccountCubit>()),
        ],
        child: const SupportModalSheet(),
      ),
    );
  }

  @override
  State<SupportModalSheet> createState() => _SupportModalSheetState();
}

class _SupportModalSheetState extends State<SupportModalSheet> {
  Future<void> _openLiveChat() async {
    final accountCubit = context.read<AccountCubit>();
    if (!accountCubit.state.isLoggedIn) {
      final authenticated = await AuthModalSheet.show(context);
      if (!mounted || authenticated != true) return;
    }
    if (!mounted) return;
    final navigator = Navigator.of(context);
    navigator.pop();
    // Most customers have one active thread — open it directly.
    await SupportChatInboxScreen.push(
      navigator,
      preferDirectThread: true,
    );
  }

  Future<void> _openExternalLink(String? rawUrl, {String? fallbackCopy}) async {
    final url = rawUrl?.trim();
    if (url != null && url.isNotEmpty) {
      final uri = Uri.tryParse(url);
      if (uri != null) {
        final launched = await launchUrl(
          uri,
          mode: LaunchMode.externalApplication,
        );
        if (launched) return;
      }
    }

    if (fallbackCopy != null && fallbackCopy.trim().isNotEmpty && mounted) {
      await Clipboard.setData(ClipboardData(text: fallbackCopy.trim()));
      if (!mounted) return;
      context.showAppSnackBar(
        'تعذر الفتح تلقائياً. تم نسخ: $fallbackCopy',
        type: AppSnackBarType.info,
      );
    } else if (mounted) {
      context.showAppSnackBar(
        'تعذر فتح الرابط حالياً.',
        type: AppSnackBarType.error,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.75,
      ),
      padding: EdgeInsets.only(
        left: 20.w,
        right: 20.w,
        top: 16.h,
        bottom: 24.h + MediaQuery.of(context).padding.bottom,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
      ),
      child: BlocBuilder<SupportCubit, SupportState>(
        builder: (context, state) {
          final support = state.support;

          return SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
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
              SizedBox(height: 16.h),

              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      'خدمة العملاء والدعم الفني',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.titleLarge.copyWith(
                        color: AppColors.primaryDark,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              SizedBox(height: 12.h),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _openLiveChat,
                  icon: const Icon(Icons.forum_outlined),
                  label: Text(
                    context.watch<AccountCubit>().state.isLoggedIn
                        ? 'محادثة مباشرة مع الدعم'
                        : 'محادثة مباشرة — سجّل الدخول',
                  ),
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
              SizedBox(height: 12.h),

              if (state.status == SupportStatus.loading && support == null)
                Padding(
                  padding: EdgeInsets.symmetric(vertical: 24.h),
                  child: const SupportSheetSkeleton(),
                )
              else if (support == null)
                Padding(
                  padding: EdgeInsets.symmetric(vertical: 24.h),
                  child: Center(
                    child: Column(
                      children: [
                        Text(
                          state.errorMessage ?? 'تعذر جلب معلومات الدعم الفني',
                          style: TextStyle(
                              color: AppColors.textMuted, fontSize: 13.sp),
                        ),
                        SizedBox(height: 12.h),
                        OutlinedButton(
                          onPressed: () =>
                              context.read<SupportCubit>().fetchSupport(),
                          child: const Text('إعادة المحاولة'),
                        ),
                      ],
                    ),
                  ),
                )
              else ...[
                Container(
                  width: double.infinity,
                  margin: EdgeInsets.only(bottom: 14.h),
                  padding: EdgeInsets.all(14.w),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.primaryDark.withValues(alpha: 0.92),
                        AppColors.primaryContainer,
                      ],
                    ),
                    borderRadius: BorderRadius.circular(16.r),
                    border: Border.all(
                      color: AppColors.gold.withValues(alpha: 0.35),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'نحن هنا من أجلك',
                        style: TextStyle(
                          fontSize: 14.sp,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textLight,
                        ),
                      ),
                      SizedBox(height: 4.h),
                      Text(
                        'اختاري الطريقة الأنسب — واتساب أولاً للرد السريع.',
                        style: TextStyle(
                          fontSize: 11.5.sp,
                          color: AppColors.goldLight,
                          height: 1.35,
                        ),
                      ),
                      if (support.hoursTextAr != null &&
                          support.hoursTextAr!.isNotEmpty) ...[
                        SizedBox(height: 10.h),
                        Row(
                          children: [
                            Icon(Icons.access_time_rounded,
                                size: 16.w, color: AppColors.gold),
                            SizedBox(width: 6.w),
                            Expanded(
                              child: Text(
                                support.hoursTextAr!,
                                style: TextStyle(
                                  fontSize: 11.sp,
                                  color: AppColors.textLight
                                      .withValues(alpha: 0.9),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),

                if (support.whatsapp.enabled &&
                    support.whatsapp.number != null) ...[
                  _buildContactCard(
                    icon: Icons.chat_bubble_outline_rounded,
                    iconColor: AppColors.goldDark,
                    title: 'واتساب — الأسرع',
                    subtitle: support.whatsapp.number!,
                    actionText: 'محادثة',
                    onTap: () {
                      final deepLink = support.whatsapp.deepLink;
                      final number = support.whatsapp.number!;
                      final digits = number.replaceAll(RegExp(r'\D'), '');
                      final fallbackLink =
                          digits.isEmpty ? null : 'https://wa.me/$digits';
                      _openExternalLink(
                        deepLink ?? fallbackLink,
                        fallbackCopy: number,
                      );
                    },
                  ),
                  SizedBox(height: 10.h),
                ],

                if (support.phone.enabled && support.phone.number != null)
                  _buildContactCard(
                    icon: Icons.phone_in_talk_rounded,
                    iconColor: AppColors.primaryDark,
                    title: 'اتصال المبيعات',
                    subtitle: support.phone.number!,
                    actionText: 'اتصال',
                    onTap: () => _openExternalLink(
                      support.phone.telLink ?? 'tel:${support.phone.number}',
                      fallbackCopy: support.phone.number,
                    ),
                  ),
              ],
            ],
          ),
        );
      },
      ),
    );
  }

  Widget _buildContactCard({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    required String actionText,
    required VoidCallback onTap,
  }) {
    return Container(
      padding: EdgeInsets.all(14.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: AppColors.border, width: 0.8),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42.w,
            height: 42.w,
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12.r),
            ),
            child: Icon(icon, color: iconColor, size: 22.w),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13.sp,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryDark,
                  ),
                ),
                SizedBox(height: 2.h),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12.sp,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 8.w),
          ElevatedButton(
            onPressed: onTap,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primaryDark,
              foregroundColor: Colors.white,
              padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 6.h),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10.r)),
            ),
            child: Text(actionText, style: TextStyle(fontSize: 11.sp)),
          ),
        ],
      ),
    );
  }
}
