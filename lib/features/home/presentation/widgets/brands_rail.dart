import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/catalog/brand_api_model.dart';
import 'package:store/features/home/presentation/widgets/home_section_header.dart';

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
        HomeSectionHeader(
          title: 'العلامات التجارية',
          onViewAll: onViewAll,
        ),
        SizedBox(height: 12.h),
        SizedBox(
          height: 88.w,
          child: ListView.separated(
            clipBehavior: Clip.none,
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            scrollDirection: Axis.horizontal,
            itemCount: brands.length,
            separatorBuilder: (_, _) => SizedBox(width: 10.w),
            itemBuilder: (context, index) {
              final brand = brands[index];
              return GestureDetector(
                onTap: () => onSelectBrand(brand),
                child: Container(
                  width: 118.w,
                  padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 10.h),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(16.r),
                    border: Border.all(color: AppColors.border, width: 1),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primaryDark.withValues(alpha: 0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 40.w,
                        height: 40.w,
                        padding: EdgeInsets.all(4.w),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceSoft,
                          borderRadius: BorderRadius.circular(12.r),
                        ),
                        child: brand.logoUrl != null
                            ? CachedImageWidget(
                                imageUrl: brand.logoUrl!,
                                fit: BoxFit.contain,
                              )
                            : Center(
                                child: Text(
                                  brand.displayName.isNotEmpty
                                      ? brand.displayName[0]
                                      : '؟',
                                  style: TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.goldDark,
                                    fontSize: 14.sp,
                                  ),
                                ),
                              ),
                      ),
                      SizedBox(width: 8.w),
                      Expanded(
                        child: Text(
                          brand.displayName,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 11.sp,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                            height: 1.2,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
