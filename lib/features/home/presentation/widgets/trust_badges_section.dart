import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';

class TrustBadgesSection extends StatelessWidget {
  const TrustBadgesSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(24.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        children: [
          _buildItem(
            icon: Icons.verified_rounded,
            iconColor: AppColors.gold,
            title: AppStrings.authenticGuaranteed,
            subtitle: AppStrings.authenticDesc,
          ),
          Divider(color: AppColors.border, height: 20.h),
          _buildItem(
            icon: Icons.local_shipping_rounded,
            iconColor: AppColors.primaryDark,
            title: AppStrings.fastDelivery,
            subtitle: AppStrings.fastDeliveryDesc,
          ),
          Divider(color: AppColors.border, height: 20.h),
          _buildItem(
            icon: Icons.card_giftcard_rounded,
            iconColor: AppColors.goldDark,
            title: AppStrings.luxuryPackaging,
            subtitle: AppStrings.luxuryPackagingDesc,
          ),
        ],
      ),
    );
  }

  Widget _buildItem({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
  }) {
    return Row(
      children: [
        Container(
          width: 42.w,
          height: 42.w,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14.r),
            border: Border.all(color: AppColors.border, width: 0.8),
          ),
          child: Icon(icon, color: iconColor, size: 22.w),
        ),
        SizedBox(width: 14.w),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: AppTypography.titleSmall.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
              SizedBox(height: 2.h),
              Text(
                subtitle,
                style: AppTypography.bodySmall.copyWith(color: AppColors.textMuted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
