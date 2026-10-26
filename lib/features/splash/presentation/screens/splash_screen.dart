import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/app_update/app_update_policy.dart';
import 'package:store/core/app_update/app_update_service.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/data/repositories/reference_repository.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/app_shell/presentation/screens/main_shell_screen.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/splash/presentation/screens/force_update_screen.dart';
import 'package:store/features/splash/presentation/widgets/optional_update_dialog.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  Timer? _fallbackTimer;
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;
  late Animation<double> _scaleAnimation;
  late Animation<double> _textFadeAnimation;

  @override
  void initState() {
    super.initState();

    SystemChrome.setSystemUIOverlayStyle(
      const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
    );

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    );

    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.0, 0.6, curve: Curves.easeOut),
      ),
    );

    _scaleAnimation = Tween<double>(begin: 0.88, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.0, 0.7, curve: Curves.easeOutCubic),
      ),
    );

    _textFadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.4, 0.9, curve: Curves.easeIn),
      ),
    );

    _controller.forward();

    _fallbackTimer = Timer(const Duration(milliseconds: 3200), () {
      if (mounted) {
        _goToMainShell(false);
      }
    });

    _navigateToHome();
  }

  Future<void> _navigateToHome() async {
    final currency = context.read<CurrencyCubit>();
    final reference = context.read<ReferenceRepository>();
    final updateService = AppUpdateService();
    final started = DateTime.now();
    bool isWarm = false;

    try {
      final bootstrap = Future.wait([
        _waitForSessionResolution(),
        currency.ensureLoaded(),
        reference.ensureGeoWarmed(),
      ]).timeout(
        const Duration(milliseconds: 2500),
        onTimeout: () => [],
      );

      final updateCheckFuture = updateService.check().timeout(
            const Duration(milliseconds: 2500),
            onTimeout: () => AppUpdateCheckResult.fallback,
          );

      await bootstrap;
      if (!mounted) return;

      final elapsed = DateTime.now().difference(started);
      isWarm = elapsed <= const Duration(milliseconds: 1200);
      final targetMin = isWarm
          ? const Duration(milliseconds: 900)
          : const Duration(milliseconds: 1500);
      final remaining = targetMin - elapsed;
      if (remaining > Duration.zero) {
        await Future.delayed(remaining);
      }
      if (!mounted) return;

      final updateCheck = await updateCheckFuture;
      if (!mounted) return;

      if (updateCheck.decision.isForce) {
        _fallbackTimer?.cancel();
        _fallbackTimer = null;
        SystemChrome.setSystemUIOverlayStyle(
          const SystemUiOverlayStyle(
            statusBarColor: Colors.transparent,
            statusBarIconBrightness: Brightness.dark,
            systemNavigationBarColor: Colors.white,
            systemNavigationBarIconBrightness: Brightness.dark,
          ),
        );
        Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => ForceUpdateScreen(
              storeUrl: updateCheck.storeUrl,
              remoteVersion: updateCheck.release.latestVersion,
              updateService: updateService,
            ),
          ),
        );
        return;
      }

      if (updateCheck.decision.kind == AppUpdateKind.optional) {
        _fallbackTimer?.cancel();
        _fallbackTimer = null;
        await showOptionalUpdateDialog(
          context,
          storeUrl: updateCheck.storeUrl,
          remoteVersion: updateCheck.release.latestVersion,
          updateService: updateService,
        );
        if (!mounted) return;
      }
    } catch (e, stack) {
      debugPrint('SplashScreen navigation fail-safe triggered: $e\n$stack');
    }

    if (!mounted) return;
    _goToMainShell(isWarm);
  }

  void _goToMainShell(bool isWarm) {
    _fallbackTimer?.cancel();
    _fallbackTimer = null;

    SystemChrome.setSystemUIOverlayStyle(
      const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
    );

    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: Duration(milliseconds: isWarm ? 400 : 650),
        pageBuilder: (context, animation, secondaryAnimation) =>
            const MainShellScreen(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(
            opacity:
                CurvedAnimation(parent: animation, curve: Curves.easeInOut),
            child: child,
          );
        },
      ),
    );
  }

  Future<void> _waitForSessionResolution() async {
    try {
      if (!mounted) return;
      final cubit = context.read<AccountCubit>();
      if (cubit.state.status != AuthStatus.unknown) return;
      await cubit.stream
          .firstWhere((s) => s.status != AuthStatus.unknown)
          .timeout(const Duration(milliseconds: 2500));
    } catch (_) {
      // Safe fallback on timeout or error to avoid blocking splash
    }
  }

  @override
  void dispose() {
    _fallbackTimer?.cancel();
    _fallbackTimer = null;
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFFFFFFF),
              AppColors.primaryContainer,
              Color(0xFFF7FAFC),
            ],
            stops: [0.0, 0.55, 1.0],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const Spacer(flex: 2),
              AnimatedBuilder(
                animation: _controller,
                builder: (context, child) {
                  return FadeTransition(
                    opacity: _fadeAnimation,
                    child: Transform.scale(
                      scale: _scaleAnimation.value,
                      child: child,
                    ),
                  );
                },
                child: Center(
                  child: Container(
                    width: 168.w,
                    height: 168.w,
                    padding: EdgeInsets.all(18.w),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(36.r),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.gold.withValues(alpha: 0.18),
                          blurRadius: 28,
                          offset: const Offset(0, 10),
                        ),
                        BoxShadow(
                          color: AppColors.burgundy.withValues(alpha: 0.08),
                          blurRadius: 18,
                          offset: const Offset(0, 4),
                        ),
                      ],
                      border: Border.all(
                        color: AppColors.surfaceMuted,
                        width: 1,
                      ),
                    ),
                    child: Image.asset(
                      'assets/images/app_icon.png',
                      fit: BoxFit.contain,
                      filterQuality: FilterQuality.high,
                    ),
                  ),
                ),
              ),
              SizedBox(height: 28.h),
              AnimatedBuilder(
                animation: _controller,
                builder: (context, child) {
                  return FadeTransition(
                    opacity: _textFadeAnimation,
                    child: child,
                  );
                },
                child: Column(
                  children: [
                    Text(
                      AppStrings.appNameArabic,
                      style: GoogleFonts.cormorantGaramond(
                        fontSize: 28.sp,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.8,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    SizedBox(height: 10.h),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 28.w,
                          height: 2,
                          color: AppColors.burgundy.withValues(alpha: 0.55),
                        ),
                        Padding(
                          padding: EdgeInsets.symmetric(horizontal: 10.w),
                          child: Text(
                            'الوفاء استور',
                            style: TextStyle(
                              fontSize: 13.sp,
                              fontWeight: FontWeight.w600,
                              color: AppColors.goldDark,
                            ),
                          ),
                        ),
                        Container(
                          width: 28.w,
                          height: 2,
                          color: AppColors.gold.withValues(alpha: 0.55),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const Spacer(flex: 3),
              AnimatedBuilder(
                animation: _controller,
                builder: (context, child) {
                  return FadeTransition(
                    opacity: _textFadeAnimation,
                    child: child,
                  );
                },
                child: Padding(
                  padding: EdgeInsets.only(bottom: 24.h),
                  child: Column(
                    children: [
                      SizedBox(
                        width: 24.w,
                        height: 24.w,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.0,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            AppColors.gold.withValues(alpha: 0.9),
                          ),
                        ),
                      ),
                      SizedBox(height: 14.h),
                      Text(
                        'متجرك الطبي الموثوق',
                        style: TextStyle(
                          fontSize: 11.sp,
                          color: AppColors.textSecondary,
                          letterSpacing: 0.2,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
