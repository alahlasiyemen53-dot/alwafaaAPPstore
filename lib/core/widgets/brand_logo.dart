import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import '../constants/app_colors.dart';
import '../constants/app_strings.dart';

enum BrandLogoVariant { full, iconOnly, horizontal, badge }
enum BrandLogoTheme { light, dark }

/// BrandLogo renders the AL-Wafa'a Store mark from [assets/images/app_icon.png].
class BrandLogo extends StatelessWidget {
  static const String _assetPath = 'assets/images/app_icon.png';

  final BrandLogoVariant variant;
  final BrandLogoTheme logoTheme;
  final double? size;
  final VoidCallback? onTap;

  const BrandLogo({
    super.key,
    this.variant = BrandLogoVariant.full,
    this.logoTheme = BrandLogoTheme.light,
    this.size,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = logoTheme == BrandLogoTheme.dark;
    final primaryColor = isDark ? AppColors.textLight : AppColors.primaryDark;
    final subtextColor = isDark ? AppColors.goldLight : AppColors.textSecondary;

    final double iconSize = size ??
        (variant == BrandLogoVariant.badge
            ? 56.w
            : (variant == BrandLogoVariant.iconOnly ? 36.w : 40.w));

    final Widget mark = ClipRRect(
      borderRadius: BorderRadius.circular(iconSize * 0.18),
      child: Image.asset(
        _assetPath,
        width: iconSize,
        height: iconSize,
        fit: BoxFit.cover,
        filterQuality: FilterQuality.high,
      ),
    );

    Widget content;

    switch (variant) {
      case BrandLogoVariant.iconOnly:
        content = mark;
        break;

      case BrandLogoVariant.badge:
        content = Container(
          padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 12.h),
          decoration: BoxDecoration(
            color: AppColors.primaryDark,
            borderRadius: BorderRadius.circular(20.r),
            border: Border.all(color: AppColors.primaryLight, width: 1.2),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.18),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              mark,
              SizedBox(height: 6.h),
              Text(
                AppStrings.brandSubtitle,
                textAlign: TextAlign.center,
                style: GoogleFonts.cormorantGaramond(
                  fontSize: 8.5.sp,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 1.6,
                  color: AppColors.goldLight,
                ),
              ),
            ],
          ),
        );
        break;

      case BrandLogoVariant.horizontal:
        content = Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            mark,
            SizedBox(width: 8.w),
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  AppStrings.brandShort,
                  style: GoogleFonts.cormorantGaramond(
                    fontSize: 16.sp,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.6,
                    color: primaryColor,
                    height: 1.0,
                  ),
                ),
                SizedBox(height: 2.h),
                Text(
                  AppStrings.brandSubtitle,
                  style: GoogleFonts.cormorantGaramond(
                    fontSize: 8.sp,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1.2,
                    color: subtextColor,
                  ),
                ),
              ],
            ),
          ],
        );
        break;

      case BrandLogoVariant.full:
        content = Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            mark,
            SizedBox(height: 6.h),
            Text(
              AppStrings.brandSubtitle,
              textAlign: TextAlign.center,
              style: GoogleFonts.cormorantGaramond(
                fontSize: 10.sp,
                fontWeight: FontWeight.w600,
                letterSpacing: 1.6,
                color: primaryColor,
              ),
            ),
          ],
        );
        break;
    }

    if (onTap != null) {
      return GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: content,
      );
    }

    return content;
  }
}
