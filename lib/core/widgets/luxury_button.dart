import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';
import '../theme/app_typography.dart';
import 'skeletons/screen_skeletons.dart';

enum LuxuryButtonVariant { primary, secondary, outline, gold, text }

class LuxuryButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final LuxuryButtonVariant variant;
  final Widget? icon;
  final bool isLoading;
  final double? width;
  final double? height;
  final EdgeInsetsGeometry? padding;

  const LuxuryButton({
    super.key,
    required this.text,
    required this.onPressed,
    this.variant = LuxuryButtonVariant.primary,
    this.icon,
    this.isLoading = false,
    this.width,
    this.height,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    Color bgColor;
    Color textColor;
    BorderSide? border;

    switch (variant) {
      case LuxuryButtonVariant.primary:
        bgColor = AppColors.primaryDark;
        textColor = Colors.white;
        border = null;
        break;
      case LuxuryButtonVariant.secondary:
        bgColor = AppColors.surfaceMuted;
        textColor = AppColors.primaryDark;
        border = null;
        break;
      case LuxuryButtonVariant.outline:
        bgColor = Colors.transparent;
        textColor = AppColors.primaryDark;
        border = const BorderSide(color: AppColors.border, width: 1.2);
        break;
      case LuxuryButtonVariant.gold:
        bgColor = AppColors.gold;
        textColor = AppColors.primaryDark;
        border = null;
        break;
      case LuxuryButtonVariant.text:
        bgColor = Colors.transparent;
        textColor = AppColors.primaryDark;
        border = null;
        break;
    }

    final buttonContent = isLoading
        ? const ActionButtonSkeleton()
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                icon!,
                SizedBox(width: 8.w),
              ],
              Flexible(
                child: Text(
                  text,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.labelLarge.copyWith(
                    color: textColor,
                    fontSize: 13.sp,
                  ),
                ),
              ),
            ],
          );

    return SizedBox(
      width: width,
      height: height ?? 50.h,
      child: Material(
        color: bgColor,
        borderRadius: BorderRadius.circular(16.r),
        child: InkWell(
          onTap: isLoading ? null : onPressed,
          borderRadius: BorderRadius.circular(16.r),
          child: Container(
            padding: padding ??
                EdgeInsets.symmetric(horizontal: 18.w, vertical: 12.h),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16.r),
              border: border != null ? Border.fromBorderSide(border) : null,
              boxShadow: variant == LuxuryButtonVariant.primary
                  ? [
                      BoxShadow(
                        color: AppColors.primaryDark.withValues(alpha: 0.15),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      )
                    ]
                  : null,
            ),
            child: Center(child: buttonContent),
          ),
        ),
      ),
    );
  }
}
