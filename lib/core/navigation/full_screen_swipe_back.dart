import 'package:flutter/cupertino.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

/// Scope to explicitly enable or disable full-screen swipe back for a subtree or screen.
class FullScreenSwipeBackScope extends StatefulWidget {
  const FullScreenSwipeBackScope({
    super.key,
    required this.enabled,
    required this.child,
  });

  final bool enabled;
  final Widget child;

  static final Expando<bool> _routeStates = Expando<bool>();

  /// Checks if a route was explicitly disabled by FullScreenSwipeBackScope in its subtree.
  static bool isRouteEnabled(Route<dynamic>? route) {
    if (route == null) return true;
    return _routeStates[route] ?? true;
  }

  /// Checks whether full-screen swipe back is enabled in the current context or route.
  static bool isEnabled(BuildContext context) {
    final _FullScreenSwipeBackScopeInherited? scope =
        context.dependOnInheritedWidgetOfExactType<_FullScreenSwipeBackScopeInherited>();
    if (scope != null) return scope.enabled;

    final route = ModalRoute.of(context);
    return isRouteEnabled(route);
  }

  @override
  State<FullScreenSwipeBackScope> createState() =>
      _FullScreenSwipeBackScopeState();
}

class _FullScreenSwipeBackScopeState extends State<FullScreenSwipeBackScope> {
  ModalRoute<dynamic>? _route;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _route = ModalRoute.of(context);
    if (_route != null) {
      FullScreenSwipeBackScope._routeStates[_route!] = widget.enabled;
    }
  }

  @override
  void didUpdateWidget(covariant FullScreenSwipeBackScope oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.enabled != widget.enabled && _route != null) {
      FullScreenSwipeBackScope._routeStates[_route!] = widget.enabled;
    }
  }

  @override
  void dispose() {
    if (_route != null) {
      FullScreenSwipeBackScope._routeStates[_route!] = null;
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _FullScreenSwipeBackScopeInherited(
      enabled: widget.enabled,
      child: widget.child,
    );
  }
}

class _FullScreenSwipeBackScopeInherited extends InheritedWidget {
  const _FullScreenSwipeBackScopeInherited({
    required this.enabled,
    required super.child,
  });

  final bool enabled;

  @override
  bool updateShouldNotify(_FullScreenSwipeBackScopeInherited oldWidget) {
    return enabled != oldWidget.enabled;
  }
}

/// Widget to explicitly exclude a widget or area (e.g. custom canvas, game, horizontal element)
/// from triggering the full-screen swipe back gesture.
class FullScreenSwipeBackExclusion extends SingleChildRenderObjectWidget {
  const FullScreenSwipeBackExclusion({
    super.key,
    required super.child,
  });

  @override
  RenderObject createRenderObject(BuildContext context) {
    return RenderFullScreenSwipeBackExclusion();
  }
}

/// RenderObject used by hit testing to detect exclusion zones.
class RenderFullScreenSwipeBackExclusion extends RenderProxyBox {}

/// Helper for inspecting pointer hits to prevent conflicts with horizontal scrollables,
/// sliders, carousels, page views, and explicit exclusions.
class FullScreenSwipeBackHitTestHelper {
  FullScreenSwipeBackHitTestHelper._();

  /// Inspects hit-test entries at [globalPosition] in [viewId].
  /// Returns `true` if the pointer landed on an excluded element or horizontal scrollable.
  static bool isPointerInExcludedOrHorizontalArea(
    Offset globalPosition,
    int viewId,
  ) {
    final HitTestResult result = HitTestResult();
    try {
      WidgetsBinding.instance.hitTestInView(result, globalPosition, viewId);
    } catch (_) {
      return false;
    }

    for (final HitTestEntry entry in result.path) {
      final target = entry.target;

      // 1. Explicitly marked exclusion zone
      if (target is RenderFullScreenSwipeBackExclusion) {
        return true;
      }

      // 2. Horizontal scrollable viewports (PageView, horizontal ListView, Carousel)
      if (target is RenderViewportBase) {
        if (target.axis == Axis.horizontal) {
          return true;
        }
      }

      // 3. Sliders / RangeSliders / Dismissible
      final String typeName = target.runtimeType.toString();
      if (typeName.contains('Slider') ||
          typeName.contains('Dismissible') ||
          typeName.contains('Carousel') ||
          typeName.contains('TabBarView')) {
        return true;
      }
    }

    return false;
  }
}

