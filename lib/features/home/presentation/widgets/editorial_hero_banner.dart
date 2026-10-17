import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/widgets/brand_logo.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import '../../../../data/models/catalog/home_api_model.dart';

class EditorialHeroBanner extends StatelessWidget {
  final HomeBannerModel? banner;
  final VoidCallback onExploreTap;
  final ValueChanged<HomeBannerModel>? onBannerTap;

  /// When true, fills the parent (used inside the auto-slider PageView).
  final bool expandToParent;

  const EditorialHeroBanner({
    super.key,
    this.banner,
    required this.onExploreTap,
    this.onBannerTap,
    this.expandToParent = false,
  });

  @override
  Widget build(BuildContext context) {
    final hasBanner = banner != null;
    final hasImage = hasBanner && banner!.imageUrl.isNotEmpty;

    // When a server banner is present, never fall back to hardcoded marketing copy.
    final tagText = hasBanner
        ? (banner!.badgeText.trim().isNotEmpty ? banner!.badgeText.trim() : null)
        : AppStrings.heroTag;
    final titleText = hasBanner
        ? banner!.displayTitle.trim()
        : AppStrings.heroTitle;
    final descText = hasBanner
        ? (banner!.displaySubtitle.trim().isNotEmpty
            ? banner!.displaySubtitle.trim()
            : null)
        : AppStrings.heroDesc;
    final ctaText = hasBanner
        ? (banner!.displayCta.trim().isNotEmpty
            ? banner!.displayCta.trim()
            : AppStrings.explorePerfumes)
        : AppStrings.explorePerfumes;

    final card = GestureDetector(
      onTap: _handlePrimaryAction,
      child: Container(
        width: expandToParent ? double.infinity : null,
        height: expandToParent ? double.infinity : null,
        margin: expandToParent
            ? EdgeInsets.symmetric(horizontal: 16.w)
            : EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
        decoration: BoxDecoration(
          color: AppColors.primaryDark,
          borderRadius: BorderRadius.circular(28.r),
          border: Border.all(color: AppColors.primaryLight, width: 1.2),
          boxShadow: [
            BoxShadow(
              color: AppColors.primaryDark.withValues(alpha: 0.25),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            if (hasImage)
              Positioned.fill(
                child: CachedImageWidget(
                  imageUrl: banner!.imageUrl,
                  fit: BoxFit.cover,
                ),
              ),
            Positioned.fill(
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      AppColors.primaryDark
                          .withValues(alpha: hasImage ? 0.88 : 0.98),
                      AppColors.primaryDark
                          .withValues(alpha: hasImage ? 0.70 : 0.90),
                    ],
                    begin: Alignment.topRight,
                    end: Alignment.bottomLeft,
                  ),
                ),
              ),
            ),
            if (!hasImage)
              Positioned(
                left: -24.w,
                bottom: -24.h,
                child: Opacity(
                  opacity: 0.12,
                  child: BrandLogo(
                    variant: BrandLogoVariant.iconOnly,
                    logoTheme: BrandLogoTheme.dark,
                    size: 130.w,
                  ),
                ),
              ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 22.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: expandToParent
                    ? MainAxisAlignment.center
                    : MainAxisAlignment.start,
                children: [
                  if (tagText != null) ...[
                    Container(
                      padding:
                          EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(20.r),
                        border: Border.all(
                            color: Colors.white.withValues(alpha: 0.15)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.auto_awesome,
                              size: 12.w, color: AppColors.gold),
                          SizedBox(width: 6.w),
                          Text(
                            tagText,
                            style: TextStyle(
                              fontSize: 9.5.sp,
                              fontWeight: FontWeight.w600,
                              color: AppColors.goldLight,
                            ),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 12.h),
                  ],
                  if (titleText.isNotEmpty)
                    Text(
                      titleText,
                      maxLines: expandToParent ? 2 : null,
                      overflow: expandToParent
                          ? TextOverflow.ellipsis
                          : TextOverflow.clip,
                      style: GoogleFonts.cormorantGaramond(
                        fontSize: 22.sp,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textLight,
                        height: 1.2,
                      ),
                    ),
                  if (descText != null) ...[
                    SizedBox(height: 8.h),
                    Text(
                      descText,
                      maxLines: expandToParent ? 2 : null,
                      overflow: expandToParent
                          ? TextOverflow.ellipsis
                          : TextOverflow.clip,
                      style: TextStyle(
                        fontSize: 11.5.sp,
                        color: AppColors.goldLight.withValues(alpha: 0.9),
                        height: 1.4,
                      ),
                    ),
                  ],
                  SizedBox(height: 16.h),
                  ElevatedButton.icon(
                    onPressed: _handlePrimaryAction,
                    icon: Icon(Icons.arrow_forward_rounded, size: 14.w),
                    label: Text(
                      ctaText,
                      style: TextStyle(
                          fontSize: 11.5.sp, fontWeight: FontWeight.bold),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.surface,
                      foregroundColor: AppColors.primaryDark,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14.r),
                      ),
                      padding: EdgeInsets.symmetric(
                          horizontal: 14.w, vertical: 10.h),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );

    return card;
  }

  void _handlePrimaryAction() {
    final current = banner;
    if (current != null &&
        current.hasNavigableDestination &&
        onBannerTap != null) {
      onBannerTap!(current);
      return;
    }
    onExploreTap();
  }
}
