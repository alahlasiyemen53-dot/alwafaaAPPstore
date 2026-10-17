import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';

class RatingStars extends StatelessWidget {
  final double rating;
  final int? reviewsCount;
  final double? starSize;

  const RatingStars({
    super.key,
    required this.rating,
    this.reviewsCount,
    this.starSize,
  });

  @override
  Widget build(BuildContext context) {
    final size = starSize ?? 12.w;

    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Icon(
          Icons.star_rounded,
          color: AppColors.ratingStar,
          size: size,
        ),
        SizedBox(width: 3.w),
        Text(
          rating.toStringAsFixed(1),
          style: TextStyle(
            fontSize: 11.sp,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
            height: 1.0,
          ),
        ),
        if (reviewsCount != null) ...[
          SizedBox(width: 2.w),
          Text(
            '($reviewsCount)',
            style: TextStyle(
              fontSize: 10.sp,
              color: AppColors.textMuted,
              height: 1.0,
            ),
          ),
        ],
      ],
    );
  }
}
