import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import '../constants/app_colors.dart';

/// AppTypography defines elegant Arabic and Latin serif typography tokens scaled with ScreenUtil.
class AppTypography {
  AppTypography._();

  // Arabic Primary Font Family: Cairo / Alexandria style
  static TextStyle get displayLarge => GoogleFonts.cairo(
        fontSize: 26.sp,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
        height: 1.25,
      );

  static TextStyle get displayMedium => GoogleFonts.cairo(
        fontSize: 22.sp,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
        height: 1.3,
      );

  static TextStyle get displaySmall => GoogleFonts.cairo(
        fontSize: 18.sp,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
        height: 1.3,
      );

  static TextStyle get titleLarge => GoogleFonts.cairo(
        fontSize: 16.sp,
        fontWeight: FontWeight.w600,
        color: AppColors.textPrimary,
      );

  static TextStyle get titleMedium => GoogleFonts.cairo(
        fontSize: 14.sp,
        fontWeight: FontWeight.w600,
        color: AppColors.textPrimary,
      );

  static TextStyle get titleSmall => GoogleFonts.cairo(
        fontSize: 12.sp,
        fontWeight: FontWeight.w600,
        color: AppColors.textPrimary,
      );

  static TextStyle get bodyLarge => GoogleFonts.cairo(
        fontSize: 14.sp,
        fontWeight: FontWeight.w400,
        color: AppColors.textSecondary,
        height: 1.5,
      );

  static TextStyle get bodyMedium => GoogleFonts.cairo(
        fontSize: 12.sp,
        fontWeight: FontWeight.w400,
        color: AppColors.textSecondary,
        height: 1.4,
      );

  static TextStyle get bodySmall => GoogleFonts.cairo(
        fontSize: 10.sp,
        fontWeight: FontWeight.w400,
        color: AppColors.textMuted,
        height: 1.3,
      );

  static TextStyle get labelLarge => GoogleFonts.cairo(
        fontSize: 13.sp,
        fontWeight: FontWeight.w700,
        color: AppColors.primaryDark,
      );

  static TextStyle get labelMedium => GoogleFonts.cairo(
        fontSize: 11.sp,
        fontWeight: FontWeight.w600,
        color: AppColors.textSecondary,
      );

  static TextStyle get labelSmall => GoogleFonts.cairo(
        fontSize: 9.sp,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.5,
      );

  // Luxury English Serif Font: Cormorant Garamond / Playfair Display
  static TextStyle luxurySerif({
    double? fontSize,
    FontWeight fontWeight = FontWeight.w400,
    Color color = AppColors.textPrimary,
    double? letterSpacing,
    FontStyle? fontStyle,
  }) {
    return GoogleFonts.cormorantGaramond(
      fontSize: fontSize ?? 16.sp,
      fontWeight: fontWeight,
      color: color,
      letterSpacing: letterSpacing,
      fontStyle: fontStyle,
    );
  }
}
