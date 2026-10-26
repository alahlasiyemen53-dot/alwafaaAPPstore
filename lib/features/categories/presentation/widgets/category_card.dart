import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/category_model.dart';

/// Category image + name. Compact for home rails; [expanded] fills a grid cell.
class CategoryCard extends StatelessWidget {
  final CategoryModel category;
  final VoidCallback onTap;
  final double size;
  final bool expanded;

  const CategoryCard({
    super.key,
    required this.category,
    required this.onTap,
    this.size = 72,
    this.expanded = false,
  });

  @override
  Widget build(BuildContext context) {
    if (expanded) {
      return Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18.r),
          child: Padding(
            // Keeps border/shadow inside the grid cell so edges are not clipped.
            padding: EdgeInsets.fromLTRB(2.w, 2.h, 2.w, 0),
            child: Column(
              children: [
                Expanded(child: _ImageTile(category: category)),
                SizedBox(height: 8.h),
                Text(
                  category.nameAr,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 11.5.sp,
                    height: 1.25,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ),
      );
    }

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: size.w + 8.w,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: size.w,
              height: size.w,
              child: _ImageTile(category: category),
            ),
            SizedBox(height: 6.h),
            Text(
              category.nameAr,
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
  }
}

class _ImageTile extends StatelessWidget {
  final CategoryModel category;

  const _ImageTile({required this.category});

  static const double _radius = 18;
  static const double _inset = 3;

  @override
  Widget build(BuildContext context) {
    final hasVisual = category.image.trim().isNotEmpty;
    final outerRadius = _radius.r;
    final innerRadius = (_radius - _inset).r;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(outerRadius),
        border: Border.all(
          color: AppColors.border,
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryDark.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: EdgeInsets.all(_inset.w),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(innerRadius),
          clipBehavior: Clip.antiAlias,
          child: hasVisual
              ? CachedImageWidget(
                  imageUrl: category.image,
                  fit: BoxFit.cover,
                )
              : _CategoryInitial(name: category.nameAr),
        ),
      ),
    );
  }
}

class _CategoryInitial extends StatelessWidget {
  final String name;

  const _CategoryInitial({required this.name});

  @override
  Widget build(BuildContext context) {
    final trimmed = name.trim();
    final initial = trimmed.isEmpty ? '؟' : trimmed[0];
    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: Center(
        child: Text(
          initial,
          style: TextStyle(
            fontSize: 22.sp,
            fontWeight: FontWeight.w700,
            color: AppColors.goldDark,
          ),
        ),
      ),
    );
  }
}
