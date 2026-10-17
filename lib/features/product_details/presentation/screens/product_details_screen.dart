import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/core/widgets/luxury_badge.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/rating_stars.dart';
import 'package:store/data/models/catalog/product_detail_api_model.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/data/repositories/catalog_repository.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/cart/cubit/cart_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/discovery/cubit/recently_viewed_cubit.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:store/core/config/app_config.dart';
import 'package:store/features/wishlist/cubit/wishlist_cubit.dart';
import 'package:share_plus/share_plus.dart';
import '../../cubit/related_products_cubit.dart';
import '../widgets/fragrance_notes_view.dart';
import '../widgets/product_gallery.dart';
import '../widgets/product_reviews_section.dart';
import '../widgets/related_products_section.dart';

class ProductDetailsScreen extends StatefulWidget {
  final ProductModel product;

  const ProductDetailsScreen({super.key, required this.product});

  static void open(
    BuildContext context,
    int productId, {
    ProductModel? product,
  }) {
    final targetProduct = product ?? ProductModel.placeholderForId(productId);
    try {
      final appShell = context.read<AppShellCubit>();
      appShell.openProductDetails(targetProduct);
    } catch (_) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProductDetailsScreen(product: targetProduct),
        ),
      );
    }
  }

  @override
  State<ProductDetailsScreen> createState() => _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends State<ProductDetailsScreen> {
  int _quantity = 1;
  String? _selectedSize;
  ProductShade? _selectedShade;
  ProductVariantApiModel? _selectedVariant;
  ProductDetailApiModel? _detail;
  ProductModel? _loadedProduct;
  String? _detailError;
  int _refreshCounter = 0;
  late final RelatedProductsCubit _relatedProductsCubit;

  ProductModel get _effectiveProduct => _loadedProduct ?? widget.product;

  @override
  void initState() {
    super.initState();
    _relatedProductsCubit = RelatedProductsCubit();
    final relatedTargetId = widget.product.serverId ??
        int.tryParse(widget.product.id) ??
        widget.product.id;
    _relatedProductsCubit.loadRelatedProducts(relatedTargetId);

    _detail = widget.product.detailApiModel;
    if (_detail != null) {
      _loadedProduct = ProductModel.fromApiDetail(_detail!);
    }

    final targetId = widget.product.serverId ?? int.tryParse(widget.product.id);
    if (targetId != null && targetId > 0) {
      if (_detail == null || widget.product.priceSAR <= 0 || widget.product.name.isEmpty) {
        _fetchProductDetail(targetId);
      }
    }

    if (widget.product.name.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        context.read<RecentlyViewedCubit>().recordView(widget.product);
      });
    }
  }

  @override
  void didUpdateWidget(covariant ProductDetailsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.product.id != widget.product.id) {
      setState(_resetForCurrentProduct);

      final relatedTargetId = widget.product.serverId ??
          int.tryParse(widget.product.id) ??
          widget.product.id;
      _relatedProductsCubit.loadRelatedProducts(relatedTargetId);

      final targetId = _currentProductId;
      if (targetId != null && targetId > 0) {
        if (_detail == null || widget.product.priceSAR <= 0 || widget.product.name.isEmpty) {
          _fetchProductDetail(targetId);
        }
      }

      if (_effectiveProduct.name.isNotEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          context.read<RecentlyViewedCubit>().recordView(_effectiveProduct);
        });
      }
    }
  }

  int? get _currentProductId =>
      widget.product.serverId ?? int.tryParse(widget.product.id);

  void _resetForCurrentProduct() {
    _quantity = 1;
    _detail = widget.product.detailApiModel;
    _loadedProduct = widget.product.detailApiModel != null
        ? ProductModel.fromApiDetail(widget.product.detailApiModel!)
        : null;
    _detailError = null;
    _selectedVariant = null;
    _selectedSize = null;
    _selectedShade = null;
  }

  @override
  void dispose() {
    _relatedProductsCubit.close();
    super.dispose();
  }

  Future<void> _fetchProductDetail([int? targetId]) async {
    final id = targetId ?? _currentProductId;
    if (id == null || id <= 0) return;

    setState(() {
      _detailError = null;
    });

    try {
      final repository = CatalogRepositoryImpl();
      final detail = await repository.getProductDetail(id);
      if (mounted && _currentProductId == id) {
        final fullProduct = ProductModel.fromApiDetail(detail);
        setState(() {
          _detail = detail;
          _loadedProduct = fullProduct;
          if (_selectedVariant != null) {
            _selectedVariant = detail.variants
                .where((v) => v.id == _selectedVariant!.id)
                .firstOrNull;
          }
        });

        context.read<RecentlyViewedCubit>().recordView(fullProduct);
      }
    } catch (e) {
      if (mounted && _currentProductId == id) {
        setState(() {
          _detailError = e.toString();
        });
      }
    }
  }

  Future<void> _onRefresh() async {
    final targetId = _currentProductId;
    final futures = <Future<dynamic>>[];
    if (targetId != null && targetId > 0) {
      futures.add(_fetchProductDetail(targetId));
    }
    final relatedTargetId = widget.product.serverId ??
        int.tryParse(widget.product.id) ??
        widget.product.id;
    futures.add(_relatedProductsCubit.loadRelatedProducts(relatedTargetId,
        force: true));

    await Future.wait(futures);
    if (mounted) {
      setState(() {
        _refreshCounter++;
      });
    }
  }

  void _onSelectVariant(ProductVariantApiModel variant) {
    setState(() {
      // Tap same color again → clear selection and restore main gallery
      if (_selectedVariant?.id == variant.id) {
        _selectedVariant = null;
        _selectedSize = null;
        _selectedShade = null;
        return;
      }

      _selectedVariant = variant;
      _selectedSize = null;
      _selectedShade = null;
      for (final attr in variant.attributes) {
        final code = attr.code.toLowerCase();
        if (code.contains('size') || code.contains('volume')) {
          _selectedSize = attr.displayValue;
        } else if (code.contains('shade') ||
            code.contains('color') ||
            code.contains('لون')) {
          _selectedShade =
              ProductShade(name: attr.displayValue, hex: '#C5A880');
        }
      }
    });
  }

  bool _isColorAttribute(ProductVariantAttributeValue attr) {
    final code = attr.code.toLowerCase();
    return code.contains('shade') ||
        code.contains('color') ||
        code.contains('لون');
  }

  List<ProductVariantApiModel> get _colorVariants {
    final variants = _detail?.variants ?? const <ProductVariantApiModel>[];
    return variants
        .where((v) => v.attributes.any(_isColorAttribute))
        .toList();
  }

  List<ProductVariantApiModel> get _nonColorVariants {
    final variants = _detail?.variants ?? const <ProductVariantApiModel>[];
    if (_colorVariants.isEmpty) {
      return variants;
    }
    return variants
        .where((v) => !v.attributes.any(_isColorAttribute))
        .toList();
  }

  List<String> get _mainGalleryImages {
    final listImages = <String>[
      if (_isUsableImageUrl(_effectiveProduct.image)) _effectiveProduct.image,
      ..._effectiveProduct.images.where(_isUsableImageUrl),
    ];
    // Preserve insertion order while dropping duplicates.
    final seen = <String>{};
    final stableListImages = <String>[];
    for (final url in listImages) {
      if (seen.add(url)) stableListImages.add(url);
    }

    final productImages = _detail?.mediaGallery
            .map((image) => image.optimizedUrl)
            .where(_isUsableImageUrl)
            .toList() ??
        const <String>[];

    if (productImages.isEmpty) {
      return stableListImages;
    }

    // Keep the already-cached list image first so opening details does not
    // flash a reload when the detail payload uses the same media.
    final merged = <String>[];
    final mergedSeen = <String>{};
    for (final url in [...stableListImages, ...productImages]) {
      if (mergedSeen.add(url)) merged.add(url);
    }
    return merged;
  }

  bool _isUsableImageUrl(String url) =>
      url.isNotEmpty && url != 'assets/images/placeholder.png';

  List<String> get _displayGalleryImages {
    // Color-specific images only when the selected variant actually has them;
    // otherwise keep showing the main product gallery.
    if (_selectedVariant != null) {
      final variantImages = _selectedVariant!.images
          .map((image) => image.optimizedUrl)
          .where(_isUsableImageUrl)
          .toList();
      if (variantImages.isNotEmpty) return variantImages;
    }
    return _mainGalleryImages;
  }

  List<String> get _displayFullscreenImages {
    if (_selectedVariant != null) {
      final variantImages = _selectedVariant!.images
          .map((image) => image.largeUrl)
          .where(_isUsableImageUrl)
          .toList();
      if (variantImages.isNotEmpty) return variantImages;
    }
    final fromDetail = _detail?.mediaGallery
            .map((image) => image.largeUrl)
            .where(_isUsableImageUrl)
            .toList() ??
        const <String>[];
    if (fromDetail.isNotEmpty) return fromDetail;
    return _displayGalleryImages;
  }

  String get _mainImageThumb {
    final main = _mainGalleryImages;
    if (main.isNotEmpty) return main.first;
    if (_isUsableImageUrl(_effectiveProduct.image)) return _effectiveProduct.image;
    return '';
  }

  int? get _resolvedVariantId {
    if (_selectedVariant != null) return _selectedVariant!.id;
    if (_effectiveProduct.selectedVariantId != null) {
      return _effectiveProduct.selectedVariantId;
    }
    final defaultVar = _detail?.defaultVariant;
    if (defaultVar != null) return defaultVar.id;
    final variants = _detail?.variants ?? const <ProductVariantApiModel>[];
    if (variants.length == 1) return variants.first.id;
    return null;
  }

  String? get _resolvedVariantSku {
    if (_selectedVariant != null) return _selectedVariant!.sku;
    if (_effectiveProduct.selectedVariantSku != null) {
      return _effectiveProduct.selectedVariantSku;
    }
    final defaultVar = _detail?.defaultVariant;
    if (defaultVar != null) return defaultVar.sku;
    final variants = _detail?.variants ?? const <ProductVariantApiModel>[];
    if (variants.length == 1) return variants.first.sku;
    return null;
  }

  Future<void> _shareProduct() async {
    final productId = _effectiveProduct.serverId ?? int.tryParse(_effectiveProduct.id) ?? 0;
    final shareUrl = AppConfig.resolveProductShareUrl(productId);
    final productName = _detail?.displayName ?? _effectiveProduct.name;
    final brandName = _detail?.brand?.displayName ?? _effectiveProduct.brand;

    final currencyCubit = context.read<CurrencyCubit>();
    final defaultPrice = _detail?.defaultVariant?.price.amount ??
        (_detail?.variants.isNotEmpty == true
            ? _detail!.variants.first.price.amount
            : _effectiveProduct.priceSAR);
    final currentPrice = _selectedVariant != null
        ? _selectedVariant!.price.amount
        : defaultPrice;
    final formattedPrice = currencyCubit.state.formatProductBasePrice(currentPrice);

    final buffer = StringBuffer();
    buffer.writeln(productName);
    if (brandName.trim().isNotEmpty) {
      buffer.writeln('الماركة: $brandName');
    }
    buffer.writeln('السعر: $formattedPrice');
    buffer.writeln();
    buffer.writeln('تسوّق هذا المنتج الفاخر الآن عبر تطبيق لمّة:');
    buffer.writeln(shareUrl);
    final shareText = buffer.toString();

    final box = context.findRenderObject() as RenderBox?;
    final origin = box != null ? box.localToGlobal(Offset.zero) & box.size : null;

    XFile? imageFile;
    final candidateImg = _displayGalleryImages.isNotEmpty
        ? _displayGalleryImages.first
        : _effectiveProduct.image;

    if (candidateImg.startsWith('http://') || candidateImg.startsWith('https://')) {
      try {
        final file = await DefaultCacheManager().getSingleFile(candidateImg);
        imageFile = XFile(file.path);
      } catch (_) {}
    }

    try {
      if (imageFile != null) {
        // ignore: deprecated_member_use
        await Share.shareXFiles(
          [imageFile],
          text: shareText,
          subject: productName,
          sharePositionOrigin: origin,
        );
      } else {
        // ignore: deprecated_member_use
        await Share.share(
          shareText,
          subject: productName,
          sharePositionOrigin: origin,
        );
      }
    } catch (_) {
      if (mounted) {
        context.showAppSnackBar(
          'تم تجهيز رابط المشاركة: $shareUrl',
          type: AppSnackBarType.success,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_detail == null && _effectiveProduct.name.isEmpty && _detailError == null) {
      return Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded),
            onPressed: () =>
                context.read<AppShellCubit>().closeProductDetails(),
          ),
          title: Text(
            'جاري تحميل المنتج...',
            style: TextStyle(
              fontSize: 14.sp,
              fontWeight: FontWeight.w600,
              color: AppColors.primaryDark,
            ),
          ),
        ),
        body: const Center(
          child: CircularProgressIndicator(
            color: AppColors.primaryDark,
          ),
        ),
      );
    }

    final isWishlisted =
        context.watch<WishlistCubit>().state.contains(_effectiveProduct.id);
    final currencyCubit = context.watch<CurrencyCubit>();

    final defaultPrice = _detail?.defaultVariant?.price.amount ??
        (_detail?.variants.isNotEmpty == true
            ? _detail!.variants.first.price.amount
            : _effectiveProduct.priceSAR);
    final currentPrice = _selectedVariant != null
        ? _selectedVariant!.price.amount
        : defaultPrice;

    final defaultCompareAt = _detail?.defaultVariant?.price.compareAt ??
        (_detail?.variants.isNotEmpty == true
            ? _detail!.variants.first.price.compareAt
            : _effectiveProduct.originalPriceSAR);
    final compareAtPrice = _selectedVariant?.price.compareAt ?? defaultCompareAt;

    final formattedPrice =
        currencyCubit.state.formatProductBasePrice(currentPrice * _quantity);
    final formattedCompareAt = compareAtPrice != null && compareAtPrice > currentPrice
        ? currencyCubit.state.formatProductBasePrice(compareAtPrice * _quantity)
        : null;

    final isAvailable = _selectedVariant != null
        ? _selectedVariant!.availability.isAvailable
        : _effectiveProduct.inStock;

    final availabilityStatus = _selectedVariant != null
        ? _selectedVariant!.availability.status
        : (_effectiveProduct.inStock ? 'in_stock' : 'out_of_stock');

    final galleryImages = _displayGalleryImages;
    final fullscreenImages = _displayFullscreenImages;

    return BlocProvider.value(
      value: _relatedProductsCubit,
      child: Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded),
            onPressed: () =>
                context.read<AppShellCubit>().closeProductDetails(),
          ),
          title: Column(
            children: [
              Text(
                _detail?.brand?.displayName ?? _effectiveProduct.brand,
                style: GoogleFonts.cormorantGaramond(
                  fontSize: 12.sp,
                  fontWeight: FontWeight.w700,
                  color: AppColors.goldDark,
                  letterSpacing: 1.2,
                ),
              ),
              Text(
                _detail?.displayName ?? _effectiveProduct.name,
                style: TextStyle(
                  fontSize: 12.sp,
                  fontWeight: FontWeight.bold,
                  color: AppColors.primaryDark,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
          actions: [
            IconButton(
              icon: Icon(
                isWishlisted
                    ? Icons.favorite_rounded
                    : Icons.favorite_border_rounded,
                color: isWishlisted
                    ? AppColors.discountBadge
                    : AppColors.primaryDark,
              ),
              onPressed: () async {
                context.read<WishlistCubit>().toggleWishlist(_effectiveProduct.id,
                    guestProduct: _effectiveProduct);
              },
            ),
            IconButton(
              icon: const Icon(Icons.share_outlined),
              tooltip: 'مشاركة المنتج',
              onPressed: _shareProduct,
            ),
          ],
        ),
        body: Column(
          children: [
            if (_detailError != null)
              Container(
                color: AppColors.warning.withValues(alpha: 0.12),
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 6.h),
                child: Row(
                  children: [
                    Icon(Icons.info_outline,
                        size: 16.w, color: AppColors.warning),
                    SizedBox(width: 8.w),
                    Expanded(
                      child: Text(
                        'يتم عرض البيانات الاحتياطية (تعذر جلب الخيارات من الخادم)',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 10.sp, color: AppColors.warning),
                      ),
                    ),
                    TextButton(
                      onPressed: _fetchProductDetail,
                      style: TextButton.styleFrom(
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.symmetric(horizontal: 8.w),
                      ),
                      child: const Text('إعادة المحاولة',
                          style: TextStyle(fontSize: 11)),
                    ),
                  ],
                ),
              ),

            Expanded(
              child: RefreshIndicator(
                color: AppColors.primaryDark,
                backgroundColor: Colors.white,
                onRefresh: _onRefresh,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: EdgeInsets.only(bottom: 24.h),
                  children: [
                  // Gallery
                  ProductGallery(
                    images: galleryImages,
                    fullscreenImages: fullscreenImages,
                  ),

                  Padding(
                    padding: EdgeInsets.all(16.w),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Badges & Availability
                        Wrap(
                          spacing: 6.w,
                          runSpacing: 6.h,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            if (_effectiveProduct.isExclusive) ...[
                              const LuxuryBadge(
                                  text: 'حصري',
                                  type: LuxuryBadgeType.exclusive),
                              SizedBox(width: 6.w),
                            ],
                            if (_effectiveProduct.isBestSeller) ...[
                              const LuxuryBadge(
                                  text: 'الأكثر مبيعاً',
                                  type: LuxuryBadgeType.bestSeller),
                              SizedBox(width: 6.w),
                            ],
                            if (_effectiveProduct.isNew) ...[
                              const LuxuryBadge(
                                  text: 'جديد', type: LuxuryBadgeType.isNew),
                              SizedBox(width: 6.w),
                            ],
                            if (_selectedVariant?.price.hasDiscount == true &&
                                _selectedVariant?.price.discountPercentage !=
                                    null)
                              LuxuryBadge(
                                text:
                                    'خصم ${_selectedVariant!.price.discountPercentage!.toStringAsFixed(0)}%',
                                type: LuxuryBadgeType.discount,
                              ),
                            RatingStars(
                              rating: _detail?.ratingSummary.average ??
                                  _effectiveProduct.rating,
                              reviewsCount:
                                  _detail?.ratingSummary.reviewsCount ??
                                      _effectiveProduct.reviewsCount,
                              starSize: 16.w,
                            ),
                          ],
                        ),
                        SizedBox(height: 12.h),

                        // Title
                        Text(
                          _detail?.displayName ?? _effectiveProduct.name,
                          style: AppTypography.titleLarge.copyWith(
                            fontWeight: FontWeight.w800,
                            fontSize: 18.sp,
                            color: AppColors.primaryDark,
                            height: 1.3,
                          ),
                        ),
                        SizedBox(height: 6.h),

                        // Price & Availability Tag
                        Wrap(
                          spacing: 10.w,
                          runSpacing: 8.h,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              formattedPrice,
                              style: TextStyle(
                                fontSize: 20.sp,
                                fontWeight: FontWeight.w900,
                                color: AppColors.primaryDark,
                              ),
                            ),
                            if (formattedCompareAt != null) ...[
                              SizedBox(width: 10.w),
                              Text(
                                formattedCompareAt,
                                style: TextStyle(
                                  fontSize: 14.sp,
                                  color: AppColors.textMuted,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                            // Availability Tag
                            Container(
                              padding: EdgeInsets.symmetric(
                                  horizontal: 10.w, vertical: 4.h),
                              decoration: BoxDecoration(
                                color: availabilityStatus == 'in_stock'
                                    ? AppColors.success.withValues(alpha: 0.1)
                                    : availabilityStatus == 'on_demand'
                                        ? AppColors.gold.withValues(alpha: 0.15)
                                        : AppColors.error.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(12.r),
                              ),
                              child: Text(
                                availabilityStatus == 'in_stock'
                                    ? 'متوفر في المخزون'
                                    : availabilityStatus == 'on_demand'
                                        ? 'متوفر تحت الطلب'
                                        : 'نفد من المخزون',
                                style: TextStyle(
                                  fontSize: 10.sp,
                                  fontWeight: FontWeight.bold,
                                  color: availabilityStatus == 'in_stock'
                                      ? AppColors.success
                                      : availabilityStatus == 'on_demand'
                                          ? AppColors.goldDark
                                          : AppColors.error,
                                ),
                              ),
                            ),
                          ],
                        ),
                        SizedBox(height: 16.h),

                        // Color / shade swatches — no auto-selection
                        if (_colorVariants.isNotEmpty) ...[
                          Text(
                            AppStrings.selectShade,
                            style: AppTypography.titleSmall
                                .copyWith(fontWeight: FontWeight.w700),
                          ),
                          SizedBox(height: 10.h),
                          Wrap(
                            spacing: 10.w,
                            runSpacing: 10.h,
                            children: _colorVariants.map((variant) {
                              final isSelected =
                                  _selectedVariant?.id == variant.id;
                              final label = variant.attributes
                                  .where(_isColorAttribute)
                                  .map((a) => a.displayValue)
                                  .join(' ');
                              final variantThumbs = variant.images
                                  .map((image) => image.optimizedUrl)
                                  .where(_isUsableImageUrl)
                                  .toList();
                              final thumb = variantThumbs.isNotEmpty
                                  ? variantThumbs.first
                                  : _mainImageThumb;

                              return GestureDetector(
                                onTap: () => _onSelectVariant(variant),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Container(
                                      width: 44.w,
                                      height: 44.w,
                                      padding: EdgeInsets.all(isSelected ? 2.w : 0),
                                      decoration: BoxDecoration(
                                        shape: BoxShape.circle,
                                        border: Border.all(
                                          color: isSelected
                                              ? AppColors.primaryDark
                                              : AppColors.border,
                                          width: isSelected ? 2 : 1,
                                        ),
                                      ),
                                      child: ClipOval(
                                        child: thumb.isNotEmpty
                                            ? CachedImageWidget(
                                                imageUrl: thumb,
                                                fit: BoxFit.cover,
                                              )
                                            : Container(
                                                color: AppColors.surfaceSoft,
                                                alignment: Alignment.center,
                                                child: Icon(
                                                  Icons.palette_outlined,
                                                  size: 18.w,
                                                  color: AppColors.textMuted,
                                                ),
                                              ),
                                      ),
                                    ),
                                    SizedBox(height: 4.h),
                                    SizedBox(
                                      width: 56.w,
                                      child: Text(
                                        label.isNotEmpty ? label : variant.sku,
                                        textAlign: TextAlign.center,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          fontSize: 9.sp,
                                          fontWeight: isSelected
                                              ? FontWeight.w700
                                              : FontWeight.w500,
                                          color: isSelected
                                              ? AppColors.primaryDark
                                              : AppColors.textMuted,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                          SizedBox(height: 16.h),
                        ],

                        // Other variant options (non-color) without auto-select
                        if (_detail != null &&
                            _nonColorVariants.isNotEmpty &&
                            (_colorVariants.isEmpty
                                ? _nonColorVariants.length > 1
                                : true)) ...[
                          Text(
                            'الخيارات المتاحة (${_nonColorVariants.length})',
                            style: AppTypography.titleSmall
                                .copyWith(fontWeight: FontWeight.w700),
                          ),
                          SizedBox(height: 8.h),
                          Wrap(
                            spacing: 8.w,
                            runSpacing: 8.h,
                            children: _nonColorVariants.map((variant) {
                              final isSelected =
                                  _selectedVariant?.id == variant.id;
                              final attrLabel = variant.attributes.isNotEmpty
                                  ? variant.attributes
                                      .map((a) => a.displayValue)
                                      .join(' / ')
                                  : variant.sku;

                              return ChoiceChip(
                                label: Text(attrLabel),
                                selected: isSelected,
                                onSelected: (_) => _onSelectVariant(variant),
                                selectedColor: AppColors.primaryDark,
                                labelStyle: TextStyle(
                                  color: isSelected
                                      ? Colors.white
                                      : AppColors.textPrimary,
                                  fontSize: 11.sp,
                                  fontWeight: isSelected
                                      ? FontWeight.w700
                                      : FontWeight.w500,
                                ),
                                backgroundColor: AppColors.surfaceSoft,
                                side: BorderSide(
                                  color: isSelected
                                      ? AppColors.primaryDark
                                      : AppColors.border,
                                ),
                              );
                            }).toList(),
                          ),
                          SizedBox(height: 16.h),
                        ],

                        // Fallback Size Selector
                        if ((_detail == null || _detail!.variants.isEmpty) &&
                            _effectiveProduct.volumeOrSize != null &&
                            _effectiveProduct.volumeOrSize!.isNotEmpty) ...[
                          Text(
                            AppStrings.selectSize,
                            style: AppTypography.titleSmall
                                .copyWith(fontWeight: FontWeight.w700),
                          ),
                          SizedBox(height: 8.h),
                          Wrap(
                            spacing: 8.w,
                            children: _effectiveProduct.volumeOrSize!.map((size) {
                              final isSelected = _selectedSize == size;
                              return ChoiceChip(
                                label: Text(size),
                                selected: isSelected,
                                onSelected: (_) => setState(() {
                                  _selectedSize =
                                      isSelected ? null : size;
                                }),
                                selectedColor: AppColors.primaryDark,
                                labelStyle: TextStyle(
                                  color: isSelected
                                      ? Colors.white
                                      : AppColors.textPrimary,
                                  fontSize: 11.sp,
                                  fontWeight: isSelected
                                      ? FontWeight.w700
                                      : FontWeight.w500,
                                ),
                                backgroundColor: AppColors.surfaceSoft,
                                side: BorderSide(
                                  color: isSelected
                                      ? AppColors.primaryDark
                                      : AppColors.border,
                                ),
                              );
                            }).toList(),
                          ),
                          SizedBox(height: 16.h),
                        ],

                        // Fallback shade chips (local/mock data)
                        if ((_detail == null || _detail!.variants.isEmpty) &&
                            _effectiveProduct.shades != null &&
                            _effectiveProduct.shades!.isNotEmpty) ...[
                          Text(
                            AppStrings.selectShade,
                            style: AppTypography.titleSmall
                                .copyWith(fontWeight: FontWeight.w700),
                          ),
                          SizedBox(height: 8.h),
                          Wrap(
                            spacing: 10.w,
                            runSpacing: 8.h,
                            children: _effectiveProduct.shades!.map((shade) {
                              final isSelected =
                                  _selectedShade?.name == shade.name;
                              Color swatchColor;
                              try {
                                swatchColor = Color(int.parse(
                                    shade.hex.replaceFirst('#', '0xFF')));
                              } catch (_) {
                                swatchColor = AppColors.gold;
                              }
                              return GestureDetector(
                                onTap: () => setState(() {
                                  _selectedShade =
                                      isSelected ? null : shade;
                                }),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Container(
                                      width: 36.w,
                                      height: 36.w,
                                      decoration: BoxDecoration(
                                        color: swatchColor,
                                        shape: BoxShape.circle,
                                        border: Border.all(
                                          color: isSelected
                                              ? AppColors.primaryDark
                                              : AppColors.border,
                                          width: isSelected ? 2.5 : 1,
                                        ),
                                      ),
                                    ),
                                    SizedBox(height: 4.h),
                                    Text(
                                      shade.name,
                                      style: TextStyle(
                                        fontSize: 9.sp,
                                        fontWeight: isSelected
                                            ? FontWeight.w700
                                            : FontWeight.w500,
                                        color: isSelected
                                            ? AppColors.primaryDark
                                            : AppColors.textMuted,
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                          SizedBox(height: 16.h),
                        ],

                        // Description (تفاصيل ووصف المنتج - يظهر فقط عند وجود وصف)
                        Builder(
                          builder: (_) {
                            final desc = _detail?.displayDescription.trim().isNotEmpty == true
                                ? _detail!.displayDescription.trim()
                                : _effectiveProduct.description.trim();
                            if (desc.isEmpty) return const SizedBox.shrink();

                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  AppStrings.productDescription,
                                  style: AppTypography.titleSmall
                                      .copyWith(fontWeight: FontWeight.w700),
                                ),
                                SizedBox(height: 6.h),
                                Text(
                                  desc,
                                  style: TextStyle(
                                    fontSize: 12.sp,
                                    color: AppColors.textMuted,
                                    height: 1.6,
                                  ),
                                ),
                                SizedBox(height: 16.h),
                              ],
                            );
                          },
                        ),

                        // Specifications (الخصائص)
                        _buildSpecificationsAndFormulaSection(),

                        // Customer Reviews & Comments
                        ProductReviewsHost(
                          key: ValueKey(
                              'reviews_${_effectiveProduct.id}_$_refreshCounter'),
                          productId: _effectiveProduct.serverId ??
                              int.tryParse(_effectiveProduct.id) ??
                              _effectiveProduct.id,
                        ),
                        SizedBox(height: 16.h),
                      ],
                    ),
                  ),

                  // Related Products Section (Sprint 42)
                  RelatedProductsSection(
                    onRetry: () {
                      final relatedTargetId = _effectiveProduct.serverId ??
                          int.tryParse(_effectiveProduct.id) ??
                          _effectiveProduct.id;
                      _relatedProductsCubit.loadRelatedProducts(relatedTargetId,
                          force: true);
                    },
                  ),
                ],
              ),
            ),
          ),

            // Bottom Bar (Quantity & Add to Cart)
            Container(
              padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: AppColors.border)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 10,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                top: false,
                child: Row(
                  children: [
                    // Quantity
                    Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(14.r),
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.remove_rounded, size: 18),
                            onPressed: _quantity > 1
                                ? () => setState(() => _quantity--)
                                : null,
                            padding: EdgeInsets.symmetric(horizontal: 8.w),
                            constraints: const BoxConstraints(),
                          ),
                          Text(
                            '$_quantity',
                            style: TextStyle(
                                fontSize: 14.sp, fontWeight: FontWeight.bold),
                          ),
                          IconButton(
                            icon: const Icon(Icons.add_rounded, size: 18),
                            onPressed: () => setState(() => _quantity++),
                            padding: EdgeInsets.symmetric(horizontal: 8.w),
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(width: 12.w),

                    // Add to Cart Button
                    Expanded(
                      child: LuxuryButton(
                        text: isAvailable
                            ? AppStrings.addToCart
                            : 'نفد من المخزون',
                        icon: isAvailable
                            ? null
                            : Icon(
                                Icons.block_rounded,
                                size: 18.w,
                                color: Colors.white,
                              ),
                        onPressed: isAvailable
                            ? () async {
                                if (!context.mounted) return;

                                final variantsCount =
                                    _detail?.variants.length ?? 0;
                                final resolvedVariantId = _resolvedVariantId;
                                if (variantsCount > 1 &&
                                    _selectedVariant == null) {
                                  context.showAppSnackBar(
                                    _colorVariants.isNotEmpty
                                        ? 'يرجى اختيار اللون أولاً'
                                        : 'يرجى اختيار الخيار أولاً',
                                    type: AppSnackBarType.info,
                                  );
                                  return;
                                }
                                if (resolvedVariantId == null) {
                                  context.showAppSnackBar(
                                    'يرجى اختيار الحجم أو اللون أولاً',
                                    type: AppSnackBarType.info,
                                  );
                                  return;
                                }

                                final resolvedProduct = _effectiveProduct.copyWith(
                                  name: _detail?.displayName.trim().isNotEmpty == true
                                      ? _detail!.displayName
                                      : _effectiveProduct.name,
                                  brand: _detail?.brand?.displayName ?? _effectiveProduct.brand,
                                  image: _displayGalleryImages.isNotEmpty
                                      ? _displayGalleryImages.first
                                      : _effectiveProduct.image,
                                  images: _displayGalleryImages.isNotEmpty
                                      ? _displayGalleryImages
                                      : _effectiveProduct.images,
                                  selectedVariantId: resolvedVariantId,
                                  selectedVariantSku: _resolvedVariantSku,
                                  priceSAR: currentPrice,
                                  inStock: isAvailable,
                                );
                                final success =
                                    await context.read<CartCubit>().addToCart(
                                          resolvedProduct,
                                          quantity: _quantity,
                                          selectedSize: _selectedSize,
                                          selectedShade: _selectedShade,
                                          variantId: resolvedVariantId,
                                        );
                                if (!context.mounted) return;
                                if (success) {
                                  context.showAppSnackBar(
                                    'تمت إضافة ${_detail?.displayName ?? _effectiveProduct.name} إلى حقيبة التسوق',
                                    type: AppSnackBarType.success,
                                  );
                                } else {
                                  final error = context
                                          .read<CartCubit>()
                                          .state
                                          .errorMessage ??
                                      'تعذر إضافة المنتج إلى السلة.';
                                  context.showAppSnackBar(
                                    error,
                                    type: AppSnackBarType.error,
                                  );
                                }
                              }
                            : null,
                      ),
                    ),
                    SizedBox(width: 8.w),
                    BlocBuilder<CartCubit, CartState>(
                      builder: (context, cartState) {
                        final count = cartState.totalItemsCount;
                        return Material(
                          color: AppColors.surfaceSoft,
                          borderRadius: BorderRadius.circular(14.r),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14.r),
                            onTap: () {
                              context
                                  .read<AppShellCubit>()
                                  .closeProductDetails();
                              context.read<AppShellCubit>().setTab(3);
                            },
                            child: SizedBox(
                              width: 48.w,
                              height: 48.h,
                              child: Center(
                                child: Badge(
                                  isLabelVisible: count > 0,
                                  backgroundColor: AppColors.gold,
                                  label: Text(
                                    count > 99 ? '99+' : '$count',
                                    style: TextStyle(
                                      fontSize: 9.sp,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.primaryDark,
                                    ),
                                  ),
                                  child: Icon(
                                    Icons.shopping_bag_outlined,
                                    color: AppColors.primaryDark,
                                    size: 22.w,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSpecificationsAndFormulaSection() {
    final currentAttrs = <ProductVariantAttributeValue>[];
    if (_selectedVariant != null && _selectedVariant!.attributes.isNotEmpty) {
      currentAttrs.addAll(_selectedVariant!.attributes);
    } else if (_detail?.defaultVariant != null &&
        _detail!.defaultVariant!.attributes.isNotEmpty) {
      currentAttrs.addAll(_detail!.defaultVariant!.attributes);
    } else if (_detail != null && _detail!.allAttributes.isNotEmpty) {
      currentAttrs.addAll(_detail!.allAttributes);
    }

    // Deduplicate by name & value
    final uniqueAttrs = <ProductVariantAttributeValue>[];
    final seenKeys = <String>{};
    for (final a in currentAttrs) {
      final key = '${a.displayName.trim()}_${a.displayValue.trim()}';
      if (seenKeys.add(key) && a.displayValue.trim().isNotEmpty) {
        uniqueAttrs.add(a);
      }
    }

    final fulfillmentMode = _selectedVariant?.fulfillmentMode ??
        _detail?.defaultVariant?.fulfillmentMode;

    final brandName = _detail?.brand?.displayName ??
        (_effectiveProduct.brand.isNotEmpty ? _effectiveProduct.brand : null);

    final categoryName = _detail?.category?.displayName;

    final hasNotes = _effectiveProduct.notes != null;
    final hasFeatures = _effectiveProduct.features != null &&
        _effectiveProduct.features!.isNotEmpty;

    // Collect specification rows (excluding SKU / رمز التخزين as requested)
    final specRows = <Widget>[];

    for (final attr in uniqueAttrs) {
      specRows.add(_buildSpecRow(
        icon: _getAttributeIcon(attr.displayName),
        label: attr.displayName,
        value: attr.displayValue,
      ));
    }

    if (brandName != null && brandName.isNotEmpty) {
      specRows.add(_buildSpecRow(
        icon: Icons.verified_outlined,
        label: 'العلامة التجارية',
        value: brandName,
      ));
    }

    if (categoryName != null && categoryName.isNotEmpty) {
      specRows.add(_buildSpecRow(
        icon: Icons.category_outlined,
        label: 'التصنيف',
        value: categoryName,
      ));
    }

    if (fulfillmentMode != null && fulfillmentMode.isNotEmpty) {
      specRows.add(_buildSpecRow(
        icon: fulfillmentMode == 'stocked'
            ? Icons.archive_outlined
            : Icons.local_shipping_outlined,
        label: 'طريقة التوفير',
        value: fulfillmentMode == 'stocked'
            ? 'متوفر في المخزن (جاهز للشحن)'
            : 'حسب الطلب من المورد',
        valueColor: fulfillmentMode == 'stocked'
            ? AppColors.success
            : AppColors.goldDark,
      ));
    }

    if (specRows.isEmpty && !hasNotes && !hasFeatures) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: EdgeInsets.all(6.w),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(8.r),
              ),
              child: Icon(
                Icons.auto_awesome,
                size: 16.w,
                color: AppColors.goldDark,
              ),
            ),
            SizedBox(width: 8.w),
            Text(
              'الخصائص',
              style: AppTypography.titleSmall.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primaryDark,
              ),
            ),
            if (_selectedVariant != null) ...[
              const Spacer(),
              Container(
                padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12.r),
                  border: Border.all(
                    color: AppColors.goldDark.withValues(alpha: 0.3),
                    width: 0.6,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.check_circle_rounded,
                      size: 11.w,
                      color: AppColors.goldDark,
                    ),
                    SizedBox(width: 4.w),
                    Text(
                      'الخيار المحدد',
                      style: TextStyle(
                        fontSize: 10.sp,
                        fontWeight: FontWeight.w700,
                        color: AppColors.goldDark,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
        SizedBox(height: 10.h),

        // Specifications card
        if (specRows.isNotEmpty)
          Container(
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(16.r),
              border: Border.all(color: AppColors.border, width: 0.8),
            ),
            child: Column(
              children: [
                for (int i = 0; i < specRows.length; i++) ...[
                  specRows[i],
                  if (i < specRows.length - 1)
                    Divider(
                      height: 1,
                      thickness: 0.6,
                      color: AppColors.border.withValues(alpha: 0.7),
                    ),
                ],
              ],
            ),
          ),

        // Fragrance Notes (if present)
        if (hasNotes) ...[
          SizedBox(height: 12.h),
          FragranceNotesView(notes: _effectiveProduct.notes!),
        ],

        // Product Features (if present)
        if (hasFeatures) ...[
          SizedBox(height: 12.h),
          Container(
            padding: EdgeInsets.all(12.w),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(14.r),
              border: Border.all(color: AppColors.border, width: 0.8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'أبرز المميزات',
                  style: TextStyle(
                    fontSize: 12.sp,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                ),
                SizedBox(height: 8.h),
                ..._effectiveProduct.features!.map(
                  (f) => Padding(
                    padding: EdgeInsets.symmetric(vertical: 3.h),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.check_circle_rounded,
                            size: 14.w, color: AppColors.success),
                        SizedBox(width: 8.w),
                        Expanded(
                          child: Text(
                            f,
                            style: TextStyle(
                              fontSize: 11.5.sp,
                              color: AppColors.textPrimary,
                              height: 1.35,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],

        SizedBox(height: 16.h),
      ],
    );
  }

  Widget _buildSpecRow({
    required IconData icon,
    required String label,
    required String value,
    Color? iconColor,
    Color? valueColor,
  }) {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: EdgeInsets.all(5.w),
            decoration: BoxDecoration(
              color: (iconColor ?? AppColors.goldDark).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(6.r),
            ),
            child: Icon(
              icon,
              size: 14.w,
              color: iconColor ?? AppColors.goldDark,
            ),
          ),
          SizedBox(width: 10.w),
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.sp,
                fontWeight: FontWeight.w600,
                color: AppColors.textMuted,
              ),
            ),
          ),
          SizedBox(width: 8.w),
          Expanded(
            flex: 5,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                fontSize: 12.sp,
                fontWeight: FontWeight.w700,
                color: valueColor ?? AppColors.primaryDark,
              ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _getAttributeIcon(String name) {
    final lower = name.toLowerCase();
    if (lower.contains('حجم') ||
        lower.contains('سعة') ||
        lower.contains('size') ||
        lower.contains('volume')) {
      return Icons.straighten_rounded;
    }
    if (lower.contains('بشرة') ||
        lower.contains('skin') ||
        lower.contains('نوع')) {
      return Icons.spa_outlined;
    }
    if (lower.contains('تركيب') ||
        lower.contains('تركيبة') ||
        lower.contains('formula') ||
        lower.contains('مكون')) {
      return Icons.science_outlined;
    }
    if (lower.contains('لون') ||
        lower.contains('color') ||
        lower.contains('shade')) {
      return Icons.palette_outlined;
    }
    if (lower.contains('صنع') ||
        lower.contains('منشأ') ||
        lower.contains('بلد') ||
        lower.contains('origin')) {
      return Icons.public_rounded;
    }
    if (lower.contains('عطر') ||
        lower.contains('رائحة') ||
        lower.contains('fragrance')) {
      return Icons.air_rounded;
    }
    return Icons.tune_rounded;
  }
}

