import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/app_update/app_update_service.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';

Future<void> showOptionalUpdateDialog(
  BuildContext context, {
  required String? storeUrl,
  String? remoteVersion,
  AppUpdateService? updateService,
}) {
  final service = updateService ?? AppUpdateService();
  final isApple = !kIsWeb && Platform.isIOS;
  final storeLabel = isApple ? 'App Store' : 'Google Play';
  final effectiveUrl = (storeUrl != null && storeUrl.trim().isNotEmpty)
      ? storeUrl.trim()
      : (isApple
          ? ''
          : 'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp');

  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) {
      return PopScope(
        canPop: false,
        child: Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: EdgeInsets.symmetric(horizontal: 24.w),
          child: Container(
            padding: EdgeInsets.symmetric(horizontal: 22.w, vertical: 24.h),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(20.r),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.12),
                  blurRadius: 24,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 64.w,
                  height: 64.w,
                  decoration: BoxDecoration(
                    color: AppColors.burgundy.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.system_update_alt_rounded,
                    size: 32.w,
                    color: AppColors.burgundy,
                  ),
                ),
                SizedBox(height: 18.h),
                Text(
                  'يتوفر تحديث جديد',
                  textAlign: TextAlign.center,
                  style: AppTypography.titleMedium.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                    fontSize: 18.sp,
                  ),
                ),
                SizedBox(height: 10.h),
                Text(
                  remoteVersion == null || remoteVersion.isEmpty
                      ? 'يتوفر إصدار جديد من تطبيق لمّة على $storeLabel لتحسين الأداء وإضافة ميزات جديدة. يمكنك التحديث الآن أو المتابعة لاحقاً.'
                      : 'الإصدار $remoteVersion متاح الآن على $storeLabel. نوصي بالتحديث للحصول على أفضل أداء واستقرار، أو يمكنك المتابعة لاحقاً.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13.sp,
                    height: 1.5,
                    color: AppColors.textMuted,
                  ),
                ),
                SizedBox(height: 24.h),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 46.h,
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(dialogContext).pop(),
                          style: OutlinedButton.styleFrom(
                            side: BorderSide(
                              color: AppColors.primaryDark.withValues(alpha: 0.2),
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12.r),
                            ),
                          ),
                          child: Text(
                            'لاحقاً',
                            style: TextStyle(
                              fontSize: 13.5.sp,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ),
                      ),
                    ),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: SizedBox(
                        height: 46.h,
                        child: ElevatedButton(
                          onPressed: () async {
                            Navigator.of(dialogContext).pop();
                            await service.openStore(effectiveUrl);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.burgundy,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12.r),
                            ),
                          ),
                          child: Text(
                            'تحديث الآن',
                            style: TextStyle(
                              fontSize: 13.5.sp,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}
