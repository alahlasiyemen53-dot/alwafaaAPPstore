import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';

enum LuxuryBadgeType { exclusive, discount, bestSeller, isNew, vip }

class LuxuryBadge extends StatelessWidget {
  final String text;
  final LuxuryBadgeType type;
  final Widget? icon;

  const LuxuryBadge({
    super.key,
    required this.text,
    this.type = LuxuryBadgeType.exclusive,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    Color bgColor;
    Color textColor;
    Border? border;

    switch (type) {
      case LuxuryBadgeType.exclusive:
        bgColor = AppColors.primaryDark;
        textColor = AppColors.textLight;
        border = Border.all(color: AppColors.primaryLight, width: 0.8);
        break;
      case LuxuryBadgeType.discount:
        bgColor = AppColors.discountBadge;
        textColor = AppColors.textLight;
        border = null;
        break;
      case LuxuryBadgeType.bestSeller:
        bgColor = AppColors.gold.withValues(alpha: 0.18);
        textColor = AppColors.goldDark;
        border = Border.all(color: AppColors.gold.withValues(alpha: 0.45), width: 0.8);
        break;
      case LuxuryBadgeType.isNew:
        bgColor = AppColors.success.withValues(alpha: 0.12);
        textColor = AppColors.success;
        border = Border.all(color: AppColors.success.withValues(alpha: 0.3), width: 0.8);
        break;
      case LuxuryBadgeType.vip:
        bgColor = AppColors.gold.withValues(alpha: 0.22);
        textColor = AppColors.goldDark;
        border = Border.all(color: AppColors.gold.withValues(alpha: 0.5), width: 0.8);
        break;
    }

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(20.r),
        border: border,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (icon != null) ...[
            icon!,
            SizedBox(width: 4.w),
          ],
          Text(
            text,
            style: TextStyle(
              fontSize: 9.5.sp,
              fontWeight: FontWeight.w700,
              color: textColor,
              height: 1.1,
            ),
          ),
        ],
      ),
    );
  }
}
