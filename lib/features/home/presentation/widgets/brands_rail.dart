import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/data/models/catalog/brand_api_model.dart';
import 'package:store/features/brands/presentation/widgets/brand_card.dart';

class BrandsRail extends StatelessWidget {
  final List<BrandApiModel> brands;
  final ValueChanged<BrandApiModel> onSelectBrand;
  final VoidCallback onViewAll;

  const BrandsRail({
    super.key,
    required this.brands,
    required this.onSelectBrand,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 16.w),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'العلامات التجارية',
                style: AppTypography.titleMedium.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
              GestureDetector(
                onTap: onViewAll,
                child: Row(
                  children: [
                    Text(
                      'عرض الكل',
                      style: TextStyle(
                        fontSize: 11.sp,
                        fontWeight: FontWeight.w600,
                        color: AppColors.goldDark,
                      ),
                    ),
                    Icon(Icons.chevron_right_rounded, size: 16.w, color: AppColors.goldDark),
                  ],
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: 8.h),
        SizedBox(
          height: 72.w + 8.h + 18.h,
          child: ListView.separated(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            scrollDirection: Axis.horizontal,
            itemCount: brands.length,
            separatorBuilder: (_, __) => SizedBox(width: 12.w),
            itemBuilder: (context, index) {
              final brand = brands[index];
              return BrandCard(brand: brand, onTap: () => onSelectBrand(brand));
            },
          ),
        ),
      ],
    );
  }
}