/// Custom horizontal drag gesture recognizer with directional validation,
/// early vertical scroll yielding, and Gesture Arena integration.
class FullScreenSwipeBackRecognizer extends HorizontalDragGestureRecognizer {
  FullScreenSwipeBackRecognizer({
    required this.isRtl,
    super.debugOwner,
  });

  final ValueGetter<bool> isRtl;
  bool _resolved = false;
  Offset _initialPosition = Offset.zero;

  @override
  void addPointer(PointerDownEvent event) {
    _resolved = false;
    _initialPosition = event.position;
    super.addPointer(event);
  }

  @override
  void handleEvent(PointerEvent event) {
    if (!_resolved && event is PointerMoveEvent) {
      final double dx = event.position.dx - _initialPosition.dx;
      final double dy = event.position.dy - _initialPosition.dy;

      // 1. If vertical movement is dominant, reject early so vertical scrollables scroll smoothly.
      if (dy.abs() > dx.abs() && dy.abs() > kTouchSlop) {
        _resolved = true;
        resolve(GestureDisposition.rejected);
        return;
      }

      // 2. Check if movement is in the correct pop direction.
      // In RTL (Arabic): pop drag is Leftward (dx < 0). Dragging Right (dx > 0) is wrong direction.
      // In LTR: pop drag is Rightward (dx > 0). Dragging Left (dx < 0) is wrong direction.
      if (dx.abs() > 4.0) {
        final bool rtl = isRtl();
        final bool isWrongDirection = rtl ? (dx > 0) : (dx < 0);
        if (isWrongDirection) {
          _resolved = true;
          resolve(GestureDisposition.rejected);
          return;
        }
      }
    }
    super.handleEvent(event);
  }

  @override
  void rejectGesture(int pointer) {
    _resolved = true;
    super.rejectGesture(pointer);
  }

  @override
  void acceptGesture(int pointer) {
    _resolved = true;
    super.acceptGesture(pointer);
  }
}

/// PageTransitionsBuilder that provides interactive full-screen swipe back
/// for PageRoutes, seamlessly integrated into ThemeData.pageTransitionsTheme.
class FullScreenSwipeBackPageTransitionsBuilder extends PageTransitionsBuilder {
  const FullScreenSwipeBackPageTransitionsBuilder({
    this.swipeThreshold = 0.35,
    this.minFlingVelocity = 300.0,
  });

  /// Fraction of screen width required to trigger pop upon release (default: 35%).
  final double swipeThreshold;

  /// Minimum fling velocity required to complete pop even if distance < threshold.
  final double minFlingVelocity;

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    if (route.fullscreenDialog) {
      return CupertinoFullscreenDialogTransition(
        primaryRouteAnimation: animation,
        secondaryRouteAnimation: secondaryAnimation,
        linearTransition: route.popGestureInProgress,
        child: child,
      );
    }

    return FullScreenSwipeBackRouteTransition(
      primaryRouteAnimation: animation,
      secondaryRouteAnimation: secondaryAnimation,
      linearTransition: route.popGestureInProgress,
      child: FullScreenBackGestureDetector<T>(
        route: route,
        swipeThreshold: swipeThreshold,
        minFlingVelocity: minFlingVelocity,
        child: child,
      ),
    );
  }
}

/// Bidirectional route transition that respects RTL (Arabic) and LTR,
/// providing smooth parallax for the underlying route and shadow elevation.
class FullScreenSwipeBackRouteTransition extends StatelessWidget {
  const FullScreenSwipeBackRouteTransition({
    super.key,
    required this.primaryRouteAnimation,
    required this.secondaryRouteAnimation,
    required this.child,
    required this.linearTransition,
  });

  final Animation<double> primaryRouteAnimation;
  final Animation<double> secondaryRouteAnimation;
  final Widget child;
  final bool linearTransition;

