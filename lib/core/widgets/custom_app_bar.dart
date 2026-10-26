import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';
import '../constants/app_strings.dart';
import '../utils/currency_formatter.dart';
import 'brand_logo.dart';

class CustomAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String? activeCurrencySymbol;
  final String? activeCurrencyCode;
  final int cartCount;
  final int wishlistCount;
  final int supportUnreadCount;
  final VoidCallback? onCurrencyTap;
  final VoidCallback? onSearchTap;
  final VoidCallback? onCameraTap;
  final VoidCallback? onCartTap;
  final VoidCallback? onWishlistTap;
  final VoidCallback? onSupportTap;
  final VoidCallback? onLogoTap;
  final bool showSearch;
  final bool showBrandBar;
  final bool showCart;
  final bool showWishlist;

  const CustomAppBar({
    super.key,
    this.activeCurrencySymbol = 'ر.س',
    this.activeCurrencyCode = 'SAR',
    this.cartCount = 0,
    this.wishlistCount = 0,
    this.supportUnreadCount = 0,
    this.onCurrencyTap,
    this.onSearchTap,
    this.onCameraTap,
    this.onCartTap,
    this.onWishlistTap,
    this.onSupportTap,
    this.onLogoTap,
    this.showSearch = true,
    this.showBrandBar = true,
    this.showCart = false,
    this.showWishlist = false,
  });

  @override
  Size get preferredSize {
    double height = 28.h;
    if (showBrandBar) {
      height += 64.h;
    }
    if (showSearch) {
      height += 48.h;
    }
    if (showBrandBar || showSearch) {
      height += 1.0;
    }
    return Size.fromHeight(height);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white.withValues(alpha: 0.98),
      child: SafeArea(
        bottom: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Top Utility Ribbon
            Container(
              padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 4.h),
              color: AppColors.primaryDark,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      AppStrings.freeShippingNotice,
                      style: TextStyle(
                        fontSize: 9.sp,
                        fontWeight: FontWeight.w600,
                        color: const Color(0xFFEFE7DC),
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  GestureDetector(
                    onTap: onCurrencyTap,
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      padding:
                          EdgeInsets.symmetric(horizontal: 8.w, vertical: 2.h),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12.r),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.language_rounded,
                              size: 11.w, color: AppColors.gold),
                          SizedBox(width: 4.w),
                          Text(
                            '$activeCurrencyCode (${CurrencyFormatter.normalizeSymbol(activeCurrencySymbol ?? '')})',
                            style: TextStyle(
                              fontSize: 9.sp,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                            ),
                          ),
                          SizedBox(width: 2.w),
                          Icon(Icons.keyboard_arrow_down_rounded,
                              size: 12.w, color: Colors.white70),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Main Bar with Brand Logo & Action Buttons
            if (showBrandBar)
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 8.h),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    BrandLogo(
                      variant: BrandLogoVariant.horizontal,
                      size: 38.w,
                      onTap: onLogoTap,
                    ),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (onSupportTap != null) ...[
                          IconButton(
                            onPressed: onSupportTap,
                            tooltip: 'دعم الوفاء',
                            icon: Stack(
                              clipBehavior: Clip.none,
                              children: [
                                Container(
                                  padding: EdgeInsets.all(6.w),
                                  decoration: BoxDecoration(
                                    color: AppColors.surfaceMuted,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(Icons.support_agent_rounded,
                                      size: 18.w, color: AppColors.primaryDark),
                                ),
                                if (supportUnreadCount > 0)
                                  Positioned(
                                    top: -2,
                                    right: -2,
                                    child: Container(
                                      padding: EdgeInsets.all(3.w),
                                      decoration: const BoxDecoration(
                                        color: AppColors.discountBadge,
                                        shape: BoxShape.circle,
                                      ),
                                      constraints: BoxConstraints(
                                          minWidth: 14.w, minHeight: 14.w),
                                      child: Center(
                                        child: Text(
                                          supportUnreadCount > 9
                                              ? '9+'
                                              : '$supportUnreadCount',
                                          style: TextStyle(
                                            color: Colors.white,
                                            fontSize: 8.sp,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            visualDensity: VisualDensity.compact,
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                          SizedBox(width: 8.w),
                        ],
                        IconButton(
                          onPressed: onCameraTap,
                          icon: Container(
                            padding: EdgeInsets.all(6.w),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceMuted,
                              shape: BoxShape.circle,
                            ),
                            child: Icon(Icons.camera_alt_outlined,
                                size: 18.w, color: AppColors.primaryDark),
                          ),
                          visualDensity: VisualDensity.compact,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                        ),
                        if (showWishlist && onWishlistTap != null) ...[
                          SizedBox(width: 8.w),
                          IconButton(
                            onPressed: onWishlistTap,
                            icon: Stack(
                              clipBehavior: Clip.none,
                              children: [
                                Container(
                                  padding: EdgeInsets.all(6.w),
                                  decoration: BoxDecoration(
                                    color: AppColors.surfaceMuted,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(Icons.favorite_border_rounded,
                                      size: 18.w, color: AppColors.primaryDark),
                                ),
                                if (wishlistCount > 0)
                                  Positioned(
                                    top: -2,
                                    right: -2,
                                    child: Container(
                                      padding: EdgeInsets.all(3.w),
                                      decoration: const BoxDecoration(
                                        color: AppColors.discountBadge,
                                        shape: BoxShape.circle,
                                      ),
                                      constraints: BoxConstraints(
                                          minWidth: 14.w, minHeight: 14.w),
                                      child: Center(
                                        child: Text(
                                          '$wishlistCount',
                                          style: TextStyle(
                                            color: Colors.white,
                                            fontSize: 8.sp,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            visualDensity: VisualDensity.compact,
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                        if (showCart && onCartTap != null) ...[
                          SizedBox(width: 8.w),
                          IconButton(
                            onPressed: onCartTap,
                            icon: Stack(
                              clipBehavior: Clip.none,
                              children: [
                                Container(
                                  padding: EdgeInsets.all(6.w),
                                  decoration: const BoxDecoration(
                                    color: AppColors.primaryDark,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(Icons.shopping_bag_outlined,
                                      size: 18.w, color: Colors.white),
                                ),
                                if (cartCount > 0)
                                  Positioned(
                                    top: -2,
                                    right: -2,
                                    child: Container(
                                      padding: const EdgeInsets.all(3),
                                      decoration: const BoxDecoration(
                                        color: AppColors.gold,
                                        shape: BoxShape.circle,
                                      ),
                                      constraints: BoxConstraints(
                                          minWidth: 14.w, minHeight: 14.w),
                                      child: Center(
                                        child: Text(
                                          '$cartCount',
                                          style: TextStyle(
                                            color: AppColors.primaryDark,
                                            fontSize: 8.sp,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            visualDensity: VisualDensity.compact,
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),

            if (showSearch)
              Padding(
                padding: EdgeInsets.only(left: 14.w, right: 14.w, bottom: 8.h),
                child: GestureDetector(
                  onTap: onSearchTap,
                  child: Container(
                    padding:
                        EdgeInsets.symmetric(horizontal: 14.w, vertical: 8.h),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(14.r),
                      border: Border.all(color: AppColors.border, width: 0.8),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.search_rounded,
                            size: 18.w, color: AppColors.textMuted),
                        SizedBox(width: 8.w),
                        Expanded(
                          child: Text(
                            AppStrings.searchPlaceholder,
                            style: TextStyle(
                              fontSize: 11.sp,
                              color: AppColors.textMuted,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        Container(
                          padding: EdgeInsets.all(4.w),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8.r),
                            border:
                                Border.all(color: AppColors.border, width: 0.6),
                          ),
                          child: Icon(Icons.tune_rounded,
                              size: 14.w, color: AppColors.primaryDark),
                        ),
                      ],
                    ),
                  ),
                ),
              ),

            if (showBrandBar || showSearch)
              const Divider(height: 1, color: AppColors.borderLight),
          ],
        ),
      ),
    );
  }
}
