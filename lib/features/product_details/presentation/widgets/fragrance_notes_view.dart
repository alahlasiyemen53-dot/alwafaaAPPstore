import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/data/models/product_model.dart';

class FragranceNotesView extends StatelessWidget {
  final FragranceNotes notes;

  const FragranceNotesView({super.key, required this.notes});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.auto_awesome, size: 18.w, color: AppColors.goldDark),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  AppStrings.fragranceNotes,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.titleMedium.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          if (notes.top != null)
            _buildNoteRow(
              title: AppStrings.topNotes,
              content: notes.top!,
              badgeColor: AppColors.gold.withValues(alpha: 0.2),
            ),
          if (notes.heart != null) ...[
            SizedBox(height: 8.h),
            _buildNoteRow(
              title: AppStrings.heartNotes,
              content: notes.heart!,
              badgeColor: AppColors.primaryDark.withValues(alpha: 0.1),
            ),
          ],
          if (notes.base != null) ...[
            SizedBox(height: 8.h),
            _buildNoteRow(
              title: AppStrings.baseNotes,
              content: notes.base!,
              badgeColor: AppColors.primaryLight.withValues(alpha: 0.15),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildNoteRow({
    required String title,
    required String content,
    required Color badgeColor,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
          decoration: BoxDecoration(
            color: badgeColor,
            borderRadius: BorderRadius.circular(8.r),
          ),
          child: Text(
            title,
            style: TextStyle(
              fontSize: 10.sp,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryDark,
            ),
          ),
        ),
        SizedBox(width: 10.w),
        Expanded(
          child: Text(
            content,
            style: TextStyle(
              fontSize: 11.5.sp,
              color: AppColors.textPrimary,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}
