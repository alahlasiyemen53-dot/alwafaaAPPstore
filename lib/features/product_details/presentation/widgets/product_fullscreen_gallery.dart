import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/widgets/cached_image_widget.dart';

/// Full-screen product gallery with swipe + pinch-zoom (RTL-safe).
class ProductFullscreenGallery extends StatefulWidget {
  final List<String> images;
  final int initialIndex;

  const ProductFullscreenGallery({
    super.key,
    required this.images,
    this.initialIndex = 0,
  });

  static Future<void> open(
    BuildContext context, {
    required List<String> images,
    int initialIndex = 0,
  }) {
    if (images.isEmpty) return Future.value();
    return Navigator.of(context).push(
      PageRouteBuilder(
        opaque: false,
        barrierColor: Colors.black.withValues(alpha: 0.92),
        pageBuilder: (_, __, ___) => ProductFullscreenGallery(
          images: images,
          initialIndex: initialIndex.clamp(0, images.length - 1),
        ),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    );
  }

  @override
  State<ProductFullscreenGallery> createState() =>
      _ProductFullscreenGalleryState();
}

class _ProductFullscreenGalleryState extends State<ProductFullscreenGallery> {
  late final PageController _pageController;
  late int _index;
  final Map<int, TransformationController> _transforms = {};

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _pageController = PageController(initialPage: _index);
  }

  @override
  void dispose() {
    _pageController.dispose();
    for (final controller in _transforms.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TransformationController _controllerFor(int index) {
    return _transforms.putIfAbsent(index, TransformationController.new);
  }

  void _resetZoom(int index) {
    final controller = _transforms[index];
    if (controller == null) return;
    controller.value = Matrix4.identity();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: SafeArea(
        child: Stack(
          children: [
            PageView.builder(
              controller: _pageController,
              itemCount: widget.images.length,
              onPageChanged: (index) {
                _resetZoom(_index);
                setState(() => _index = index);
              },
              itemBuilder: (context, index) {
                return InteractiveViewer(
                  transformationController: _controllerFor(index),
                  minScale: 1,
                  maxScale: 4,
                  child: Center(
                    child: CachedImageWidget(
                      key: ValueKey('fullscreen-${widget.images[index]}'),
                      imageUrl: widget.images[index],
                      fit: BoxFit.contain,
                    ),
                  ),
                );
              },
            ),
            PositionedDirectional(
              top: 8.h,
              start: 8.w,
              child: IconButton(
                style: IconButton.styleFrom(
                  backgroundColor: Colors.black45,
                  foregroundColor: Colors.white,
                ),
                icon: const Icon(Icons.close_rounded),
                onPressed: () => Navigator.of(context).pop(),
              ),
            ),
            if (widget.images.length > 1)
              Positioned(
                bottom: 20.h,
                left: 0,
                right: 0,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '${_index + 1} / ${widget.images.length}',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13.sp,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
