import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/cached_image_widget.dart';
import '../../../../data/models/catalog/home_api_model.dart';
import '../../../../data/models/product_model.dart';
import '../../../../features/app_shell/cubit/app_shell_cubit.dart';
import '../../../../features/catalog/cubit/catalog_cubit.dart';
import '../../../../features/catalog/presentation/widgets/product_card.dart';
import '../../../../features/discovery/presentation/screens/collection_detail_screen.dart';

class HomeCollectionSection extends StatelessWidget {
  final HomeSectionModel section;

  const HomeCollectionSection({super.key, required this.section});

  @override
  Widget build(BuildContext context) {
    final collection = section.collectionSummary;
    final products = section.collectionProducts
        .map(ProductModel.fromApiListItem)
        .toList();
    if (collection == null || collection.id <= 0) {
      return const SizedBox.shrink();
    }

    final title = section.displayTitle.isNotEmpty
        ? section.displayTitle
        : collection.displayName;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 16.w),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: AppTypography.titleMedium.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              GestureDetector(
                onTap: () => CollectionDetailScreen.open(
                  context,
                  collection.id,
                  title: collection.displayName,
                ),
                child: Row(
                  children: [
                    Text(
                      'عرض الكل',
                      style: TextStyle(
                        fontSize: 11.sp,
                        fontWeight: FontWeight.w600,
                        color: AppColors.goldDark,
                      ),
                    ),
                    Icon(Icons.chevron_right_rounded,
                        size: 16.w, color: AppColors.goldDark),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (collection.coverUrl != null && collection.coverUrl!.isNotEmpty) ...[
          SizedBox(height: 10.h),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: GestureDetector(
              onTap: () => CollectionDetailScreen.open(
                context,
                collection.id,
                title: collection.displayName,
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18.r),
                child: AspectRatio(
                  aspectRatio: 16 / 7,
                  child: CachedImageWidget(
                    imageUrl: collection.coverUrl!,
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            ),
          ),
        ],
        if (products.isNotEmpty) ...[
          SizedBox(height: 12.h),
          SizedBox(
            height: 285.h,
            child: ListView.separated(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              scrollDirection: Axis.horizontal,
              itemCount: products.length,
              separatorBuilder: (_, __) => SizedBox(width: 14.w),
              itemBuilder: (context, index) {
                final product = products[index];
                return SizedBox(
                  width: 170.w,
                  child: ProductCard(
                    product: product,
                    variation: CardVariation.classic,
                    onTap: () => context
                        .read<AppShellCubit>()
                        .openProductDetails(product),
                  ),
                );
              },
            ),
          ),
        ],
        SizedBox(height: 20.h),
      ],
    );
  }
}
