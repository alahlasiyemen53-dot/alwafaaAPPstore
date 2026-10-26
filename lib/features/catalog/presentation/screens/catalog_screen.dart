import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_staggered_grid_view/flutter_staggered_grid_view.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/app_error_state.dart';
import 'package:store/core/widgets/luxury_text_field.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/discovery/cubit/search_history_cubit.dart';
import 'package:store/features/visual_search/presentation/screens/visual_search_dialog.dart';
import '../widgets/filters/product_filter_bar.dart';
import '../widgets/product_card.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounceTimer;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    // Restore search text if CatalogCubit still holds an active query
    // (e.g. after an unexpected remount).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final query = context.read<CatalogCubit>().state.searchQuery;
      if (query.isNotEmpty && _searchController.text != query) {
        _searchController.text = query;
        _searchController.selection =
            TextSelection.collapsed(offset: query.length);
      }
    });
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _searchController.dispose();
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    _maybeLoadMore();
  }

  void _maybeLoadMore() {
    if (!_scrollController.hasClients) return;
    final position = _scrollController.position;
    // Also load when content is shorter than the viewport (no scroll possible).
    final nearBottom = position.maxScrollExtent <= 0 ||
        position.pixels >= position.maxScrollExtent - 200;
    if (nearBottom) {
      context.read<CatalogCubit>().loadMoreProducts();
    }
  }

  void _onSearchChanged(String val, CatalogCubit cubit) {
    cubit.updateSearchQuery(val);
    _debounceTimer?.cancel();
    if (val.trim().isEmpty) {
      cubit.searchProducts('', refresh: true);
      return;
    }
    _debounceTimer = Timer(const Duration(milliseconds: 500), () {
      cubit.searchProducts(val, refresh: true);
    });
  }

  void _onSearchSubmitted(String val, CatalogCubit cubit) {
    _debounceTimer?.cancel();
    cubit.searchProducts(val, refresh: true);
    context.read<SearchHistoryCubit>().recordSubmittedQuery(val);
  }

  void _runHistorySearch(String query, CatalogCubit cubit) {
    _debounceTimer?.cancel();
    _searchController.text = query;
    _searchController.selection =
        TextSelection.collapsed(offset: query.length);
    cubit.searchProducts(query, refresh: true);
    context.read<SearchHistoryCubit>().recordSubmittedQuery(query);
  }

  void _clearSearch(CatalogCubit cubit) {
    _debounceTimer?.cancel();
    _searchController.clear();
    cubit.updateSearchQuery('');
    cubit.searchProducts('', refresh: true);
  }

  void _syncSearchFieldFromState(CatalogState state) {
    if (state.searchQuery.isEmpty && _searchController.text.isNotEmpty) {
      _searchController.clear();
    } else if (state.searchQuery.isNotEmpty &&
        _searchController.text.isEmpty) {
      _searchController.text = state.searchQuery;
      _searchController.selection = TextSelection.collapsed(
        offset: state.searchQuery.length,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocListener(
      listeners: [
        BlocListener<CatalogCubit, CatalogState>(
          listenWhen: (previous, current) =>
              previous.allProducts.length != current.allProducts.length ||
              previous.isLoadingMore != current.isLoadingMore ||
              previous.isLoading != current.isLoading ||
              previous.hasMore != current.hasMore,
          listener: (context, state) {
            if (state.isLoading || state.isLoadingMore || !state.hasMore) {
              return;
            }
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (!mounted) return;
              _maybeLoadMore();
            });
          },
        ),
        BlocListener<CatalogCubit, CatalogState>(
          listenWhen: (previous, current) =>
              previous.searchQuery != current.searchQuery,
          listener: (context, state) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (!mounted) return;
              _syncSearchFieldFromState(state);
            });
          },
        ),
      ],
      child: BlocBuilder<CatalogCubit, CatalogState>(
      builder: (context, state) {
        final cubit = context.read<CatalogCubit>();
        final filteredProducts = state.filteredProducts;

        return Column(
          children: [
            // Search & Filter Bar
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
              child: Row(
                children: [
                  Expanded(
                    child: LuxuryTextField(
                      controller: _searchController,
                      hintText: AppStrings.searchPlaceholder,
                      compact: true,
                      prefixIcon: Icon(Icons.search_rounded,
                          size: 18.w, color: AppColors.textMuted),
                      suffixIcon: Padding(
                        padding: EdgeInsetsDirectional.only(end: 4.w),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (state.searchQuery.isNotEmpty)
                              IconButton(
                                onPressed: () => _clearSearch(cubit),
                                visualDensity: VisualDensity.compact,
                                padding: EdgeInsets.zero,
                                constraints: BoxConstraints(
                                  minWidth: 28.w,
                                  minHeight: 28.w,
                                ),
                                icon: Icon(Icons.close_rounded,
                                    size: 16.w, color: AppColors.textMuted),
                              ),
                            IconButton(
                              onPressed: () => VisualSearchDialog.show(context),
                              tooltip: AppStrings.visualSearch,
                              visualDensity: VisualDensity.compact,
                              padding: EdgeInsets.zero,
                              constraints: BoxConstraints(
                                minWidth: 32.w,
                                minHeight: 32.w,
                              ),
                              icon: Container(
                                padding: EdgeInsets.all(5.w),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceMuted,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  Icons.camera_alt_outlined,
                                  size: 15.w,
                                  color: AppColors.primaryDark,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      onChanged: (val) => _onSearchChanged(val, cubit),
                      onSubmitted: (val) => _onSearchSubmitted(val, cubit),
                    ),
                  ),
                ],
              ),
            ),

            // Recent search chips (when not actively browsing results-only)
            if (!state.isSearching || state.searchQuery.trim().isEmpty)
              BlocBuilder<SearchHistoryCubit, SearchHistoryState>(
                builder: (context, historyState) {
                  if (historyState.items.isEmpty) {
                    return const SizedBox.shrink();
                  }
                  return Padding(
                    padding: EdgeInsets.only(top: 8.h, bottom: 2.h),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: EdgeInsets.symmetric(horizontal: 16.w),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  'عمليات البحث الأخيرة',
                                  style: TextStyle(
                                    fontSize: 11.sp,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ),
                              GestureDetector(
                                onTap: () => context
                                    .read<SearchHistoryCubit>()
                                    .clearAll(),
                                child: Text(
                                  'مسح السجل',
                                  style: TextStyle(
                                    fontSize: 10.5.sp,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.goldDark,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(height: 8.h),
                        SizedBox(
                          height: 34.h,
                          child: ListView.separated(
                            padding: EdgeInsets.symmetric(horizontal: 16.w),
                            scrollDirection: Axis.horizontal,
                            itemCount: historyState.items.length,
                            separatorBuilder: (_, _) => SizedBox(width: 8.w),
                            itemBuilder: (context, index) {
                              final item = historyState.items[index];
                              return ActionChip(
                                label: Text(
                                  item.query,
                                  style: TextStyle(
                                    fontSize: 11.sp,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.primaryDark,
                                  ),
                                ),
                                onPressed: () =>
                                    _runHistorySearch(item.query, cubit),
                                backgroundColor:
                                    AppColors.surfaceSoft,
                                side: BorderSide(
                                  color: AppColors.border,
                                  width: 0.8,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12.r),
                                ),
                                visualDensity: VisualDensity.compact,
                                materialTapTargetSize:
                                    MaterialTapTargetSize.shrinkWrap,
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),

            SizedBox(height: 8.h),
            const ProductFilterBar(),

            SizedBox(height: 6.h),

            // Top Status & Layout Switcher Bar
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Row(
                children: [
                  Expanded(
                    child: Row(
                      children: [
                        Flexible(
                          child: Text(
                            '${state.displayedProductCount} منتج متاح',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 10.5.sp,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ),
                        if (state.isSearching) ...[
                          SizedBox(width: 6.w),
                          Container(
                            padding: EdgeInsets.symmetric(
                                horizontal: 6.w, vertical: 2.h),
                            decoration: BoxDecoration(
                              color: AppColors.gold.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(8.r),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.search_rounded,
                                    size: 10.w, color: AppColors.goldDark),
                                SizedBox(width: 3.w),
                                Text(
                                  'بحث نشط',
                                  style: TextStyle(
                                    fontSize: 9.sp,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.goldDark,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ] else if (state.isUsingApi) ...[
                          SizedBox(width: 6.w),
                          Container(
                            padding: EdgeInsets.symmetric(
                                horizontal: 6.w, vertical: 2.h),
                            decoration: BoxDecoration(
                              color: AppColors.gold.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(8.r),
                            ),
                            child: Text(
                              'مباشر',
                              style: TextStyle(
                                fontSize: 9.sp,
                                fontWeight: FontWeight.w600,
                                color: AppColors.goldDark,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),

                  // View Mode Buttons (Staggered Grid / Compact List)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        onPressed: () =>
                            cubit.setCardVariation(CardVariation.staggered),
                        tooltip: 'شبكة متدرجة',
                        icon: Icon(
                          Icons.dashboard_rounded,
                          size: 18.w,
                          color: state.cardVariation == CardVariation.staggered
                              ? AppColors.primaryDark
                              : AppColors.textMuted,
                        ),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        visualDensity: VisualDensity.compact,
                      ),
                      SizedBox(width: 8.w),
                      IconButton(
                        onPressed: () =>
                            cubit.setCardVariation(CardVariation.compact),
                        tooltip: 'قائمة مدمجة',
                        icon: Icon(
                          Icons.view_agenda_outlined,
                          size: 18.w,
                          color: state.cardVariation == CardVariation.compact
                              ? AppColors.primaryDark
                              : AppColors.textMuted,
                        ),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        visualDensity: VisualDensity.compact,
                      ),
                    ],
                  ),
                ],
              ),
            ),

            SizedBox(height: 8.h),

            // Products View
            Expanded(
              child: _buildProductsContent(
                  context, state, cubit, filteredProducts),
            ),
          ],
        );
      },
      ),
    );
  }

  Widget _buildProductsContent(
    BuildContext context,
    CatalogState state,
    CatalogCubit cubit,
    List<ProductModel> filteredProducts,
  ) {
    if (state.isLoading && state.allProducts.isEmpty) {
      return ProductGridSkeleton(variation: state.cardVariation);
    }

    if (state.errorMessage != null && state.allProducts.isEmpty) {
      return AppErrorState(
        title: 'تعذر الاتصال بكتالوج الخادم',
        message: 'تحقق من الاتصال ثم أعد المحاولة.',
        onRetry: () => cubit.loadInitialData(),
      );
    }

    if (filteredProducts.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off_rounded,
                size: 48.w, color: AppColors.textMuted),
            SizedBox(height: 12.h),
            Text(
              'لم نتمكن من العثور على نتائج مطابقة',
              style: AppTypography.titleSmall
                  .copyWith(color: AppColors.textPrimary),
            ),
            SizedBox(height: 6.h),
            TextButton(
              onPressed: () {
                _clearSearch(cubit);
                cubit.resetFilters();
              },
              child: const Text('إعادة ضبط كافة الفلاتر'),
            ),
          ],
        ),
      );
    }

    final bottomPad = 120.h + MediaQuery.of(context).padding.bottom;
    final itemCount =
        filteredProducts.length + (state.isLoadingMore ? 2 : 0);

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async {
        await Future.wait([
          state.isSearching && state.searchQuery.trim().isNotEmpty
              ? cubit.searchProducts(state.searchQuery, refresh: true)
              : cubit.loadProducts(refresh: true),
          context.read<CurrencyCubit>().loadCurrencies(force: true),
        ]);
      },
      child: state.cardVariation == CardVariation.compact
          ? ListView.separated(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.only(
                left: 16.w,
                right: 16.w,
                bottom: bottomPad,
              ),
              itemCount: itemCount,
              separatorBuilder: (_, _) => SizedBox(height: 10.h),
              itemBuilder: (context, index) {
                if (index >= filteredProducts.length) {
                  return const AppShimmer(
                    child: ProductCardSkeleton(
                      variation: CardVariation.compact,
                    ),
                  );
                }

                final product = filteredProducts[index];
                return ProductCard(
                  key: ValueKey('product-compact-${product.id}'),
                  product: product,
                  variation: CardVariation.compact,
                  onTap: () {
                    context.read<AppShellCubit>().openProductDetails(product);
                  },
                );
              },
            )
          : MasonryGridView.count(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.only(
                left: 16.w,
                right: 16.w,
                bottom: bottomPad,
              ),
              crossAxisCount: 2,
              mainAxisSpacing: 12.h,
              crossAxisSpacing: 12.w,
              itemCount: itemCount,
              itemBuilder: (context, index) {
                if (index >= filteredProducts.length) {
                  return AppShimmer(
                    child: ProductCardSkeleton(
                      variation: CardVariation.staggered,
                      staggerIndex: index,
                    ),
                  );
                }

                final product = filteredProducts[index];
                return ProductCard(
                  key: ValueKey('product-staggered-${product.id}'),
                  product: product,
                  variation: CardVariation.staggered,
                  staggerIndex: index,
                  onTap: () {
                    context.read<AppShellCubit>().openProductDetails(product);
                  },
                );
              },
            ),
    );
  }
}

