import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../constants/app_colors.dart';
import '../theme/app_typography.dart';

class LuxuryTextField extends StatelessWidget {
  final TextEditingController? controller;
  final String hintText;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final TextInputType keyboardType;
  final bool obscureText;
  final bool readOnly;
  final VoidCallback? onTap;
  final int maxLines;
  final Color? fillColor;
  final bool compact;

  const LuxuryTextField({
    super.key,
    this.controller,
    required this.hintText,
    this.prefixIcon,
    this.suffixIcon,
    this.onChanged,
    this.onSubmitted,
    this.keyboardType = TextInputType.text,
    this.obscureText = false,
    this.readOnly = false,
    this.onTap,
    this.maxLines = 1,
    this.fillColor,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final radius = compact ? 12.r : 16.r;
    final horizontalPad = compact ? 12.w : 16.w;
    final verticalPad = compact ? 8.h : 14.h;
    final textStyle = compact
        ? AppTypography.bodyMedium.copyWith(color: AppColors.textPrimary)
        : AppTypography.bodyLarge.copyWith(color: AppColors.textPrimary);
    final hintStyle = compact
        ? AppTypography.bodySmall.copyWith(color: AppColors.textMuted)
        : AppTypography.bodyMedium.copyWith(color: AppColors.textMuted);

    // Border lives on the Container only. Theme sets filled:true globally,
    // which would paint over rounded corners and hide the border edges —
    // so force filled:false and transparent fill here.
    return Container(
      decoration: BoxDecoration(
        color: fillColor ?? AppColors.surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.border, width: 1.2),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: compact ? 4 : 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        onSubmitted: onSubmitted,
        keyboardType: keyboardType,
        obscureText: obscureText,
        readOnly: readOnly,
        onTap: onTap,
        maxLines: maxLines,
        style: textStyle,
        decoration: InputDecoration(
          hintText: hintText,
          hintStyle: hintStyle,
          prefixIcon: prefixIcon,
          suffixIcon: suffixIcon,
          isDense: compact,
          filled: false,
          fillColor: Colors.transparent,
          prefixIconConstraints: compact
              ? BoxConstraints(minWidth: 36.w, minHeight: 36.h)
              : null,
          suffixIconConstraints: compact
              ? BoxConstraints(minWidth: 0, minHeight: 36.h)
              : null,
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          disabledBorder: InputBorder.none,
          errorBorder: InputBorder.none,
          focusedErrorBorder: InputBorder.none,
          contentPadding: EdgeInsets.symmetric(
            horizontal: horizontalPad,
            vertical: verticalPad,
          ),
        ),
      ),
    );
  }
}
