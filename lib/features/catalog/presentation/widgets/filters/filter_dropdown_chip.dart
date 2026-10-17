import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';

/// Compact filter chip that opens a bottom sheet (phone) or anchored menu (wide).
class FilterDropdownChip extends StatelessWidget {
  const FilterDropdownChip({
    super.key,
    required this.label,
    required this.onPressed,
    this.isActive = false,
    this.leadingIcon,
  });

  final String label;
  final VoidCallback onPressed;
  final bool isActive;
  final IconData? leadingIcon;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      selected: isActive,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(20.r),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
            decoration: BoxDecoration(
              color: isActive ? AppColors.primaryDark : AppColors.surface,
              borderRadius: BorderRadius.circular(20.r),
              border: Border.all(
                color: isActive ? AppColors.primaryDark : AppColors.border,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (leadingIcon != null) ...[
                  Icon(
                    leadingIcon,
                    size: 14.w,
                    color: isActive ? Colors.white : AppColors.primaryDark,
                  ),
                  SizedBox(width: 4.w),
                ],
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 11.sp,
                    fontWeight: isActive ? FontWeight.w700 : FontWeight.w600,
                    color: isActive ? Colors.white : AppColors.textPrimary,
                  ),
                ),
                SizedBox(width: 2.w),
                Icon(
                  Icons.keyboard_arrow_down_rounded,
                  size: 16.w,
                  color: isActive ? Colors.white70 : AppColors.textMuted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Shows [builder] content in a modal bottom sheet (default) or a simple dialog on wide screens.
Future<T?> showFilterPicker<T>({
  required BuildContext context,
  required String title,
  required WidgetBuilder builder,
}) {
  final wide = MediaQuery.sizeOf(context).shortestSide >= 600 || kIsWeb;
  if (wide) {
    return showDialog<T>(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: EdgeInsets.symmetric(horizontal: 40.w, vertical: 24.h),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20.r)),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: 420.w, maxHeight: 520.h),
          child: Padding(
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 12.h),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 15.sp,
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                  ),
                ),
                SizedBox(height: 12.h),
                Flexible(child: builder(ctx)),
              ],
            ),
          ),
        ),
      ),
    );
  }

  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      final bottom = MediaQuery.paddingOf(ctx).bottom;
      return Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(ctx).height * 0.72,
        ),
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h + bottom),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24.r)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 40.w,
                height: 4.h,
                decoration: BoxDecoration(
                  color: AppColors.border,
                  borderRadius: BorderRadius.circular(4.r),
                ),
              ),
            ),
            SizedBox(height: 12.h),
            Text(
              title,
              style: TextStyle(
                fontSize: 15.sp,
                fontWeight: FontWeight.w800,
                color: AppColors.primaryDark,
              ),
            ),
            SizedBox(height: 12.h),
            Flexible(child: builder(ctx)),
          ],
        ),
      );
    },
  );
}
