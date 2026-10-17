import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/skeletons/screen_skeletons.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/features/brands/cubit/brands_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/filters/filter_option_list.dart';

class FilterDrawerSheet extends StatelessWidget {
  const FilterDrawerSheet({super.key});

  static void show(BuildContext context) {
    final brandsCubit = context.read<BrandsCubit>();
    if (brandsCubit.state.brands.isEmpty) {
      brandsCubit.loadBrands();
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => MultiBlocProvider(
        providers: [
          BlocProvider.value(value: context.read<CatalogCubit>()),
          BlocProvider.value(value: brandsCubit),
        ],
        child: const FilterDrawerSheet(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomSafeArea = MediaQuery.paddingOf(context).bottom;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.9,
      ),
      padding: EdgeInsets.only(
        left: 20.w,
        right: 20.w,
        top: 16.h,
        bottom: 28.h + bottomSafeArea,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
      ),
      child: BlocBuilder<CatalogCubit, CatalogState>(
        builder: (context, state) {
          final cubit = context.read<CatalogCubit>();
          final hideBadgeWhileSearching =
              state.isSearching && state.searchQuery.trim().isNotEmpty;

          return SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
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
                SizedBox(height: 16.h),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        AppStrings.filterAndSort,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.titleLarge.copyWith(
                          color: AppColors.primaryDark,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    TextButton(
                      onPressed: () => cubit.resetFilters(),
                      child: Text(
                        AppStrings.resetFilters,
                        style: TextStyle(
                          fontSize: 12.sp,
                          color: AppColors.goldDark,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 16.h),

                Text(
                  AppStrings.categoriesFilter,
                  style: AppTypography.titleSmall
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 8.h),
                Wrap(
                  spacing: 8.w,
                  runSpacing: 8.h,
                  children: [
                    FilterSelectChip(
                      label: AppStrings.allOption,
                      isSelected: state.selectedServerCategoryIds.isEmpty &&
                          state.selectedCategory == ProductCategory.all,
                      onTap: () => cubit.setSelectedCategories(const {}),
                    ),
                    ...state.categoryFilterChips.map((cat) {
                      final serverId = cat.serverId;
                      final isSelected = serverId != null
                          ? state.selectedServerCategoryIds.contains(serverId)
                          : (state.selectedCategory == cat.id &&
                              state.selectedServerCategoryIds.isEmpty);
                      return FilterSelectChip(
                        label: cat.nameAr,
                        isSelected: isSelected,
                        onTap: () {
                          if (serverId == null) {
                            cubit.selectCategory(cat.id);
                            return;
                          }
                          final next = Map<int, String>.from(
                            state.selectedCategoryNames,
                          );
                          if (isSelected) {
                            next.remove(serverId);
                          } else {
                            next[serverId] = cat.nameAr;
                          }
                          cubit.setSelectedCategories(next);
                        },
                      );
                    }),
                  ],
                ),
                SizedBox(height: 20.h),

                Text(
                  AppStrings.brandsFilter,
                  style: AppTypography.titleSmall
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 8.h),
                BlocBuilder<BrandsCubit, BrandsState>(
                  builder: (context, brandsState) {
                    if (brandsState.status == BrandsStatus.loading &&
                        brandsState.brands.isEmpty) {
                      return const FilterChipsSkeleton();
                    }

                    if (brandsState.brands.isEmpty) {
                      return Text(
                        'لا توجد علامات تجارية حالياً',
                        style: TextStyle(
                          fontSize: 11.sp,
                          color: AppColors.textMuted,
                        ),
                      );
                    }

                    return Wrap(
                      spacing: 8.w,
                      runSpacing: 8.h,
                      children: brandsState.brands.map((brand) {
                        final isSelected =
                            state.selectedBrandIds.contains(brand.id);
                        return FilterSelectChip(
                          label: brand.displayName,
                          isSelected: isSelected,
                          onTap: () {
                            final next =
                                Map<int, String>.from(state.selectedBrandNames);
                            if (isSelected) {
                              next.remove(brand.id);
                            } else {
                              next[brand.id] = brand.displayName;
                            }
                            cubit.setSelectedBrands(next);
                          },
                        );
                      }).toList(),
                    );
                  },
                ),
                SizedBox(height: 20.h),

                Text(
                  AppStrings.sortFilter,
                  style: AppTypography.titleSmall
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 4.h),
                ...[
                  (CatalogSortOption.featured, AppStrings.sortFeatured),
                  (CatalogSortOption.priceAsc, AppStrings.sortPriceAsc),
                  (CatalogSortOption.priceDesc, AppStrings.sortPriceDesc),
                  (CatalogSortOption.rating, AppStrings.sortBestRating),
                  (CatalogSortOption.newest, AppStrings.sortNewest),
                ].map(
                  (entry) => _SortRow(
                    label: entry.$2,
                    selected: state.sortBy == entry.$1,
                    onTap: () => cubit.setSortBy(entry.$1),
                  ),
                ),
                SizedBox(height: 8.h),

                FilterToggleTile(
                  title: AppStrings.offersOnly,
                  value: state.onlyOffers,
                  onChanged: cubit.toggleOnlyOffers,
                ),

                if (!hideBadgeWhileSearching) ...[
                  SizedBox(height: 8.h),
                  Text(
                    AppStrings.badgeFilter,
                    style: AppTypography.titleSmall
                        .copyWith(fontWeight: FontWeight.w700),
                  ),
                  SizedBox(height: 8.h),
                  Wrap(
                    spacing: 8.w,
                    runSpacing: 8.h,
                    children: [
                      FilterSelectChip(
                        label: AppStrings.allOption,
                        isSelected: state.selectedBadge == null,
                        onTap: () => cubit.setBadge(null),
                      ),
                      FilterSelectChip(
                        label: AppStrings.badgeBestSeller,
                        isSelected: state.selectedBadge == 'best_seller',
                        onTap: () => cubit.setBadge(
                          state.selectedBadge == 'best_seller'
                              ? null
                              : 'best_seller',
                        ),
                      ),
                      FilterSelectChip(
                        label: AppStrings.badgeNew,
                        isSelected: state.selectedBadge == 'new',
                        onTap: () => cubit.setBadge(
                          state.selectedBadge == 'new' ? null : 'new',
                        ),
                      ),
                      FilterSelectChip(
                        label: AppStrings.badgeExclusive,
                        isSelected: state.selectedBadge == 'exclusive',
                        onTap: () => cubit.setBadge(
                          state.selectedBadge == 'exclusive'
                              ? null
                              : 'exclusive',
                        ),
                      ),
                    ],
                  ),
                ],

                SizedBox(height: 16.h),

                LuxuryButton(
                  text: AppStrings.applyFilters,
                  width: double.infinity,
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SortRow extends StatelessWidget {
  const _SortRow({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12.r),
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 8.h),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12.sp,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: selected
                      ? AppColors.primaryDark
                      : AppColors.textSecondary,
                ),
              ),
            ),
            if (selected)
              Icon(
                Icons.check_circle_rounded,
                size: 18.w,
                color: AppColors.primaryDark,
              ),
          ],
        ),
      ),
    );
  }
}
