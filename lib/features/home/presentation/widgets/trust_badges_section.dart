import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';

class TrustBadgesSection extends StatelessWidget {
  const TrustBadgesSection({super.key});

  @override
  Widget build(BuildContext context) {
    final items = [
      (
        Icons.verified_rounded,
        AppColors.gold,
        AppStrings.authenticGuaranteed,
        AppStrings.authenticDesc,
      ),
      (
        Icons.local_shipping_rounded,
        AppColors.burgundy,
        AppStrings.fastDelivery,
        AppStrings.fastDeliveryDesc,
      ),
      (
        Icons.inventory_2_rounded,
        AppColors.goldDark,
        AppStrings.luxuryPackaging,
        AppStrings.luxuryPackagingDesc,
      ),
    ];

    return Padding(
      padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 12.h),
      child: Row(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            if (i > 0) SizedBox(width: 8.w),
            Expanded(
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 12.h),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(16.r),
                  border: Border.all(color: AppColors.borderLight),
                ),
                child: Column(
                  children: [
                    Container(
                      width: 36.w,
                      height: 36.w,
                      decoration: BoxDecoration(
                        color: items[i].$2.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(items[i].$1, color: items[i].$2, size: 18.w),
                    ),
                    SizedBox(height: 8.h),
                    Text(
                      items[i].$3,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.titleSmall.copyWith(
                        fontSize: 10.5.sp,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark,
                        height: 1.2,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
