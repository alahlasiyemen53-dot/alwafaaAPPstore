import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import '../constants/app_colors.dart';
import '../constants/app_strings.dart';

enum BrandLogoVariant { full, iconOnly, horizontal, badge }
enum BrandLogoTheme { light, dark }

/// BrandLogo renders the high-end Beauty & Elegance monogram ("B&e") with twin luxury ovals.
class BrandLogo extends StatelessWidget {
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
    final strokeColor = isDark ? AppColors.goldLight : AppColors.primaryDark;
    final subtextColor = isDark ? AppColors.gold : AppColors.textSecondary;

    final double baseWidth = size ?? (variant == BrandLogoVariant.badge ? 56.w : (variant == BrandLogoVariant.iconOnly ? 36.w : 48.w));
    final double baseHeight = baseWidth * 1.22;

    Widget monogramWidget = SizedBox(
      width: baseWidth,
      height: baseHeight,
      child: CustomPaint(
        painter: _MonogramPainter(
          strokeColor: strokeColor,
          textColor: primaryColor,
          isDark: isDark,
        ),
      ),
    );

    Widget content;

    switch (variant) {
      case BrandLogoVariant.iconOnly:
        content = monogramWidget;
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
              monogramWidget,
              SizedBox(height: 6.h),
              Text(
                AppStrings.brandSubtitle,
                textAlign: TextAlign.center,
                style: GoogleFonts.cormorantGaramond(
                  fontSize: 8.5.sp,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 2.2,
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
            monogramWidget,
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
                    letterSpacing: 1.2,
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
                    letterSpacing: 1.8,
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
            monogramWidget,
            SizedBox(height: 4.h),
            Text(
              AppStrings.brandSubtitle,
              textAlign: TextAlign.center,
              style: GoogleFonts.cormorantGaramond(
                fontSize: 10.sp,
                fontWeight: FontWeight.w600,
                letterSpacing: 2.8,
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

class _MonogramPainter extends CustomPainter {
  final Color strokeColor;
  final Color textColor;
  final bool isDark;

  _MonogramPainter({
    required this.strokeColor,
    required this.textColor,
    required this.isDark,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);

    // Outer subtle oval
    final outerPaint = Paint()
      ..color = strokeColor.withValues(alpha: isDark ? 0.6 : 0.4)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0;

    canvas.drawOval(
      Rect.fromCenter(center: center, width: size.width * 0.94, height: size.height * 0.94),
      outerPaint,
    );

    // Inner refined oval
    final innerPaint = Paint()
      ..color = strokeColor.withValues(alpha: isDark ? 0.9 : 0.8)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 0.8;

    canvas.drawOval(
      Rect.fromCenter(center: center, width: size.width * 0.86, height: size.height * 0.86),
      innerPaint,
    );

    // Main 'B' letter
    final textPainterB = TextPainter(
      text: TextSpan(
        text: 'B',
        style: TextStyle(
          fontFamily: 'serif',
          fontSize: size.height * 0.54,
          fontWeight: FontWeight.w400,
          color: textColor,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    textPainterB.paint(
      canvas,
      Offset(
        center.dx - (textPainterB.width / 2),
        center.dy - (textPainterB.height / 2) - (size.height * 0.08),
      ),
    );

    // Intertwined '&e'
    final textPainterAndE = TextPainter(
      text: TextSpan(
        text: '&e',
        style: TextStyle(
          fontFamily: 'serif',
          fontStyle: FontStyle.italic,
          fontSize: size.height * 0.28,
          fontWeight: FontWeight.w300,
          color: textColor,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    textPainterAndE.paint(
      canvas,
      Offset(
        center.dx - (textPainterAndE.width / 2) + (size.width * 0.08),
        center.dy + (size.height * 0.12),
      ),
    );
  }

  @override
  bool shouldRepaint(covariant _MonogramPainter oldDelegate) {
    return oldDelegate.strokeColor != strokeColor ||
        oldDelegate.textColor != textColor ||
        oldDelegate.isDark != isDark;
  }
}
