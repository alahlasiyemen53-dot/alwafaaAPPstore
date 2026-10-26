import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/home_banner_slider.dart';
import 'package:store/data/models/catalog/home_api_model.dart';
import 'package:store/features/home/presentation/widgets/editorial_hero_banner.dart';

/// Auto-playing home banner carousel over the existing banner list.
///
/// Rules:
/// - 0 banners → hidden
/// - 1 banner → static (no timer / indicator / loop)
/// - >1 banners → autoplay + swipe + loop + indicator
class HomeBannerAutoSlider extends StatefulWidget {
  final List<HomeBannerModel> banners;
  final VoidCallback onExploreTap;
  final ValueChanged<HomeBannerModel>? onBannerTap;

  const HomeBannerAutoSlider({
    super.key,
    required this.banners,
    required this.onExploreTap,
    this.onBannerTap,
  });

  @override
  State<HomeBannerAutoSlider> createState() => _HomeBannerAutoSliderState();
}

class _HomeBannerAutoSliderState extends State<HomeBannerAutoSlider>
    with WidgetsBindingObserver {
  PageController? _pageController;
  Timer? _autoPlayTimer;
  Timer? _resumeTimer;
  int _logicalIndex = 0;
  int _generation = 0;
  bool _userInteracting = false;
  bool _appInForeground = true;
  List<int> _bannerIds = const [];

  bool get _isMulti => widget.banners.length > 1;
  int get _count => widget.banners.length;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _bannerIds = widget.banners.map((b) => b.id).toList(growable: false);
    _configureController();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _scheduleAutoPlay();
    });
  }

  @override
  void didUpdateWidget(covariant HomeBannerAutoSlider oldWidget) {
    super.didUpdateWidget(oldWidget);
    final nextIds = widget.banners.map((b) => b.id).toList(growable: false);
    if (!_sameIds(_bannerIds, nextIds)) {
      _bannerIds = nextIds;
      _generation++;
      _logicalIndex = 0;
      _disposeController();
      _configureController();
      _scheduleAutoPlay();
      return;
    }
    if (!_isMulti) {
      _stopAutoPlay();
    } else if (_autoPlayTimer == null) {
      _scheduleAutoPlay();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final foreground = state == AppLifecycleState.resumed;
    if (_appInForeground == foreground) return;
    _appInForeground = foreground;
    if (foreground) {
      _scheduleAutoPlay();
    } else {
      _stopAutoPlay();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopAutoPlay();
    _resumeTimer?.cancel();
    _resumeTimer = null;
    _disposeController();
    super.dispose();
  }

  void _configureController() {
    if (!_isMulti) {
      _pageController = null;
      return;
    }
    final initial =
        _count * (HomeBannerSliderConstants.loopMultiplier ~/ 2);
    _pageController = PageController(initialPage: initial);
    _logicalIndex = 0;
  }

  void _disposeController() {
    _pageController?.dispose();
    _pageController = null;
  }

  void _scheduleAutoPlay() {
    _stopAutoPlay();
    if (!_canAutoPlay) return;

    final generation = _generation;
    _autoPlayTimer = Timer.periodic(
      HomeBannerSliderConstants.autoPlayInterval,
      (_) {
        if (!mounted || generation != _generation) return;
        if (!_canAutoPlay) return;
        final controller = _pageController;
        if (controller == null || !controller.hasClients) return;
        final next = (controller.page?.round() ?? controller.initialPage) + 1;
        controller.animateToPage(
          next,
          duration: HomeBannerSliderConstants.transitionDuration,
          curve: Curves.easeInOut,
        );
      },
    );
  }

  void _stopAutoPlay() {
    _autoPlayTimer?.cancel();
    _autoPlayTimer = null;
  }

  bool get _canAutoPlay {
    if (!_isMulti || !_appInForeground || _userInteracting) return false;
    if (!mounted) return false;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    return !reduceMotion;
  }

  void _onPageChanged(int page) {
    if (!_isMulti) return;
    final logical = page % _count;
    if (logical == _logicalIndex) return;
    setState(() => _logicalIndex = logical);
  }

  void _onInteractionStart() {
    if (!_isMulti) return;
    _userInteracting = true;
    _stopAutoPlay();
  }

  void _onInteractionEnd() {
    if (!_isMulti || !_userInteracting) return;
    _userInteracting = false;
    _stopAutoPlay();
    _resumeTimer?.cancel();
    final generation = _generation;
    _resumeTimer = Timer(
      HomeBannerSliderConstants.resumeAfterInteraction,
      () {
        if (!mounted || generation != _generation || _userInteracting) return;
        _scheduleAutoPlay();
      },
    );
  }

  bool _sameIds(List<int> a, List<int> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return false;
    }
    return true;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) {
      return const SizedBox.shrink(key: Key('home-banner-slider-empty'));
    }

    if (!_isMulti) {
      return EditorialHeroBanner(
        key: const Key('home-banner-slider-single'),
        banner: widget.banners.first,
        onExploreTap: widget.onExploreTap,
        onBannerTap: widget.onBannerTap,
      );
    }

    return Column(
      key: const Key('home-banner-slider-multi'),
      mainAxisSize: MainAxisSize.min,
      children: [
        AspectRatio(
          aspectRatio: HomeBannerSliderConstants.aspectRatio,
          child: NotificationListener<ScrollNotification>(
            onNotification: (notification) {
              if (notification is ScrollStartNotification &&
                  notification.dragDetails != null) {
                _onInteractionStart();
              } else if (notification is ScrollEndNotification &&
                  _userInteracting) {
                _onInteractionEnd();
              }
              return false;
            },
            child: PageView.builder(
              key: const Key('home-banner-page-view'),
              controller: _pageController,
              onPageChanged: _onPageChanged,
              itemBuilder: (context, index) {
                final banner = widget.banners[index % _count];
                return EditorialHeroBanner(
                  banner: banner,
                  expandToParent: true,
                  onExploreTap: widget.onExploreTap,
                  onBannerTap: widget.onBannerTap,
                );
              },
            ),
          ),
        ),
        SizedBox(height: 10.h),
        _BannerPageIndicator(
          count: _count,
          index: _logicalIndex,
        ),
        SizedBox(height: 4.h),
      ],
    );
  }
}

class _BannerPageIndicator extends StatelessWidget {
  final int count;
  final int index;

  const _BannerPageIndicator({
    required this.count,
    required this.index,
  });

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'بانر ${index + 1} من $count',
      child: Row(
        key: const Key('home-banner-indicator'),
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(count, (i) {
          final active = i == index;
          return AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            margin: EdgeInsets.symmetric(horizontal: 3.w),
            width: active ? 16.w : 6.w,
            height: 6.h,
            decoration: BoxDecoration(
              color: active
                  ? AppColors.burgundy
                  : AppColors.gold.withValues(alpha: 0.28),
              borderRadius: BorderRadius.circular(8.r),
            ),
          );
        }),
      ),
    );
  }
}