  @override
  Widget build(BuildContext context) {
    final bool rtl = Directionality.of(context) == TextDirection.rtl;
    final Curve curve =
        linearTransition ? Curves.linear : Curves.fastEaseInToSlowEaseOut;
    final Curve reverseCurve =
        linearTransition ? Curves.linear : Curves.fastEaseInToSlowEaseOut.flipped;

    final Animation<Offset> primaryOffsetAnimation = CurvedAnimation(
      parent: primaryRouteAnimation,
      curve: curve,
      reverseCurve: reverseCurve,
    ).drive(
      Tween<Offset>(
        begin: rtl ? const Offset(-1.0, 0.0) : const Offset(1.0, 0.0),
        end: Offset.zero,
      ),
    );

    final Animation<Offset> secondaryOffsetAnimation = CurvedAnimation(
      parent: secondaryRouteAnimation,
      curve: curve,
      reverseCurve: reverseCurve,
    ).drive(
      Tween<Offset>(
        begin: Offset.zero,
        end: rtl ? const Offset(0.33, 0.0) : const Offset(-0.33, 0.0),
      ),
    );

    return SlideTransition(
      position: secondaryOffsetAnimation,
      child: SlideTransition(
        position: primaryOffsetAnimation,
        child: DecoratedBox(
          decoration: BoxDecoration(
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.12),
                blurRadius: 18,
                spreadRadius: 1,
                offset: Offset(rtl ? 6 : -6, 0),
              ),
            ],
          ),
          child: child,
        ),
      ),
    );
  }
}

/// Interactive gesture detector that tracks pointer anywhere across the route
/// and interactively drives the route's animation controller.
class FullScreenBackGestureDetector<T> extends StatefulWidget {
  const FullScreenBackGestureDetector({
    super.key,
    required this.route,
    required this.child,
    this.swipeThreshold = 0.35,
    this.minFlingVelocity = 300.0,
  });

  final PageRoute<T> route;
  final Widget child;
  final double swipeThreshold;
  final double minFlingVelocity;

  @override
  State<FullScreenBackGestureDetector<T>> createState() =>
      _FullScreenBackGestureDetectorState<T>();
}

