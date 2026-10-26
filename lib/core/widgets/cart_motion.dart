import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';

/// Shared cart-target anchor and add-to-cart flight animation.
class CartMotion {
  CartMotion._();

  static final GlobalKey targetKey = GlobalKey(debugLabel: 'cart-target');
  static final ValueNotifier<int> pulses = ValueNotifier<int>(0);

  static void pulse({bool haptic = true}) {
    pulses.value++;
    if (haptic) HapticFeedback.lightImpact();
  }

  static Offset? originOf(BuildContext context) {
    final box = context.findRenderObject();
    if (box is! RenderBox || !box.hasSize || !box.attached) return null;
    return box.localToGlobal(box.size.center(Offset.zero));
  }

  /// Flies a product thumbnail from [origin] into the visible cart icon.
  static void fly({
    required BuildContext context,
    Offset? origin,
    String? imageUrl,
  }) {
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    final targetContext = targetKey.currentContext;
    final targetBox = targetContext?.findRenderObject();
    final overlayBox = overlay?.context.findRenderObject();

    if (overlay == null ||
        origin == null ||
        targetBox is! RenderBox ||
        !targetBox.hasSize ||
        overlayBox is! RenderBox) {
      pulse();
      return;
    }

    final start = overlayBox.globalToLocal(origin);
    final end = overlayBox.globalToLocal(
      targetBox.localToGlobal(targetBox.size.center(Offset.zero)),
    );

    _insertFly(
      overlay: overlay,
      start: start,
      end: end,
      imageUrl: imageUrl,
      borderColor: AppColors.gold,
      fallbackIcon: Icons.shopping_bag_rounded,
      fallbackColor: AppColors.goldDark,
      onArrive: pulse,
    );
  }
}

/// Shared wishlist-target anchor and add-to-wishlist flight animation.
class WishlistMotion {
  WishlistMotion._();

  static final GlobalKey targetKey = GlobalKey(debugLabel: 'wishlist-target');
  static final ValueNotifier<int> pulses = ValueNotifier<int>(0);

  static void pulse({bool haptic = true}) {
    pulses.value++;
    if (haptic) HapticFeedback.lightImpact();
  }

  static void fly({
    required BuildContext context,
    Offset? origin,
    String? imageUrl,
  }) {
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    final targetContext = targetKey.currentContext;
    final targetBox = targetContext?.findRenderObject();
    final overlayBox = overlay?.context.findRenderObject();

    if (overlay == null || origin == null || overlayBox is! RenderBox) {
      pulse();
      return;
    }

    final start = overlayBox.globalToLocal(origin);
    final Offset end;
    if (targetBox is RenderBox && targetBox.hasSize) {
      end = overlayBox.globalToLocal(
        targetBox.localToGlobal(targetBox.size.center(Offset.zero)),
      );
    } else {
      end = Offset(overlayBox.size.width * 0.5, overlayBox.size.height - 36);
    }

    _insertFly(
      overlay: overlay,
      start: start,
      end: end,
      imageUrl: imageUrl,
      borderColor: AppColors.discountBadge,
      fallbackIcon: Icons.favorite_rounded,
      fallbackColor: AppColors.discountBadge,
      onArrive: pulse,
    );
  }
}

void _insertFly({
  required OverlayState overlay,
  required Offset start,
  required Offset end,
  required String? imageUrl,
  required Color borderColor,
  required IconData fallbackIcon,
  required Color fallbackColor,
  required VoidCallback onArrive,
}) {
  late OverlayEntry entry;
  entry = OverlayEntry(
    builder: (_) => _FlyBubble(
      start: start,
      end: end,
      imageUrl: imageUrl,
      borderColor: borderColor,
      fallbackIcon: fallbackIcon,
      fallbackColor: fallbackColor,
      onDone: () {
        if (entry.mounted) entry.remove();
        onArrive();
      },
    ),
  );
  overlay.insert(entry);
}

/// Bounces whichever cart icon is currently on screen.
class CartBounce extends StatelessWidget {
  final Widget child;

