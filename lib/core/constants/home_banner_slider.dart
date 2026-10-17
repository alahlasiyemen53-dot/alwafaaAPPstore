/// Central timing/layout defaults for the Home Banner auto slider.
class HomeBannerSliderConstants {
  HomeBannerSliderConstants._();

  /// Default autoplay interval when more than one banner is present.
  static const Duration autoPlayInterval = Duration(seconds: 5);

  /// Page transition animation duration.
  static const Duration transitionDuration = Duration(milliseconds: 400);

  /// Delay before autoplay resumes after a manual swipe.
  static const Duration resumeAfterInteraction = Duration(seconds: 5);

  /// Stable banner frame matching the editorial hero proportions.
  static const double aspectRatio = 16 / 9.2;

  /// Virtual page multiplier for seamless looping (must be even-ish large).
  static const int loopMultiplier = 1000;
}
