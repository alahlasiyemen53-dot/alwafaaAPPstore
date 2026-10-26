import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/features/onboarding/guest_onboarding_prefs.dart';

/// Lightweight 2-page guest value intro (delivery / authenticity+local pay).
class GuestOnboardingSheet extends StatefulWidget {
  const GuestOnboardingSheet({super.key});

  static Future<void> showIfNeeded(BuildContext context) async {
    final seen = await GuestOnboardingPrefs.hasSeen();
    if (seen || !context.mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      isDismissible: true,
      enableDrag: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const GuestOnboardingSheet(),
    );
    await GuestOnboardingPrefs.markSeen();
  }

  @override
  State<GuestOnboardingSheet> createState() => _GuestOnboardingSheetState();
}

class _GuestOnboardingSheetState extends State<GuestOnboardingSheet> {
  final _controller = PageController();
  int _page = 0;

  static const _pages = [
    _OnboardingPage(
      icon: Icons.local_shipping_outlined,
      title: 'توصيل موثوق داخل اليمن',
      body: 'نصل طلبك بعناية إلى عنوانك، مع متابعة واضحة لحالة الشحن.',
    ),
    _OnboardingPage(
      icon: Icons.verified_outlined,
      title: 'أصالة ودفع محلي',
      body: 'منتجات أصلية، ودفع يناسبك — عند الاستلام أو عبر المحافظ المحلية.',
    ),
  ];

  Future<void> _finish() async {
    await GuestOnboardingPrefs.markSeen();
    if (!mounted) return;
    Navigator.of(context).pop();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;

    return Container(
      height: MediaQuery.sizeOf(context).height * 0.52,
      padding: EdgeInsets.fromLTRB(20.w, 12.h, 20.w, 16.h + bottom),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        children: [
          Container(
            width: 44.w,
            height: 4.h,
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(4.r),
            ),
          ),
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton(
              onPressed: _finish,
              child: Text(
                'تخطي',
                style: TextStyle(
                  fontSize: 12.sp,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textMuted,
                ),
              ),
            ),
          ),
          Expanded(
            child: PageView.builder(
              controller: _controller,
              itemCount: _pages.length,
              onPageChanged: (i) => setState(() => _page = i),
              itemBuilder: (_, index) {
                final page = _pages[index];
                return Padding(
                  padding: EdgeInsets.symmetric(horizontal: 8.w),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 84.w,
                        height: 84.w,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.primaryDark.withValues(alpha: 0.08),
                          border: Border.all(
                            color: AppColors.gold.withValues(alpha: 0.5),
                          ),
                        ),
                        child: Icon(page.icon,
                            size: 36.w, color: AppColors.primaryDark),
                      ),
                      SizedBox(height: 20.h),
                      Text(
                        AppStrings.appNameArabic,
                        style: GoogleFonts.cormorantGaramond(
                          fontSize: 14.sp,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.6,
                          color: AppColors.goldDark,
                        ),
                      ),
                      SizedBox(height: 10.h),
                      Text(
                        page.title,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 18.sp,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primaryDark,
                        ),
                      ),
                      SizedBox(height: 10.h),
                      Text(
                        page.body,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 13.sp,
                          height: 1.45,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(_pages.length, (i) {
              final active = i == _page;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: EdgeInsets.symmetric(horizontal: 3.w),
                width: active ? 18.w : 6.w,
                height: 6.w,
                decoration: BoxDecoration(
                  color: active
                      ? AppColors.primaryDark
                      : AppColors.border,
                  borderRadius: BorderRadius.circular(8.r),
                ),
              );
            }),
          ),
          SizedBox(height: 16.h),
          LuxuryButton(
            text: _page == _pages.length - 1 ? 'ابدأ التصفح' : 'التالي',
            onPressed: () {
              if (_page < _pages.length - 1) {
                _controller.nextPage(
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOut,
                );
              } else {
                _finish();
              }
            },
          ),
        ],
      ),
    );
  }
}

class _OnboardingPage {
  final IconData icon;
  final String title;
  final String body;

  const _OnboardingPage({
    required this.icon,
    required this.title,
    required this.body,
  });
}