class _FullScreenBackGestureDetectorState<T>
    extends State<FullScreenBackGestureDetector<T>> {
  late FullScreenSwipeBackRecognizer _recognizer;
  AnimationController? _controller;
  bool _isDragging = false;

  @override
  void initState() {
    super.initState();
    _recognizer = FullScreenSwipeBackRecognizer(
      isRtl: () => Directionality.of(context) == TextDirection.rtl,
      debugOwner: this,
    )
      ..onStart = _handleDragStart
      ..onUpdate = _handleDragUpdate
      ..onEnd = _handleDragEnd
      ..onCancel = _handleDragCancel;
  }

  @override
  void dispose() {
    _recognizer.dispose();
    if (_isDragging) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (widget.route.navigator?.mounted ?? false) {
          widget.route.navigator?.didStopUserGesture();
        }
      });
    }
    super.dispose();
  }

  bool _isPopGestureEnabled() {
    // 1. Explicit scope check
    if (!FullScreenSwipeBackScope.isEnabled(context) ||
        !FullScreenSwipeBackScope.isRouteEnabled(widget.route)) {
      return false;
    }

    final route = widget.route;

    // 2. Route must be currently active and top-most (e.g. no Dialog or Sheet on top)
    if (!route.isCurrent) {
      return false;
    }

    // 3. Route must not be the root route (cannot exit app via swipe)
    if (route.isFirst) {
      return false;
    }

    // 4. Standard pop gesture checks (respects PopScope, canPop, unsaved changes, animations)
    if (!route.popGestureEnabled) {
      return false;
    }

    // 5. Must have a valid navigator and animation controller
    final controller = _resolveRouteController(route);
    return controller != null;
  }

  AnimationController? _resolveRouteController(PageRoute route) {
    try {
      final dynamic dynamicRoute = route;
      final ctrl = dynamicRoute.controller;
      if (ctrl is AnimationController) {
        return ctrl;
      }
    } catch (_) {}
    return null;
  }

  void _handlePointerDown(PointerDownEvent event) {
    if (!_isPopGestureEnabled()) {
      return;
    }

    // Do not capture pointer if touch starts inside a horizontal scrollable or exclusion zone
    if (FullScreenSwipeBackHitTestHelper.isPointerInExcludedOrHorizontalArea(
      event.position,
      event.viewId,
    )) {
      return;
    }

    _recognizer.addPointer(event);
  }

  void _handleDragStart(DragStartDetails details) {
    assert(mounted);
    final route = widget.route;
    final navigator = route.navigator;
    if (navigator == null) return;

    _controller = _resolveRouteController(route);
    if (_controller == null) return;

    _isDragging = true;
    navigator.didStartUserGesture();
  }

  void _handleDragUpdate(DragUpdateDetails details) {
    if (!_isDragging || _controller == null) return;

    final double width = context.size?.width ?? MediaQuery.sizeOf(context).width;
    if (width <= 0) return;

    final double delta = details.primaryDelta ?? 0.0;
    final bool rtl = Directionality.of(context) == TextDirection.rtl;

    // In RTL, dragging left (negative delta) moves towards pop (controller.value decreases).
    // In LTR, dragging right (positive delta) moves towards pop (controller.value decreases).
    final double logicalDeltaFraction = rtl ? (-delta / width) : (delta / width);

    final double newValue =
        (_controller!.value - logicalDeltaFraction).clamp(0.0, 1.0);
    _controller!.value = newValue;
  }

  void _handleDragEnd(DragEndDetails details) {
    if (!_isDragging || _controller == null) {
      _cleanupDrag();
      return;
    }

    final bool rtl = Directionality.of(context) == TextDirection.rtl;
    final double rawVelocity = details.velocity.pixelsPerSecond.dx;
    final double logicalVelocity = rtl ? -rawVelocity : rawVelocity;

    final double droppedValue = _controller!.value;
    final double popProgress = 1.0 - droppedValue;

    final bool shouldPop = logicalVelocity >= widget.minFlingVelocity ||
        (logicalVelocity > -widget.minFlingVelocity &&
            popProgress >= widget.swipeThreshold);

    final bool disableAnimations =
        MediaQuery.maybeOf(context)?.disableAnimations ?? false;

    final navigator = widget.route.navigator;

    if (shouldPop) {
      _controller!.animateTo(
        0.0,
        duration: disableAnimations
            ? Duration.zero
            : Duration(
                milliseconds: (droppedValue * 300).round().clamp(150, 300)),
        curve: Curves.fastEaseInToSlowEaseOut,
      ).whenCompleteOrCancel(() {
        if (widget.route.isCurrent && (navigator?.mounted ?? false)) {
          navigator!.pop();
        }
        _cleanupDrag();
      });
    } else {
      _controller!.animateTo(
        1.0,
        duration: disableAnimations
            ? Duration.zero
            : Duration(
                milliseconds:
                    ((1.0 - droppedValue) * 300).round().clamp(150, 300)),
        curve: Curves.fastEaseInToSlowEaseOut,
      ).whenCompleteOrCancel(() {
        _cleanupDrag();
      });
    }
  }

  void _handleDragCancel() {
    if (!_isDragging || _controller == null) {
      _cleanupDrag();
      return;
    }

    final bool disableAnimations =
        MediaQuery.maybeOf(context)?.disableAnimations ?? false;

    _controller!.animateTo(
      1.0,
      duration: disableAnimations ? Duration.zero : const Duration(milliseconds: 200),
      curve: Curves.fastEaseInToSlowEaseOut,
    ).whenCompleteOrCancel(() {
      _cleanupDrag();
    });
  }

  void _cleanupDrag() {
    if (_isDragging) {
      _isDragging = false;
      widget.route.navigator?.didStopUserGesture();
    }
    _controller = null;
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: _handlePointerDown,
      child: widget.child,
    );
  }
}

/// Standalone interactive container for screens displayed as in-app overlays
/// (e.g. ProductDetailsScreen, CheckoutScreen, OrderTrackingScreen inside MainShellScreen).
class FullScreenSwipeBackContainer extends StatefulWidget {
  const FullScreenSwipeBackContainer({
    super.key,
    required this.child,
    required this.onDismiss,
    this.swipeThreshold = 0.35,
    this.minFlingVelocity = 300.0,
    this.enabled = true,
  });

  final Widget child;
  final VoidCallback onDismiss;
  final double swipeThreshold;
  final double minFlingVelocity;
  final bool enabled;

  @override
  State<FullScreenSwipeBackContainer> createState() =>
      _FullScreenSwipeBackContainerState();
}

