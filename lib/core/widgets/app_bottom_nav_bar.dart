import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';
import '../constants/app_strings.dart';

enum BottomNavStyle { floating, classic }

class AppBottomNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final int cartCount;
  final int wishlistCount;
  final BottomNavStyle style;

  const AppBottomNavBar({
    super.key,
    required this.currentIndex,
    required this.onTap,
    this.cartCount = 0,
    this.wishlistCount = 0,
    this.style = BottomNavStyle.classic,
  });

  @override
  Widget build(BuildContext context) {
    final items = [
      _NavItem(icon: Icons.home_filled, label: AppStrings.navHome),
      _NavItem(icon: Icons.grid_view_rounded, label: AppStrings.navCatalog),
      _NavItem(
          icon: Icons.favorite_rounded,
          label: AppStrings.navWishlist,
          badge: wishlistCount),
      _NavItem(
          icon: Icons.shopping_bag_rounded,
          label: AppStrings.navCart,
          badge: cartCount),
      _NavItem(icon: Icons.person_rounded, label: AppStrings.navAccount),
    ];

    if (style == BottomNavStyle.floating) {
      return SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(left: 16.w, right: 16.w, bottom: 8.h),
          child: Material(
            color: AppColors.primaryDark.withValues(alpha: 0.96),
            elevation: 8,
            shadowColor: AppColors.primaryDark.withValues(alpha: 0.35),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(28.r),
              side: BorderSide(color: AppColors.primaryLight, width: 1.2),
            ),
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: 4.w, vertical: 4.h),
              child: Row(
                children: List.generate(items.length, (index) {
                  return Expanded(
                    child: _NavBarItem(
                      item: items[index],
                      isSelected: currentIndex == index,
                      onTap: () => onTap(index),
                      selectedColor: AppColors.gold,
                      unselectedColor: AppColors.textMuted,
                      labelColorWhenSelected: AppColors.textLight,
                      showSelectionDot: true,
                      compact: true,
                    ),
                  );
                }),
              ),
            ),
          ),
        ),
      );
    }

    // Classic style
    return Material(
      color: AppColors.surface,
      elevation: 4,
      shadowColor: Colors.black.withValues(alpha: 0.04),
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: AppColors.border, width: 0.8)),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 4.h),
            child: Row(
              children: List.generate(items.length, (index) {
                return Expanded(
                  child: _NavBarItem(
                    item: items[index],
                    isSelected: currentIndex == index,
                    onTap: () => onTap(index),
                    selectedColor: AppColors.primaryDark,
                    unselectedColor: AppColors.textMuted,
                    labelColorWhenSelected: AppColors.primaryDark,
                    showSelectionDot: false,
                    compact: false,
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavBarItem extends StatelessWidget {
  final _NavItem item;
  final bool isSelected;
  final VoidCallback onTap;
  final Color selectedColor;
  final Color unselectedColor;
  final Color labelColorWhenSelected;
  final bool showSelectionDot;
  final bool compact;

  const _NavBarItem({
    required this.item,
    required this.isSelected,
    required this.onTap,
    required this.selectedColor,
    required this.unselectedColor,
    required this.labelColorWhenSelected,
    required this.showSelectionDot,
    required this.compact,
  });

  @override
  Widget build(BuildContext context) {
    final badgeText = (item.badge != null && item.badge! > 0)
        ? (item.badge! > 99 ? '99+' : '${item.badge}')
        : null;

    return Semantics(
      button: true,
      selected: isSelected,
      label: badgeText != null
          ? '${item.label}، $badgeText'
          : item.label,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20.r),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Padding(
            padding: EdgeInsets.symmetric(
              horizontal: compact ? 4.w : 6.w,
              vertical: compact ? 4.h : 6.h,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  alignment: Alignment.center,
                  children: [
                    Icon(
                      item.icon,
                      size: compact ? 20.w : 22.w,
                      color: isSelected ? selectedColor : unselectedColor,
                    ),
                    if (badgeText != null)
                      Positioned(
                        top: -6,
                        right: -8,
                        child: Container(
                          padding: EdgeInsets.symmetric(
                              horizontal: 4.w, vertical: 2.h),
                          decoration: BoxDecoration(
                            color: AppColors.gold,
                            borderRadius: BorderRadius.circular(10.r),
                          ),
                          constraints:
                              BoxConstraints(minWidth: 18.w, minHeight: 16.w),
                          child: Center(
                            child: Text(
                              badgeText,
                              style: TextStyle(
                                color: AppColors.primaryDark,
                                fontSize: 9.sp,
                                fontWeight: FontWeight.w900,
                                height: 1,
                              ),
                            ),
                          ),
                        ),
                      ),
                    if (showSelectionDot && isSelected)
                      Positioned(
                        bottom: -5,
                        child: Container(
                          width: 4.w,
                          height: 4.w,
                          decoration: const BoxDecoration(
                            color: AppColors.gold,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ),
                  ],
                ),
                SizedBox(height: compact ? 2.h : 3.h),
                Text(
                  item.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: compact ? 9.sp : 10.sp,
                    fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                    color:
                        isSelected ? labelColorWhenSelected : unselectedColor,
                    height: 1.1,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem {
  final IconData icon;
  final String label;
  final int? badge;

  _NavItem({required this.icon, required this.label, this.badge});
}
