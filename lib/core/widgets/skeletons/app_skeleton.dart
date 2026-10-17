import 'package:flutter/material.dart';
import 'package:store/core/constants/app_colors.dart';

/// Coordinates a single subtle shimmer animation for a skeleton group.
/// Prefer one [AppShimmer] ancestor over per-box AnimationControllers.
class AppShimmer extends StatefulWidget {
  final Widget child;
  final Duration period;

  const AppShimmer({
    super.key,
    required this.child,
    this.period = const Duration(milliseconds: 1400),
  });

  static AppShimmerState? maybeOf(BuildContext context) {
    return context.findAncestorStateOfType<AppShimmerState>();
  }

  @override
  State<AppShimmer> createState() => AppShimmerState();
}

class AppShimmerState extends State<AppShimmer>
    with SingleTickerProviderStateMixin {
  late final AnimationController controller;

  @override
  void initState() {
    super.initState();
    controller = AnimationController(vsync: this, duration: widget.period);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncMotion();
  }

  void _syncMotion() {
    final disable = MediaQuery.disableAnimationsOf(context);
    if (disable) {
      if (controller.isAnimating) {
        controller.stop();
      }
      controller.value = 0.35;
    } else if (!controller.isAnimating) {
      controller.repeat();
    }
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Base bone shape used by all skeletons. No fake business text/data.
class AppSkeletonBox extends StatelessWidget {
  final double? width;
  final double? height;
  final BorderRadius? borderRadius;
  final BoxShape shape;

  const AppSkeletonBox({
    super.key,
    this.width,
    this.height,
    this.borderRadius,
    this.shape = BoxShape.rectangle,
  });

  @override
  Widget build(BuildContext context) {
    final shimmer = AppShimmer.maybeOf(context);
    final radius = borderRadius ?? BorderRadius.circular(8);

    Widget bone = Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: AppColors.surfaceMuted,
        shape: shape,
        borderRadius: shape == BoxShape.circle ? null : radius,
      ),
    );

    if (shimmer == null) return bone;

    return AnimatedBuilder(
      animation: shimmer.controller,
      builder: (context, child) {
        final t = shimmer.controller.value;
        final highlight = Color.lerp(
          AppColors.surfaceMuted,
          AppColors.surfaceSoft,
          (0.35 + 0.65 * (t < 0.5 ? t * 2 : (1 - t) * 2)).clamp(0.0, 1.0),
        )!;
        return Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            color: highlight,
            shape: shape,
            borderRadius: shape == BoxShape.circle ? null : radius,
          ),
        );
      },
    );
  }
}

class AppSkeletonCircle extends StatelessWidget {
  final double size;

  const AppSkeletonCircle({super.key, required this.size});

  @override
  Widget build(BuildContext context) {
    return AppSkeletonBox(
      width: size,
      height: size,
      shape: BoxShape.circle,
    );
  }
}

class AppSkeletonText extends StatelessWidget {
  final double width;
  final double height;

  const AppSkeletonText({
    super.key,
    required this.width,
    this.height = 12,
  });

  @override
  Widget build(BuildContext context) {
    return AppSkeletonBox(
      width: width,
      height: height,
      borderRadius: BorderRadius.circular(6),
    );
  }
}
