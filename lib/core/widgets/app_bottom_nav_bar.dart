import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';
import '../constants/app_strings.dart';
import 'cart_motion.dart';

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

  List<_NavItem> get _items => [
        _NavItem(icon: Icons.home_rounded, label: AppStrings.navHome),
        _NavItem(icon: Icons.grid_view_rounded, label: AppStrings.navCatalog),
        _NavItem(
          icon: Icons.favorite_rounded,
          label: AppStrings.navWishlist,
          badge: wishlistCount,
          isWishlist: true,
        ),
        _NavItem(
          icon: Icons.shopping_bag_rounded,
          label: AppStrings.navCart,
          badge: cartCount,
          isCart: true,
        ),
        _NavItem(icon: Icons.person_rounded, label: AppStrings.navAccount),
      ];

  @override
  Widget build(BuildContext context) {
    if (style == BottomNavStyle.floating) {
      return _FloatingBar(
        items: _items,
        currentIndex: currentIndex,
        onTap: onTap,
      );
    }
    return _ClassicBar(
      items: _items,
      currentIndex: currentIndex,
      onTap: onTap,
    );
  }
}

class _ClassicBar extends StatelessWidget {
  final List<_NavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  const _ClassicBar({
    required this.items,
    required this.currentIndex,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: const Border(top: BorderSide(color: AppColors.borderLight)),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryDark.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(8.w, 6.h, 8.w, 4.h),
          child: Row(
            children: [
              for (var i = 0; i < items.length; i++)
                Expanded(
                  child: _ClassicItem(
                    item: items[i],
                    selected: currentIndex == i,
                    onTap: () => onTap(i),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClassicItem extends StatelessWidget {
  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  const _ClassicItem({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.goldDark : AppColors.textMuted;
    return Semantics(
      button: true,
      selected: selected,
      label: _label(item),
      child: InkWell(
        onTap: () {
          HapticFeedback.selectionClick();
          if (item.isCart) CartMotion.pulse(haptic: false);
          if (item.isWishlist) WishlistMotion.pulse(haptic: false);
          onTap();
        },
        borderRadius: BorderRadius.circular(16.r),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 52),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeOutCubic,
                height: 3,
                width: selected ? 18.w : 0,
                margin: EdgeInsets.only(bottom: 4.h),
                decoration: BoxDecoration(
                  color: AppColors.burgundy,
                  borderRadius: BorderRadius.circular(4.r),
                ),
              ),
              _anchorIcon(
                _BadgeIcon(
                  icon: item.icon,
                  badge: item.badge,
                  color: color,
                  size: 22.w,
                ),
                item.isCart,
                item.isWishlist,
              ),
              SizedBox(height: 3.h),
              Text(
                item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10.sp,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                  color: color,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FloatingBar extends StatelessWidget {
  final List<_NavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  const _FloatingBar({
    required this.items,
    required this.currentIndex,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(14.w, 0, 14.w, 10.h),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(28.r),
            border: Border.all(color: AppColors.borderLight),
            boxShadow: [
              BoxShadow(
                color: AppColors.primaryDark.withValues(alpha: 0.10),
                blurRadius: 24,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 6.w, vertical: 6.h),
            child: Row(
              children: [
                for (var i = 0; i < items.length; i++)
                  Expanded(
                    child: _FloatingItem(
                      item: items[i],
                      selected: currentIndex == i,
                      onTap: () => onTap(i),
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

class _FloatingItem extends StatelessWidget {
  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  const _FloatingItem({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      label: _label(item),
      child: InkWell(
        onTap: () {
          HapticFeedback.selectionClick();
          if (item.isCart) CartMotion.pulse(haptic: false);
          if (item.isWishlist) WishlistMotion.pulse(haptic: false);
          onTap();
        },
        borderRadius: BorderRadius.circular(22.r),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: 48),
          padding: EdgeInsets.symmetric(vertical: 6.h),
          decoration: BoxDecoration(
            color: selected
                ? AppColors.gold.withValues(alpha: 0.12)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(20.r),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _anchorIcon(
                _BadgeIcon(
                  icon: item.icon,
                  badge: item.badge,
                  color: selected ? AppColors.goldDark : AppColors.textMuted,
                  size: 20.w,
                ),
                item.isCart,
                item.isWishlist,
              ),
              SizedBox(height: 2.h),
              Text(
                item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 9.sp,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                  color: selected ? AppColors.goldDark : AppColors.textMuted,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BadgeIcon extends StatelessWidget {
  final IconData icon;
  final int? badge;
  final Color color;
  final double size;

  const _BadgeIcon({
    required this.icon,
    required this.badge,
    required this.color,
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    final count = badge ?? 0;
    final text = count > 99 ? '99+' : '$count';
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Icon(icon, size: size, color: color),
        if (count > 0)
          PositionedDirectional(
            top: -6,
            end: -10,
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 4.w, vertical: 1.h),
              constraints: BoxConstraints(minWidth: 16.w, minHeight: 16.w),
              decoration: BoxDecoration(
                color: AppColors.burgundy,
                borderRadius: BorderRadius.circular(10.r),
                border: Border.all(color: AppColors.surface, width: 1.4),
              ),
              child: Text(
                text,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 8.sp,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

String _label(_NavItem item) {
  final count = item.badge ?? 0;
  if (count <= 0) return item.label;
  return '${item.label}، ${count > 99 ? '99+' : count}';
}

Widget _anchorIcon(Widget icon, bool isCart, bool isWishlist) {
  if (isCart) return CartBounce(child: icon);
  if (isWishlist) return WishlistBounce(child: icon);
  return icon;
}

class _NavItem {
  final IconData icon;
  final String label;
  final int? badge;
  final bool isCart;
  final bool isWishlist;

  const _NavItem({
    required this.icon,
    required this.label,
    this.badge,
    this.isCart = false,
    this.isWishlist = false,
  });
}
