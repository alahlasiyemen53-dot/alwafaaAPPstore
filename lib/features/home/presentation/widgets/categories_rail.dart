import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/category_model.dart';

class CategoriesRail extends StatelessWidget {
  final List<CategoryModel> categories;
  final ValueChanged<ProductCategory> onSelectCategory;
  final ValueChanged<CategoryModel>? onSelectCategoryModel;
  final VoidCallback onViewAll;

  const CategoriesRail({
    super.key,
    required this.categories,
    required this.onSelectCategory,
    this.onSelectCategoryModel,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Title Bar
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 16.w),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                AppStrings.browseCategories,
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
                      AppStrings.viewAll,
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

        // Horizontal Rail — snug to content with a small layout buffer
        SizedBox(
          height: 72.w + 8.h + 18.h,
          child: ListView.separated(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            scrollDirection: Axis.horizontal,
            itemCount: categories.length,
            separatorBuilder: (_, __) => SizedBox(width: 12.w),
            itemBuilder: (context, index) {
              final cat = categories[index];

              return GestureDetector(
                onTap: () {
                  if (onSelectCategoryModel != null) {
                    onSelectCategoryModel!(cat);
                  } else {
                    onSelectCategory(cat.id);
                  }
                },
                child: SizedBox(
                  width: 76.w,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 72.w,
                        height: 72.w,
                        padding: EdgeInsets.all(2.w),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceMuted,
                          borderRadius: BorderRadius.circular(20.r),
                          border: Border.all(color: AppColors.border, width: 1.0),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.04),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(18.r),
                          child: CachedImageWidget(
                            imageUrl: cat.image,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      SizedBox(height: 6.h),
                      Text(
                        cat.nameAr,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11.sp,
                          height: 1.2,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
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
