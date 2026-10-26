import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/data/models/catalog/home_api_model.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import 'package:store/features/home/presentation/widgets/home_product_rail.dart';
import 'package:store/features/home/presentation/widgets/home_section_header.dart';

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
        HomeSectionHeader(
          title: title,
          onViewAll: () => CollectionDetailScreen.open(
            context,
            collection.id,
            title: collection.displayName,
          ),
        ),
        if (collection.coverUrl != null && collection.coverUrl!.isNotEmpty) ...[
          SizedBox(height: 12.h),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: GestureDetector(
              onTap: () => CollectionDetailScreen.open(
                context,
                collection.id,
                title: collection.displayName,
              ),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(18.r),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primaryDark.withValues(alpha: 0.08),
                      blurRadius: 14,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(18.r),
                  child: AspectRatio(
                    aspectRatio: 16 / 7,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        CachedImageWidget(
                          imageUrl: collection.coverUrl!,
                          fit: BoxFit.cover,
                        ),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                              colors: [
                                AppColors.primaryDark.withValues(alpha: 0.45),
                                Colors.transparent,
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
        if (products.isNotEmpty) ...[
          SizedBox(height: 12.h),
          HomeProductRail(
            title: title,
            showHeader: false,
            products: products,
            onProductTap: (product) =>
                context.read<AppShellCubit>().openProductDetails(product),
          ),
        ],
        SizedBox(height: 20.h),
      ],
    );
  }
}
