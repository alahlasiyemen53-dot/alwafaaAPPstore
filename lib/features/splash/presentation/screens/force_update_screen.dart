import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/app_update/app_update_service.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/features/app_shell/presentation/screens/main_shell_screen.dart';

/// Blocking gate — user cannot continue without opening the store and updating.
/// Back navigation is strictly disabled via PopScope(canPop: false), and no "Later"
/// option is presented.
class ForceUpdateScreen extends StatefulWidget {
  const ForceUpdateScreen({
    super.key,
    required this.storeUrl,
    this.remoteVersion,
    AppUpdateService? updateService,
  }) : _updateService = updateService;

  final String? storeUrl;
  final String? remoteVersion;
  final AppUpdateService? _updateService;

  @override
  State<ForceUpdateScreen> createState() => _ForceUpdateScreenState();
}

class _ForceUpdateScreenState extends State<ForceUpdateScreen> {
  bool _isChecking = false;

  String get _effectiveStoreUrl {
    final url = widget.storeUrl?.trim();
    if (url != null && url.isNotEmpty) {
      return url;
    }
    if (!kIsWeb && Platform.isIOS) {
      return '';
    }
    return 'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp';
  }

  String get _storeButtonLabel {
    if (!kIsWeb && Platform.isIOS) return 'التحديث من App Store';
    return 'التحديث من Google Play';
  }

  Future<void> _handleRecheck(AppUpdateService service) async {
    if (_isChecking) return;
    setState(() => _isChecking = true);

    try {
      final result = await service.check();
      if (!mounted) return;

      if (!result.decision.isForce) {
        // App has been updated or force flag cleared — allow entry
        Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => const MainShellScreen(),
          ),
        );
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'لم يتم تحديث التطبيق بعد، يرجى إتمام التحديث من المتجر للمتابعة.',
            textAlign: TextAlign.center,
          ),
          backgroundColor: AppColors.primaryDark,
          duration: Duration(seconds: 3),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'تعذر التحقق من التحديث، يرجى المحاولة مرة أخرى.',
            textAlign: TextAlign.center,
          ),
          backgroundColor: AppColors.primaryDark,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isChecking = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final service = widget._updateService ?? AppUpdateService();

    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight,
                  ),
                  child: IntrinsicHeight(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 28.w),
                      child: Column(
                        children: [
                          const Spacer(flex: 2),
                          Container(
                            width: 88.w,
                            height: 88.w,
                            decoration: BoxDecoration(
                              color: AppColors.primaryDark.withValues(alpha: 0.08),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              Icons.system_update_alt_rounded,
                              size: 44.w,
                              color: AppColors.primaryDark,
                            ),
                          ),
                          SizedBox(height: 28.h),
                          Text(
                            'يرجى تحديث التطبيق',
                            textAlign: TextAlign.center,
                            style: AppTypography.titleLarge.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppColors.primaryDark,
                            ),
                          ),
                          SizedBox(height: 12.h),
                          Text(
                            'يتوفر إصدار جديد ضروري لمتابعة استخدام لمّة بأفضل تجربة وأمان. التحديث إجباري لمتابعة استخدام التطبيق.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 13.5.sp,
                              height: 1.45,
                              color: AppColors.textMuted,
                            ),
                          ),
                          if (widget.remoteVersion != null && widget.remoteVersion!.isNotEmpty) ...[
                            SizedBox(height: 10.h),
                            Text(
                              'الإصدار المطلوب: ${widget.remoteVersion}',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 12.sp,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                          const Spacer(flex: 3),
                          SizedBox(
                            width: double.infinity,
                            height: 50.h,
                            child: ElevatedButton(
                              onPressed: _effectiveStoreUrl.isEmpty
                                  ? null
                                  : () => service.openStore(_effectiveStoreUrl),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primaryDark,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14.r),
                                ),
                              ),
                              child: Text(
                                _storeButtonLabel,
                                style: TextStyle(
                                  fontSize: 14.sp,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                          SizedBox(height: 12.h),
                          TextButton(
                            onPressed: _isChecking ? null : () => _handleRecheck(service),
                            child: _isChecking
                                ? SizedBox(
                                    width: 18.w,
                                    height: 18.w,
                                    child: const CircularProgressIndicator(
                                      strokeWidth: 2,
                                      valueColor: AlwaysStoppedAnimation(AppColors.primaryDark),
                                    ),
                                  )
                                : Text(
                                    'التحقق من التحديث',
                                    style: TextStyle(
                                      fontSize: 13.sp,
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                          ),
                          SizedBox(height: 16.h),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
