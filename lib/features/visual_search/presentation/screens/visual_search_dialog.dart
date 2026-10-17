import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/product_card.dart';
import 'package:store/features/visual_search/cubit/visual_search_cubit.dart';

class VisualSearchDialog extends StatelessWidget {
  const VisualSearchDialog({super.key});

  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider(
        create: (_) => VisualSearchCubit(),
        child: const VisualSearchDialog(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: .85.sh,
      padding: EdgeInsets.fromLTRB(
        20.w,
        20.w,
        20.w,
        20.w + MediaQuery.paddingOf(context).bottom,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
      ),
      child: BlocBuilder<VisualSearchCubit, VisualSearchState>(
        builder: (context, state) {
          final cubit = context.read<VisualSearchCubit>();
          return SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44.w,
                    height: 4.h,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(4.r),
                    ),
                  ),
                ),
                SizedBox(height: 14.h),
                Row(
                  children: [
                    Icon(Icons.camera_alt_rounded,
                        color: AppColors.gold, size: 22.w),
                    SizedBox(width: 8.w),
                    Expanded(
                      child: Text(
                        'البحث الذكي بالصورة',
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: Icon(Icons.close_rounded, size: 20.w),
                    ),
                  ],
                ),
                SizedBox(height: 12.h),
                _ImagePreview(state: state),
                SizedBox(height: 16.h),
                _SourceButton(
                  icon: Icons.photo_camera_outlined,
                  label: 'التقاط صورة',
                  onPressed: state.isScanning ? null : cubit.pickFromCamera,
                ),
                SizedBox(height: 10.h),
                _SourceButton(
                  icon: Icons.photo_library_outlined,
                  label: 'اختيار من الاستديو',
                  onPressed: state.isScanning ? null : cubit.pickFromGallery,
                ),
                if (state.hasSelectedImage) ...[
                  SizedBox(height: 12.h),
                  LuxuryButton(
                    text: state.isScanning
                        ? 'جاري البحث...'
                        : 'البحث بهذه الصورة',
                    onPressed:
                        state.isScanning ? null : cubit.searchSelectedImage,
                  ),
                ],
                if (state.errorMessage != null) ...[
                  SizedBox(height: 16.h),
                  _ErrorMessage(
                    message: state.errorMessage!,
                    onRetry: state.hasSelectedImage && !state.isScanning
                        ? cubit.searchSelectedImage
                        : null,
                  ),
                ],
                if (state.isScanning) ...[
                  SizedBox(height: 20.h),
                  Text(
                    'جاري تحليل الصورة...',
                    style: AppTypography.titleSmall.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                  SizedBox(height: 10.h),
                  const ProductGridSkeleton(itemCount: 4, shrinkWrap: true),
                ],
                if (state.hasSearched &&
                    !state.isScanning &&
                    state.errorMessage == null &&
                    state.matchedProducts.isEmpty) ...[
                  SizedBox(height: 16.h),
                  const _EmptyResults(),
                ],
                if (state.matchedProducts.isNotEmpty) ...[
                  SizedBox(height: 20.h),
                  Text(
                    'منتجات مشابهة',
                    style: AppTypography.titleSmall.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                  SizedBox(height: 10.h),
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      childAspectRatio: .52,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                    itemCount: state.matchedProducts.length,
                    itemBuilder: (context, index) {
                      final product = state.matchedProducts[index];
                      return ProductCard(
                        product: product,
                        variation: CardVariation.classic,
                        onTap: () {
                          Navigator.pop(context);
                          context
                              .read<AppShellCubit>()
                              .openProductDetails(product);
                        },
                      );
                    },
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SourceButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  const _SourceButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 20.w),
        label: Text(
          label,
          maxLines: 1,
          softWrap: false,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 13.sp,
            fontWeight: FontWeight.w600,
          ),
        ),
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primaryDark,
          side: const BorderSide(color: AppColors.border, width: 1.2),
          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14.r),
          ),
        ),
      ),
    );
  }
}

class _ImagePreview extends StatelessWidget {
  final VisualSearchState state;

  const _ImagePreview({required this.state});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 200.h,
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.primaryDark,
        borderRadius: BorderRadius.circular(24.r),
        border: Border.all(color: AppColors.primaryLight, width: 1.2),
      ),
      child: state.selectedImageBytes == null
          ? Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.image_search_rounded,
                    color: AppColors.gold, size: 48.w),
                SizedBox(height: 10.h),
                Text('التقطي صورة أو اختاريها من الاستديو',
                    style: TextStyle(
                        fontSize: 11.5.sp,
                        color: AppColors.textLight.withValues(alpha: 0.75))),
              ],
            )
          : Stack(
              fit: StackFit.expand,
              children: [
                Image.memory(state.selectedImageBytes!, fit: BoxFit.cover),
                if (state.isScanning)
                  const ColoredBox(color: Color(0x66000000)),
                if (state.isScanning)
                  const Center(child: CircularProgressIndicator()),
              ],
            ),
    );
  }
}

class _ErrorMessage extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const _ErrorMessage({required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, color: AppColors.error),
          const SizedBox(width: 8),
          Expanded(child: Text(message)),
          if (onRetry != null)
            TextButton(onPressed: onRetry, child: const Text('إعادة المحاولة')),
        ],
      ),
    );
  }
}

class _EmptyResults extends StatelessWidget {
  const _EmptyResults();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: const Text('لم نجد منتجات مشابهة لهذه الصورة.',
          textAlign: TextAlign.center),
    );
  }
}