class _FullScreenSwipeBackContainerState
    extends State<FullScreenSwipeBackContainer>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late FullScreenSwipeBackRecognizer _recognizer;
  bool _isDragging = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
      value: 1.0,
    );

    _recognizer = FullScreenSwipeBackRecognizer(
      isRtl: () => Directionality.of(context) == TextDirection.rtl,
      debugOwner: this,
    )
      ..onStart = _handleDragStart
      ..onUpdate = _handleDragUpdate
      ..onEnd = _handleDragEnd
      ..onCancel = _handleDragCancel;
  }

  @override
  void dispose() {
    _recognizer.dispose();
    _controller.dispose();
    super.dispose();
  }

  void _handlePointerDown(PointerDownEvent event) {
    if (!widget.enabled || !FullScreenSwipeBackScope.isEnabled(context)) {
      return;
    }

    if (FullScreenSwipeBackHitTestHelper.isPointerInExcludedOrHorizontalArea(
      event.position,
      event.viewId,
    )) {
      return;
    }

    _recognizer.addPointer(event);
  }

  void _handleDragStart(DragStartDetails details) {
    _isDragging = true;
  }

  void _handleDragUpdate(DragUpdateDetails details) {
    if (!_isDragging) return;

    final double width = context.size?.width ?? MediaQuery.sizeOf(context).width;
    if (width <= 0) return;

    final double delta = details.primaryDelta ?? 0.0;
    final bool rtl = Directionality.of(context) == TextDirection.rtl;
    final double logicalDeltaFraction = rtl ? (-delta / width) : (delta / width);

    _controller.value = (_controller.value - logicalDeltaFraction).clamp(0.0, 1.0);
  }

  void _handleDragEnd(DragEndDetails details) {
    if (!_isDragging) return;
    _isDragging = false;

    final bool rtl = Directionality.of(context) == TextDirection.rtl;
    final double rawVelocity = details.velocity.pixelsPerSecond.dx;
    final double logicalVelocity = rtl ? -rawVelocity : rawVelocity;

    final double droppedValue = _controller.value;
    final double popProgress = 1.0 - droppedValue;

    final bool shouldDismiss = logicalVelocity >= widget.minFlingVelocity ||
        (logicalVelocity > -widget.minFlingVelocity &&
            popProgress >= widget.swipeThreshold);

    final bool disableAnimations =
        MediaQuery.maybeOf(context)?.disableAnimations ?? false;

    if (shouldDismiss) {
      _controller.animateTo(
        0.0,
        duration: disableAnimations
            ? Duration.zero
            : Duration(
                milliseconds: (droppedValue * 300).round().clamp(150, 300)),
        curve: Curves.fastEaseInToSlowEaseOut,
      ).whenCompleteOrCancel(() {
        if (mounted) {
          widget.onDismiss();
        }
      });
    } else {
      _controller.animateTo(
        1.0,
        duration: disableAnimations
            ? Duration.zero
            : Duration(
                milliseconds:
                    ((1.0 - droppedValue) * 300).round().clamp(150, 300)),
        curve: Curves.fastEaseInToSlowEaseOut,
      );
    }
  }

  void _handleDragCancel() {
    if (!_isDragging) return;
    _isDragging = false;

    final bool disableAnimations =
        MediaQuery.maybeOf(context)?.disableAnimations ?? false;

    _controller.animateTo(
      1.0,
      duration: disableAnimations ? Duration.zero : const Duration(milliseconds: 200),
      curve: Curves.fastEaseInToSlowEaseOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final bool rtl = Directionality.of(context) == TextDirection.rtl;
    final Offset beginOffset =
        rtl ? const Offset(-1.0, 0.0) : const Offset(1.0, 0.0);

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final double value = _controller.value;
        final Offset offset = Offset.lerp(beginOffset, Offset.zero, value)!;
        final double opacity = (1.0 - value).clamp(0.0, 1.0);

        return Stack(
          fit: StackFit.passthrough,
          children: [
            // Dark scrim behind sliding container
            if (value < 0.999)
              Positioned.fill(
                child: Container(
                  color: Colors.black.withValues(alpha: 0.3 * (1.0 - opacity)),
                ),
              ),
            FractionalTranslation(
              translation: offset,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  boxShadow: value < 0.999
                      ? [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.15),
                            blurRadius: 16,
                            spreadRadius: 2,
                            offset: Offset(rtl ? 8 : -8, 0),
                          ),
                        ]
                      : const [],
                ),
                child: child,
              ),
            ),
          ],
        );
      },
      child: Listener(
        behavior: HitTestBehavior.translucent,
        onPointerDown: _handlePointerDown,
        child: widget.child,
      ),
    );
  }
}
