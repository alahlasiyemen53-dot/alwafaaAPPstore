import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/product_card.dart';
import 'package:store/features/home/presentation/widgets/home_section_header.dart';

/// Horizontal product rail with shared home section chrome.
class HomeProductRail extends StatelessWidget {
  final String title;
  final List<ProductModel> products;
  final VoidCallback? onViewAll;
  final ValueChanged<ProductModel> onProductTap;
  final bool showHeader;

  const HomeProductRail({
    super.key,
    required this.title,
    required this.products,
    required this.onProductTap,
    this.onViewAll,
    this.showHeader = true,
  });

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (showHeader && title.trim().isNotEmpty) ...[
          HomeSectionHeader(title: title, onViewAll: onViewAll),
          SizedBox(height: 12.h),
        ],
        SizedBox(
          height: 285.h,
          child: ListView.separated(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            scrollDirection: Axis.horizontal,
            itemCount: products.length,
            separatorBuilder: (_, _) => SizedBox(width: 12.w),
            itemBuilder: (context, index) {
              final product = products[index];
              return SizedBox(
                width: 168.w,
                child: ProductCard(
                  product: product,
                  variation: CardVariation.classic,
                  onTap: () => onProductTap(product),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
