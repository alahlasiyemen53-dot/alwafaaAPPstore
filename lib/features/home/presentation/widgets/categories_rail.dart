import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/features/home/presentation/widgets/home_section_header.dart';

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
        HomeSectionHeader(
          title: AppStrings.browseCategories,
          onViewAll: onViewAll,
        ),
        SizedBox(height: 12.h),
        SizedBox(
          height: 96.w,
          child: ListView.separated(
            clipBehavior: Clip.none,
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            scrollDirection: Axis.horizontal,
            itemCount: categories.length,
            separatorBuilder: (_, _) => SizedBox(width: 12.w),
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
                  width: 78.w,
                  child: Column(
                    children: [
                      Container(
                        width: 68.w,
                        height: 68.w,
                        padding: EdgeInsets.all(3.w),
                        decoration: BoxDecoration(
                          color: AppColors.surface,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppColors.border,
                            width: 1.2,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.gold.withValues(alpha: 0.10),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: ClipOval(
                          child: ColoredBox(
                            color: AppColors.surfaceSoft,
                            child: CachedImageWidget(
                              imageUrl: cat.image,
                              fit: BoxFit.cover,
                            ),
                          ),
                        ),
                      ),
                      SizedBox(height: 8.h),
                      Text(
                        cat.nameAr,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11.sp,
                          height: 1.15,
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