  const CartBounce({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return _AnchorBounce(
      anchorKey: CartMotion.targetKey,
      pulses: CartMotion.pulses,
      child: child,
    );
  }
}

/// Bounces whichever wishlist icon is currently on screen.
class WishlistBounce extends StatelessWidget {
  final Widget child;

  const WishlistBounce({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return _AnchorBounce(
      anchorKey: WishlistMotion.targetKey,
      pulses: WishlistMotion.pulses,
      child: child,
    );
  }
}

class _AnchorBounce extends StatefulWidget {
  final Widget child;
  final GlobalKey anchorKey;
  final ValueNotifier<int> pulses;

  const _AnchorBounce({
    required this.child,
    required this.anchorKey,
    required this.pulses,
  });

  @override
  State<_AnchorBounce> createState() => _AnchorBounceState();
}

class _AnchorBounceState extends State<_AnchorBounce>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 520),
    );
    _scale = TweenSequence<double>([
      TweenSequenceItem(
        tween: Tween<double>(begin: 1.0, end: 1.22)
            .chain(CurveTween(curve: Curves.easeOut)),
        weight: 28,
      ),
      TweenSequenceItem(
        tween: Tween<double>(begin: 1.22, end: 0.94)
            .chain(CurveTween(curve: Curves.easeInOut)),
        weight: 24,
      ),
      TweenSequenceItem(
        tween: Tween<double>(begin: 0.94, end: 1.0)
            .chain(CurveTween(curve: Curves.elasticOut)),
        weight: 48,
      ),
    ]).animate(_controller);
    widget.pulses.addListener(_onPulse);
  }

  void _onPulse() {
    if (!mounted) return;
    _controller.forward(from: 0);
  }

  @override
  void dispose() {
    widget.pulses.removeListener(_onPulse);
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scale,
      child: KeyedSubtree(
        key: widget.anchorKey,
        child: widget.child,
      ),
    );
  }
}

class _FlyBubble extends StatefulWidget {
  final Offset start;
  final Offset end;
  final String? imageUrl;
  final Color borderColor;
  final IconData fallbackIcon;
  final Color fallbackColor;
  final VoidCallback onDone;

  const _FlyBubble({
    required this.start,
    required this.end,
    required this.imageUrl,
    required this.borderColor,
    required this.fallbackIcon,
    required this.fallbackColor,
    required this.onDone,
  });

  @override
  State<_FlyBubble> createState() => _FlyBubbleState();
}

class _FlyBubbleState extends State<_FlyBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 680),
    )..forward().whenComplete(() {
        if (mounted) widget.onDone();
      });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Offset _arc(double t) {
    final lift = math.max(72.0, (widget.start.dy - widget.end.dy).abs() * 0.28);
    final control = Offset(
      (widget.start.dx + widget.end.dx) / 2,
      math.min(widget.start.dy, widget.end.dy) - lift,
    );
    final a = Offset.lerp(widget.start, control, t)!;
    final b = Offset.lerp(control, widget.end, t)!;
    return Offset.lerp(a, b, t)!;
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = Curves.easeInOutCubic.transform(_controller.value);
          final point = _arc(t);
          final size = 46 - (22 * t);
          final url = widget.imageUrl?.trim() ?? '';
          return Stack(
            children: [
              Positioned(
                left: point.dx - size / 2,
                top: point.dy - size / 2,
                child: Opacity(
                  opacity: t < 0.82 ? 1 : (1 - (t - 0.82) / 0.18).clamp(0, 1),
                  child: Container(
                    width: size,
                    height: size,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(color: widget.borderColor, width: 1.4),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primaryDark.withValues(alpha: 0.18),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: url.isEmpty
                        ? Icon(
                            widget.fallbackIcon,
                            color: widget.fallbackColor,
                            size: size * 0.46,
                          )
                        : CachedImageWidget(
                            imageUrl: url,
                            fit: BoxFit.contain,
                          ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
