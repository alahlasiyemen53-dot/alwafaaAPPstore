import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/features/product_details/presentation/widgets/product_fullscreen_gallery.dart';

class ProductGallery extends StatefulWidget {
  final List<String> images;
  /// Optional higher-res URLs for fullscreen (falls back to [images]).
  final List<String>? fullscreenImages;

  const ProductGallery({
    super.key,
    required this.images,
    this.fullscreenImages,
  });

  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  int _currentIndex = 0;
  final PageController _pageController = PageController();
  /// Guards against stale gallery updates when variants change rapidly.
  int _imagesGeneration = 0;

  @override
  void didUpdateWidget(covariant ProductGallery oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_sameImages(oldWidget.images, widget.images)) return;

    _imagesGeneration++;
    setState(() => _currentIndex = 0);
    if (_pageController.hasClients) {
      _pageController.jumpToPage(0);
    } else {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _pageController.hasClients) {
          _pageController.jumpToPage(0);
        }
      });
    }
  }

  bool _sameImages(List<String> first, List<String> second) {
    if (first.length != second.length) return false;
    for (var index = 0; index < first.length; index++) {
      if (first[index] != second[index]) return false;
    }
    return true;
  }

  List<String> get _fullscreenImages {
    final large = widget.fullscreenImages;
    if (large != null && large.isNotEmpty) return large;
    return widget.images;
  }

  Future<void> _openFullscreen(int index) async {
    final generation = _imagesGeneration;
    await ProductFullscreenGallery.open(
      context,
      images: _fullscreenImages,
      initialIndex: index,
    );
    if (!mounted || generation != _imagesGeneration) return;
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final images = widget.images;

    return Column(
      children: [
        AspectRatio(
          aspectRatio: 1.05,
          child: Stack(
            children: [
              if (images.isEmpty)
                const _ProductImagePlaceholder()
              else
                PageView.builder(
                  controller: _pageController,
                  itemCount: images.length,
                  onPageChanged: (index) {
                    setState(() => _currentIndex = index);
                  },
                  itemBuilder: (context, index) {
                    return GestureDetector(
                      onTap: () => _openFullscreen(index),
                      child: Container(
                        color: AppColors.surfaceSoft,
                        child: CachedImageWidget(
                          key: ValueKey(
                              'product-gallery-image-${images[index]}'),
                          imageUrl: images[index],
                          fit: BoxFit.contain,
                        ),
                      ),
                    );
                  },
                ),
              if (images.length > 1)
                Positioned(
                  bottom: 12.h,
                  left: 0,
                  right: 0,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(images.length, (index) {
                      final isSelected = _currentIndex == index;
                      return AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        margin: EdgeInsets.symmetric(horizontal: 3.w),
                        width: isSelected ? 18.w : 6.w,
                        height: 6.w,
                        decoration: BoxDecoration(
                          color: isSelected
                              ? AppColors.primaryDark
                              : AppColors.border,
                          borderRadius: BorderRadius.circular(4.r),
                        ),
                      );
                    }),
                  ),
                ),
            ],
          ),
        ),
        if (images.length > 1) ...[
          SizedBox(height: 10.h),
          SizedBox(
            height: 56.h,
            child: ListView.separated(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              scrollDirection: Axis.horizontal,
              itemCount: images.length,
              separatorBuilder: (_, _) => SizedBox(width: 8.w),
              itemBuilder: (context, index) {
                final isSelected = _currentIndex == index;
                return GestureDetector(
                  onTap: () {
                    _pageController.animateToPage(
                      index,
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeInOut,
                    );
                  },
                  child: Container(
                    width: 52.w,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12.r),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.primaryDark
                            : AppColors.border,
                        width: isSelected ? 2 : 0.8,
                      ),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: ColoredBox(
                      color: AppColors.surfaceSoft,
                      child: CachedImageWidget(
                        imageUrl: images[index],
                        fit: BoxFit.contain,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }
}

class _ProductImagePlaceholder extends StatelessWidget {
  const _ProductImagePlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const Key('product-gallery-placeholder'),
      color: AppColors.surfaceSoft,
      alignment: Alignment.center,
      child: const Icon(
        Icons.image_not_supported_rounded,
        color: AppColors.textMuted,
        size: 40,
      ),
    );
  }
}
