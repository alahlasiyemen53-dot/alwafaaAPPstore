import 'package:flutter/material.dart';

/// Al-Wafa'a Medical Co. LTD
/// Medical & Healthcare Design System
///
/// Palette:
/// Deep Medical Slate + Medical Blue + Vital Red + Soft Blue-Grey + Pure White
class AppColors {
  AppColors._();

  // =========================================================
  // 1. BRAND IDENTITY
  // =========================================================
  static const Color primaryDark = Color(0xFF142433);
  static const Color primaryDarkHover = Color(0xFF1C344A);
  static const Color primaryLight = Color(0xFF3B566E);
  static const Color primaryContainer = Color(0xFFE8F1F8);

  // Main CTA — Vital Red (من لون الشعار)
  static const Color burgundy = Color(0xFFC1272D);
  static const Color burgundyHover = Color(0xFFA31D22);
  static const Color burgundyLight = Color(0xFFD65257);
  static const Color burgundyContainer = Color(0xFFFDECEE);
  static const Color onBurgundy = Color(0xFFFFFFFF);

  // =========================================================
  // 2. LUXURY ACCENTS (Medical Accents)
  // =========================================================
  // Legacy names retained for compatibility.
  static const Color gold = Color(0xFF1E6CB3); // الأزرق الطبي من الشعار
  static const Color goldLight = Color(0xFFE8F1F9);
  static const Color goldDark = Color(0xFF154C7E);
  static const Color goldShimmer = Color(0xFFD4E6F7);
  static const Color champagne = Color(0xFFE3EDF7);
  static const Color champagneDark = Color(0xFF2A5982);
  static const Color blush = Color(0xFFFCDADE);
  static const Color blushLight = Color(0xFFFDF2F4);
  static const Color blushDark = Color(0xFFC1272D);

  // =========================================================
  // 3. BACKGROUNDS & SURFACES
  // =========================================================
  static const Color background = Color(0xFFF7FAFC);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceMuted = Color(0xFFEDF2F7);
  static const Color surfaceSoft = Color(0xFFF1F6FA);
  static const Color scaffoldAlt = Color(0xFFEBF1F6);
  static const Color card = Color(0xFFFFFFFF);
  static const Color bottomSheet = Color(0xFFFFFFFF);
  static const Color dialog = Color(0xFFFFFFFF);

  // =========================================================
  // 4. BORDERS & DIVIDERS
  // =========================================================
  static const Color border = Color(0xFFD2DFE9);
  static const Color borderLight = Color(0xFFE5EEF5);
  static const Color borderDark = Color(0xFF9AB2C5);
  static const Color divider = Color(0xFFE2ECF2);

  // =========================================================
  // 5. TYPOGRAPHY
  // =========================================================
  static const Color textPrimary = Color(0xFF142433);
  static const Color textSecondary = Color(0xFF475E73);
  static const Color textMuted = Color(0xFF71889C);
  static const Color textLight = Color(0xFFF7FAFC);
  static const Color textGold = Color(0xFF1E6CB3);
  static const Color textOnPrimary = Color(0xFFFFFFFF);

  // =========================================================
  // 6. INTERACTIVE ELEMENTS
  // =========================================================
  static const Color buttonPrimary = Color(0xFF1E6CB3);
  static const Color buttonPrimaryHover = Color(0xFF154C7E);
  static const Color buttonSecondary = Color(0xFFC1272D);
  static const Color buttonDisabled = Color(0xFFD2DFE9);
  static const Color buttonDisabledText = Color(0xFF8CA1B3);
  static const Color iconPrimary = Color(0xFF142433);
  static const Color iconSecondary = Color(0xFF5A7287);
  static const Color iconSelected = Color(0xFF1E6CB3);
  static const Color iconDisabled = Color(0xFFAEC0CF);
  static const Color inputBackground = Color(0xFFFFFFFF);
  static const Color inputBorder = Color(0xFFD2DFE9);
  static const Color inputFocus = Color(0xFF1E6CB3);
  static const Color inputError = Color(0xFFC1272D);
  static const Color inputHint = Color(0xFF8CA1B3);

  // =========================================================
  // 7. E-COMMERCE
  // =========================================================
  static const Color price = Color(0xFFC1272D);
  static const Color oldPrice = Color(0xFF8CA1B3);
  static const Color discountBadge = Color(0xFFC1272D);
  static const Color discountBackground = Color(0xFFFDECEE);
  static const Color favorite = Color(0xFFC1272D);
  static const Color cartBadge = Color(0xFFC1272D);
  static const Color stockAvailable = Color(0xFF2E7D32);
  static const Color stockUnavailable = Color(0xFF71889C);

  // =========================================================
  // 8. SEMANTIC FEEDBACK
  // =========================================================
  static const Color success = Color(0xFF2E7D32);
  static const Color successBackground = Color(0xFFE8F5E9);
  static const Color error = Color(0xFFC1272D);
  static const Color errorBackground = Color(0xFFFDECEE);
  static const Color warning = Color(0xFFED6C02);
  static const Color warningBackground = Color(0xFFFFF4E5);
  static const Color info = Color(0xFF1E6CB3);
  static const Color infoBackground = Color(0xFFE8F1F9);

  // =========================================================
  // 9. RATINGS
  // =========================================================
  static const Color ratingStar = Color(0xFFFFA000);
  static const Color ratingStarEmpty = Color(0xFFCFD8DC);

  // =========================================================
  // 10. SHADOWS
  // =========================================================
  static final BoxShadow softShadow = BoxShadow(
    color: const Color(0xFF142433).withValues(alpha: 0.04),
    blurRadius: 14,
    offset: const Offset(0, 4),
  );
  static final BoxShadow elevatedShadow = BoxShadow(
    color: const Color(0xFF142433).withValues(alpha: 0.08),
    blurRadius: 24,
    offset: const Offset(0, 8),
  );

  // =========================================================
  // 11. OVERLAYS
  // =========================================================
  static const Color scrim = Color(0x66000000);
  static const Color imagePlaceholder = Color(0xFFEDF2F7);
}