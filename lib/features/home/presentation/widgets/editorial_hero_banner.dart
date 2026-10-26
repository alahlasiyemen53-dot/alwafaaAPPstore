import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
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

    final tagText = hasBanner
        ? (banner!.badgeText.trim().isNotEmpty ? banner!.badgeText.trim() : null)
        : AppStrings.heroTag;
    final titleText =
        hasBanner ? banner!.displayTitle.trim() : AppStrings.heroTitle;
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

    return GestureDetector(
      onTap: _handlePrimaryAction,
      child: Container(
        width: expandToParent ? double.infinity : null,
        height: expandToParent ? double.infinity : null,
        margin: expandToParent
            ? EdgeInsets.symmetric(horizontal: 12.w)
            : EdgeInsets.fromLTRB(12.w, 4.h, 12.w, 0),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22.r),
          boxShadow: [
            BoxShadow(
              color: AppColors.gold.withValues(alpha: 0.18),
              blurRadius: 24,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(22.r),
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (hasImage)
                CachedImageWidget(
                  imageUrl: banner!.imageUrl,
                  fit: BoxFit.cover,
                )
              else
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        AppColors.primaryDark,
                        Color(0xFF1A3A55),
                        AppColors.goldDark,
                      ],
                    ),
                  ),
                ),
              // Medical slate veil — readable Arabic type over imagery
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.centerRight,
                    end: Alignment.centerLeft,
                    colors: [
                      AppColors.primaryDark.withValues(alpha: hasImage ? 0.92 : 0.55),
                      AppColors.primaryDark.withValues(alpha: hasImage ? 0.55 : 0.25),
                      AppColors.goldDark.withValues(alpha: hasImage ? 0.35 : 0.15),
                    ],
                    stops: const [0.0, 0.55, 1.0],
                  ),
                ),
              ),
              // Soft top highlight
              Positioned(
                top: -40.h,
                left: -20.w,
                child: Container(
                  width: 160.w,
                  height: 160.w,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.gold.withValues(alpha: 0.12),
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(18.w, 20.h, 18.w, 18.h),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8.r),
                          child: Image.asset(
                            'assets/images/app_icon.png',
                            width: 28.w,
                            height: 28.w,
                            fit: BoxFit.cover,
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Text(
                          AppStrings.brandShort,
                          style: GoogleFonts.cormorantGaramond(
                            fontSize: 14.sp,
                            fontWeight: FontWeight.w700,
                            color: Colors.white.withValues(alpha: 0.92),
                            letterSpacing: 0.4,
                          ),
                        ),
                        const Spacer(),
                        if (tagText != null)
                          Container(
                            padding: EdgeInsets.symmetric(
                              horizontal: 10.w,
                              vertical: 4.h,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.burgundy.withValues(alpha: 0.92),
                              borderRadius: BorderRadius.circular(20.r),
                            ),
                            child: Text(
                              tagText,
                              style: TextStyle(
                                fontSize: 9.5.sp,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                              ),
                            ),
                          ),
                      ],
                    ),
                    SizedBox(height: 14.h),
                    if (titleText.isNotEmpty)
                      Text(
                        titleText,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.cormorantGaramond(
                          fontSize: 24.sp,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          height: 1.15,
                        ),
                      ),
                    if (descText != null) ...[
                      SizedBox(height: 8.h),
                      Text(
                        descText,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 12.sp,
                          color: Colors.white.withValues(alpha: 0.88),
                          height: 1.4,
                        ),
                      ),
                    ],
                    SizedBox(height: 16.h),
                    Row(
                      children: [
                        Flexible(
                          child: ElevatedButton(
                            onPressed: _handlePrimaryAction,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.burgundy,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14.r),
                              ),
                              padding: EdgeInsets.symmetric(
                                horizontal: 16.w,
                                vertical: 11.h,
                              ),
                            ),
                            child: Text(
                              ctaText,
                              style: TextStyle(
                                fontSize: 12.sp,
                                fontWeight: FontWeight.w800,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                        SizedBox(width: 10.w),
                        Container(
                          width: 42.w,
                          height: 42.w,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.16),
                            borderRadius: BorderRadius.circular(14.r),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.22),
                            ),
                          ),
                          child: Icon(
                            Icons.arrow_back_rounded,
                            size: 20.w,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
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
