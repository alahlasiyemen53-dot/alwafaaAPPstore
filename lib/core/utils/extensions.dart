import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

enum AppSnackBarType { success, error, info }

/// Unified floating snackbars branded with [AppColors].
abstract final class AppSnackBar {
  static void show(
    ScaffoldMessengerState messenger,
    String message, {
    AppSnackBarType type = AppSnackBarType.info,
    Duration duration = const Duration(seconds: 2),
    double? bottomMargin,
  }) {
    messenger.hideCurrentSnackBar();

    final Color background;
    final IconData icon;
    switch (type) {
      case AppSnackBarType.success:
        background = AppColors.primaryDark;
        icon = Icons.check_circle_rounded;
      case AppSnackBarType.error:
        background = AppColors.error;
        icon = Icons.error_outline_rounded;
      case AppSnackBarType.info:
        background = AppColors.info;
        icon = Icons.info_outline_rounded;
    }

    messenger.showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, color: AppColors.textLight, size: 18),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                textAlign: TextAlign.start,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  color: AppColors.textLight,
                ),
              ),
            ),
          ],
        ),
        backgroundColor: background,
        behavior: SnackBarBehavior.floating,
        margin: EdgeInsets.fromLTRB(24, 0, 24, bottomMargin ?? 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        duration: duration,
      ),
    );
  }
}

extension ContextExt on BuildContext {
  ThemeData get theme => Theme.of(this);
  TextTheme get textTheme => Theme.of(this).textTheme;
  ColorScheme get colorScheme => Theme.of(this).colorScheme;

  double get screenWidth => MediaQuery.sizeOf(this).width;
  double get screenHeight => MediaQuery.sizeOf(this).height;
  EdgeInsets get viewPadding => MediaQuery.viewPaddingOf(this);

  void showAppSnackBar(
    String message, {
    AppSnackBarType type = AppSnackBarType.info,
    Duration duration = const Duration(seconds: 2),
  }) {
    final height = MediaQuery.sizeOf(this).height;
    const snackBarHeightEstimate = 56.0;
    final bottomMargin =
        ((height - snackBarHeightEstimate) / 2).clamp(48.0, height * 0.55);

    AppSnackBar.show(
      ScaffoldMessenger.of(this),
      message,
      type: type,
      duration: duration,
      bottomMargin: bottomMargin,
    );
  }

  /// Convenience wrapper — success by default, error when [isError] is true.
  void showSnackBar(
    String message, {
    bool isError = false,
    Duration duration = const Duration(seconds: 2),
  }) {
    showAppSnackBar(
      message,
      type: isError ? AppSnackBarType.error : AppSnackBarType.success,
      duration: duration,
    );
  }
}
