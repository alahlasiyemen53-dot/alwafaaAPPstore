import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/widgets/skeletons/screen_skeletons.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/features/brands/cubit/brands_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/filters/active_filters_bar.dart';
import 'package:store/features/catalog/presentation/widgets/filters/filter_dropdown_chip.dart';
import 'package:store/features/catalog/presentation/widgets/filters/filter_option_list.dart';

/// Horizontal scrollable filter chips for the catalog screen.
class ProductFilterBar extends StatelessWidget {
  const ProductFilterBar({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<CatalogCubit, CatalogState>(
      builder: (context, state) {
        final cubit = context.read<CatalogCubit>();
        final categoryLabel = _categoryChipLabel(state);
        final brandLabel = _brandChipLabel(state);
        final sortLabel = state.sortBy == CatalogSortOption.featured
            ? AppStrings.sortFilter
            : ActiveFiltersBar.sortLabel(state.sortBy);
        final badgeChipLabel = ActiveFiltersBar.badgeLabel(state.selectedBadge) ??
            AppStrings.badgeFilter;
        final hideBadgeWhileSearching =
            state.isSearching && state.searchQuery.trim().isNotEmpty;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              height: 42.h,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: EdgeInsets.symmetric(horizontal: 16.w),
                children: [
                  FilterDropdownChip(
                    label: categoryLabel,
                    isActive: state.selectedServerCategoryIds.isNotEmpty ||
                        state.selectedCategory != ProductCategory.all,
                    leadingIcon: Icons.category_outlined,
                    onPressed: () => _openCategoryPicker(context, cubit, state),
                  ),
                  SizedBox(width: 8.w),
                  FilterDropdownChip(
                    label: brandLabel,
                    isActive: state.selectedBrandIds.isNotEmpty,
                    leadingIcon: Icons.storefront_outlined,
                    onPressed: () => _openBrandPicker(context, cubit, state),
                  ),
                  SizedBox(width: 8.w),
                  FilterDropdownChip(
                    label: sortLabel,
                    isActive: state.sortBy != CatalogSortOption.featured,
                    leadingIcon: Icons.sort_rounded,
                    onPressed: () => _openSortPicker(context, cubit, state),
                  ),
                  SizedBox(width: 8.w),
                  FilterDropdownChip(
                    label: AppStrings.offersOnly,
                    isActive: state.onlyOffers,
                    leadingIcon: Icons.local_offer_outlined,
                    onPressed: () =>
                        _openOffersPicker(context, cubit, state),
                  ),
                  if (!hideBadgeWhileSearching) ...[
                    SizedBox(width: 8.w),
                    FilterDropdownChip(
                      label: badgeChipLabel,
                      isActive: state.selectedBadge != null,
                      leadingIcon: Icons.verified_outlined,
                      onPressed: () => _openBadgePicker(context, cubit, state),
                    ),
                  ],
                ],
              ),
            ),
            const ActiveFiltersBar(),
          ],
        );
      },
    );
  }

  String _brandChipLabel(CatalogState state) {
    final ids = state.selectedBrandIds;
    if (ids.isEmpty) return AppStrings.brandsFilter;
    if (ids.length == 1) {
      final name = state.selectedBrandNames[ids.first]?.trim();
      if (name != null && name.isNotEmpty) return name;
      return AppStrings.brandsFilter;
    }
    return '${AppStrings.brandsFilter} (${ids.length})';
  }

  String _categoryChipLabel(CatalogState state) {
    final ids = state.selectedServerCategoryIds;
    if (ids.isEmpty) {
      if (state.selectedCategory != ProductCategory.all) {
        for (final cat in state.categories) {
          if (cat.id == state.selectedCategory) return cat.nameAr;
        }
      }
      return AppStrings.categoriesFilter;
    }
    if (ids.length == 1) {
      final name = state.selectedCategoryNames[ids.first]?.trim();
      if (name != null && name.isNotEmpty) return name;
      for (final cat in state.categories) {
        if (cat.serverId == ids.first) return cat.nameAr;
      }
      return AppStrings.categoriesFilter;
    }
    return '${AppStrings.categoriesFilter} (${ids.length})';
  }

  Future<void> _openCategoryPicker(
    BuildContext context,
    CatalogCubit cubit,
    CatalogState state,
  ) {
    final items = state.categoryFilterChips;
    return showFilterPicker(
      context: context,
      title: AppStrings.categoriesFilter,
      builder: (ctx) => FilterMultiOptionList<int>(
        showClearOption: true,
        clearLabel: AppStrings.allOption,
        applyLabel: AppStrings.applyFilters,
        initialSelected: state.selectedServerCategoryIds.toSet(),
        items: items
            .where((c) => c.serverId != null)
            .map(
              (c) => FilterOptionItem(
                value: c.serverId!,
                label: c.nameAr,
              ),
            )
            .toList(),
        onApply: (selectedIds) {
          if (selectedIds.isEmpty) {
            cubit.setSelectedCategories(const {});
            return;
          }
          final names = <int, String>{};
          for (final cat in items) {
            final id = cat.serverId;
            if (id != null && selectedIds.contains(id)) {
              names[id] = cat.nameAr;
            }
          }
          cubit.setSelectedCategories(names);
        },
      ),
    );
  }

  Future<void> _openBrandPicker(
    BuildContext context,
    CatalogCubit cubit,
    CatalogState state,
  ) {
    final brandsCubit = context.read<BrandsCubit>();
    if (brandsCubit.state.brands.isEmpty) {
      brandsCubit.loadBrands();
    }

    return showFilterPicker(
      context: context,
      title: AppStrings.brandsFilter,
      builder: (ctx) => BlocProvider.value(
        value: brandsCubit,
        child: BlocBuilder<BrandsCubit, BrandsState>(
          builder: (context, brandsState) {
            if (brandsState.status == BrandsStatus.loading &&
                brandsState.brands.isEmpty) {
              return const Padding(
                padding: EdgeInsets.all(16),
                child: FilterChipsSkeleton(),
              );
            }
            if (brandsState.brands.isEmpty) {
              return Padding(
                padding: EdgeInsets.all(16.w),
                child: Text(
                  'لا توجد علامات تجارية حالياً',
                  style: TextStyle(fontSize: 12.sp, color: AppColors.textMuted),
                ),
              );
            }
            return FilterMultiOptionList<int>(
              showClearOption: true,
              clearLabel: AppStrings.allOption,
              applyLabel: AppStrings.applyFilters,
              initialSelected: state.selectedBrandIds.toSet(),
              items: brandsState.brands
                  .map(
                    (b) => FilterOptionItem(
                      value: b.id,
                      label: b.displayName,
                    ),
                  )
                  .toList(),
              onApply: (selectedIds) {
                if (selectedIds.isEmpty) {
                  cubit.setSelectedBrands(const {});
                  return;
                }
                final names = <int, String>{};
                for (final brand in brandsState.brands) {
                  if (selectedIds.contains(brand.id)) {
                    names[brand.id] = brand.displayName;
                  }
                }
                cubit.setSelectedBrands(names);
              },
            );
          },
        ),
      ),
    );
  }

  Future<void> _openSortPicker(
    BuildContext context,
    CatalogCubit cubit,
    CatalogState state,
  ) {
    return showFilterPicker(
      context: context,
      title: AppStrings.sortFilter,
      builder: (ctx) => FilterOptionList<CatalogSortOption>(
        applyLabel: AppStrings.applyFilters,
        initialSelected: state.sortBy,
        items: const [
          FilterOptionItem(
            value: CatalogSortOption.featured,
            label: AppStrings.sortFeatured,
          ),
          FilterOptionItem(
            value: CatalogSortOption.priceAsc,
            label: AppStrings.sortPriceAsc,
          ),
          FilterOptionItem(
            value: CatalogSortOption.priceDesc,
            label: AppStrings.sortPriceDesc,
          ),
          FilterOptionItem(
            value: CatalogSortOption.rating,
            label: AppStrings.sortBestRating,
          ),
          FilterOptionItem(
            value: CatalogSortOption.newest,
            label: AppStrings.sortNewest,
          ),
        ],
        onApply: (value) =>
            cubit.setSortBy(value ?? CatalogSortOption.featured),
      ),
    );
  }

  Future<void> _openOffersPicker(
    BuildContext context,
    CatalogCubit cubit,
    CatalogState state,
  ) {
    return showFilterPicker(
      context: context,
      title: AppStrings.offersOnly,
      builder: (ctx) => FilterOptionList<bool>(
        applyLabel: AppStrings.applyFilters,
        showClearOption: true,
        clearLabel: AppStrings.allOption,
        initialSelected: state.onlyOffers ? true : null,
        items: const [
          FilterOptionItem(
            value: true,
            label: AppStrings.offersOnly,
          ),
        ],
        onApply: (value) => cubit.toggleOnlyOffers(value == true),
      ),
    );
  }

  Future<void> _openBadgePicker(
    BuildContext context,
    CatalogCubit cubit,
    CatalogState state,
  ) {
    return showFilterPicker(
      context: context,
      title: AppStrings.badgeFilter,
      builder: (ctx) => FilterOptionList<String>(
        applyLabel: AppStrings.applyFilters,
        showClearOption: true,
        clearLabel: AppStrings.allOption,
        initialSelected: state.selectedBadge,
        items: const [
          FilterOptionItem(
            value: 'best_seller',
            label: AppStrings.badgeBestSeller,
          ),
          FilterOptionItem(
            value: 'new',
            label: AppStrings.badgeNew,
          ),
          FilterOptionItem(
            value: 'exclusive',
            label: AppStrings.badgeExclusive,
          ),
        ],
        onApply: cubit.setBadge,
      ),
    );
  }
}
