import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/catalog/brand_api_model.dart';

/// Brand logo + name. Compact for home rails; [expanded] fills a grid cell.
class BrandCard extends StatelessWidget {
  final BrandApiModel brand;
  final VoidCallback onTap;
  final double size;
  final bool expanded;

  const BrandCard({
    super.key,
    required this.brand,
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
          child: Column(
            children: [
              Expanded(child: _LogoTile(brand: brand, padding: 14.w)),
              SizedBox(height: 8.h),
              Text(
                brand.displayName,
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
      );
    }

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: size.w + 4.w,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: size.w,
              height: size.w,
              child: _LogoTile(brand: brand, padding: 6.w),
            ),
            SizedBox(height: 6.h),
            Text(
              brand.displayName,
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

class _LogoTile extends StatelessWidget {
  final BrandApiModel brand;
  final double padding;

  const _LogoTile({required this.brand, required this.padding});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      padding: EdgeInsets.all(padding),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18.r),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryDark.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: brand.logoUrl != null
          ? ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: CachedImageWidget(
                imageUrl: brand.logoUrl!,
                fit: BoxFit.contain,
              ),
            )
          : _BrandInitial(name: brand.displayName),
    );
  }
}

class _BrandInitial extends StatelessWidget {
  final String name;

  const _BrandInitial({required this.name});

  @override
  Widget build(BuildContext context) {
    final trimmed = name.trim();
    final initial = trimmed.isEmpty ? '؟' : trimmed[0];
    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.surfaceMuted,
        borderRadius: BorderRadius.circular(12.r),
      ),
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
