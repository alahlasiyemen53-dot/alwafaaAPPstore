import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';

class ActiveFiltersBar extends StatelessWidget {
  const ActiveFiltersBar({super.key});

  static String? badgeLabel(String? badge) {
    switch (badge) {
      case 'best_seller':
        return AppStrings.badgeBestSeller;
      case 'new':
        return AppStrings.badgeNew;
      case 'exclusive':
        return AppStrings.badgeExclusive;
      default:
        return null;
    }
  }

  static String sortLabel(CatalogSortOption sort) {
    switch (sort) {
      case CatalogSortOption.priceAsc:
        return AppStrings.sortPriceAsc;
      case CatalogSortOption.priceDesc:
        return AppStrings.sortPriceDesc;
      case CatalogSortOption.rating:
        return AppStrings.sortBestRating;
      case CatalogSortOption.newest:
        return AppStrings.sortNewest;
      case CatalogSortOption.featured:
        return AppStrings.sortFeatured;
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<CatalogCubit, CatalogState>(
      builder: (context, state) {
        final cubit = context.read<CatalogCubit>();
        final chips = <Widget>[];

        if (state.selectedServerCategoryIds.isNotEmpty) {
          for (final categoryId in state.selectedServerCategoryIds) {
            final name = state.selectedCategoryNames[categoryId]?.trim();
            var label = (name != null && name.isNotEmpty) ? name : null;
            if (label == null) {
              for (final cat in state.categories) {
                if (cat.serverId == categoryId) {
                  label = cat.nameAr;
                  break;
                }
              }
            }
            chips.add(
              ActiveFilterChip(
                label: label ?? AppStrings.categoriesFilter,
                icon: Icons.category_outlined,
                onDeleted: () => cubit.removeCategory(categoryId),
              ),
            );
          }
        } else if (state.selectedCategory != ProductCategory.all) {
          String? categoryLabel;
          for (final cat in state.categories) {
            if (cat.id == state.selectedCategory) {
              categoryLabel = cat.nameAr;
              break;
            }
          }
          if (categoryLabel != null && categoryLabel.isNotEmpty) {
            chips.add(
              ActiveFilterChip(
                label: categoryLabel,
                icon: Icons.category_outlined,
                onDeleted: () => cubit.selectCategory(ProductCategory.all),
              ),
            );
          }
        }

        for (final brandId in state.selectedBrandIds) {
          final name = state.selectedBrandNames[brandId]?.trim();
          final label = (name != null && name.isNotEmpty)
              ? name
              : AppStrings.brandsFilter;
          chips.add(
            ActiveFilterChip(
              label: label,
              icon: Icons.storefront_outlined,
              onDeleted: () => cubit.removeBrand(brandId),
            ),
          );
        }

        if (state.onlyOffers) {
          chips.add(
            ActiveFilterChip(
              label: AppStrings.offersOnly,
              icon: Icons.local_offer_outlined,
              onDeleted: () => cubit.toggleOnlyOffers(false),
            ),
          );
        }

        final badge = badgeLabel(state.selectedBadge);
        if (badge != null) {
          chips.add(
            ActiveFilterChip(
              label: badge,
              icon: Icons.verified_outlined,
              onDeleted: () => cubit.setBadge(null),
            ),
          );
        }

        if (state.sortBy != CatalogSortOption.featured) {
          chips.add(
            ActiveFilterChip(
              label: sortLabel(state.sortBy),
              icon: Icons.sort_rounded,
              onDeleted: () => cubit.setSortBy(CatalogSortOption.featured),
            ),
          );
        }

        if (chips.isEmpty) return const SizedBox.shrink();

        return Padding(
          padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 0),
          child: Wrap(
            spacing: 8.w,
            runSpacing: 6.h,
            children: [
              ...chips,
              if (chips.length > 1)
                ActionChip(
                  label: Text(
                    AppStrings.clearAllFilters,
                    style: TextStyle(
                      fontSize: 11.sp,
                      fontWeight: FontWeight.w700,
                      color: AppColors.error,
                    ),
                  ),
                  onPressed: () => cubit.resetFilters(),
                  backgroundColor: AppColors.error.withValues(alpha: 0.08),
                  side: BorderSide(
                    color: AppColors.error.withValues(alpha: 0.25),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12.r),
                  ),
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
            ],
          ),
        );
      },
    );
  }
}

class ActiveFilterChip extends StatelessWidget {
  const ActiveFilterChip({
    super.key,
    required this.label,
    required this.icon,
    required this.onDeleted,
  });

  final String label;
  final IconData icon;
  final VoidCallback onDeleted;

  @override
  Widget build(BuildContext context) {
    return InputChip(
      label: Text(
        label,
        style: TextStyle(
          fontSize: 11.sp,
          fontWeight: FontWeight.w600,
          color: AppColors.primaryDark,
        ),
      ),
      avatar: Icon(icon, size: 14.w, color: AppColors.goldDark),
      deleteIcon: Icon(
        Icons.close_rounded,
        size: 14.w,
        color: AppColors.textMuted,
      ),
      onDeleted: onDeleted,
      backgroundColor: AppColors.gold.withValues(alpha: 0.12),
      side: BorderSide(color: AppColors.gold.withValues(alpha: 0.35)),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12.r),
      ),
      visualDensity: VisualDensity.compact,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
  }
}
