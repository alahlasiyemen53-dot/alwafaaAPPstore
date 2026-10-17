import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/data/models/catalog/home_api_model.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/core/widgets/app_error_state.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/features/account/cubit/account_cubit.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/brands/cubit/brands_cubit.dart';
import 'package:store/features/brands/presentation/screens/brands_list_screen.dart';
import 'package:store/features/categories/cubit/categories_cubit.dart';
import 'package:store/features/categories/presentation/screens/categories_list_screen.dart';
import 'package:store/features/currency/cubit/currency_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/product_card.dart';
import 'package:store/features/home/cubit/home_cubit.dart';
import 'package:store/features/home/cubit/home_state.dart';
import 'package:store/features/discovery/cubit/recently_viewed_cubit.dart';
import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import '../widgets/brands_rail.dart';
import '../widgets/categories_rail.dart';
import '../widgets/home_banner_auto_slider.dart';
import '../widgets/home_collection_section.dart';
import '../widgets/recently_viewed_rail.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  /// Reloads home + catalog + currencies + account after a cold-start network failure.
  Future<void> _retryBootstrap(BuildContext context) async {
    final accountCubit = context.read<AccountCubit>();
    final accountReload = accountCubit.state.isLoggedIn
        ? accountCubit.refreshProfile()
        : accountCubit.restoreSession();

    await Future.wait([
      context.read<HomeCubit>().loadHomeData(),
      context.read<CatalogCubit>().loadInitialData(),
      context.read<CurrencyCubit>().loadCurrencies(force: true),
      context.read<BrandsCubit>().retry(),
      context.read<CategoriesCubit>().retry(),
      accountReload,
    ]);
  }

  void _handleBannerTap(BuildContext context, HomeBannerModel banner) {
    final dest = banner.destination;
    if (dest == null) return;
    if (dest.type == 'product') {
      context.read<AppShellCubit>().openProductDetails(
            ProductModel(
              id: dest.id.toString(),
              serverId: dest.id,
              name: dest.nameAr ?? banner.displayTitle,
              brand: '',
              category: ProductCategory.all,
              priceSAR: 0.0,
              rating: 5.0,
              reviewsCount: 0,
              image: banner.imageUrl,
              images: banner.imageUrl.isNotEmpty ? [banner.imageUrl] : [],
              description: banner.displaySubtitle,
              inStock: true,
            ),
          );
    } else if (dest.type == 'category') {
      context.read<CatalogCubit>().selectCategory(ProductCategory.all, serverId: dest.id);
      context.read<AppShellCubit>().setTab(1);
    } else if (dest.type == 'collection') {
      CollectionDetailScreen.open(
        context,
        dest.slug?.isNotEmpty == true ? dest.slug! : dest.id,
        title: dest.nameAr,
      );
    } else if (dest.type == 'promotion') {
      context.read<AppShellCubit>().setTab(1);
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<CatalogCubit, CatalogState>(
      builder: (context, catalogState) {
        return BlocBuilder<HomeCubit, HomeState>(
          builder: (context, homeState) {
            if (homeState is HomeLoading && catalogState.allProducts.isEmpty) {
              return const HomeSkeleton();
            }

            if (homeState is HomeError && catalogState.allProducts.isEmpty) {
              return AppErrorState(
                title: 'حدث خطأ في تحميل الصفحة الرئيسية',
                message: 'تحقق من الاتصال ثم أعد المحاولة لتحميل الصفحة والعملات والحساب.',
                onRetry: () => _retryBootstrap(context),
              );
            }

            // Resolve Home Data
            final HomeApiModel? homeData = homeState is HomeLoaded ? homeState.homeData : null;

            final List<ProductModel> bestSellers = homeState is HomeLoaded && homeState.bestSellers.isNotEmpty
                ? homeState.bestSellers
                : catalogState.allProducts.where((p) => p.isBestSeller).toList();

            final List<ProductModel> newArrivals = homeState is HomeLoaded && homeState.newArrivals.isNotEmpty
                ? homeState.newArrivals
                : catalogState.allProducts.where((p) => p.isNew).toList();

            final List<ProductModel> exclusive = homeState is HomeLoaded ? homeState.exclusive : [];

            // Categories — root categories only on the home rail
            final List<CategoryModel> categories = homeData != null &&
                    homeData.featuredCategories.isNotEmpty
                ? homeData.featuredCategories
                    .where((c) => c.isRoot)
                    .map((c) => CategoryModel.fromHomeSummary(c))
                    .toList()
                : catalogState.categories
                    .where((c) => c.serverId != null && c.parentId == null)
                    .toList();

            // Hero banner carousel (reuses existing Home API banner list)
            final List<HomeBannerModel> heroBanners =
                homeData?.banners ?? const <HomeBannerModel>[];

            return RefreshIndicator(
              color: AppColors.goldDark,
              onRefresh: () async {
                final accountCubit = context.read<AccountCubit>();
                final accountReload = accountCubit.state.isLoggedIn
                    ? accountCubit.refreshProfile()
                    : accountCubit.restoreSession();
                await Future.wait([
                  context.read<HomeCubit>().refresh(),
                  context.read<CatalogCubit>().loadProducts(refresh: true),
                  context.read<CurrencyCubit>().loadCurrencies(force: true),
                  context.read<RecentlyViewedCubit>().load(),
                  accountReload,
                ]);
              },
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: EdgeInsets.only(
                  bottom: 120.h + MediaQuery.of(context).padding.bottom,
                ),
                children: homeState is HomeLoaded &&
                        homeState.hasServerDrivenSections
                    ? _buildServerDrivenSections(context, homeState)
                    : [
                  // Editorial Hero Auto Slider
                  HomeBannerAutoSlider(
                    banners: heroBanners,
                    onExploreTap: () {
                      context.read<CatalogCubit>().selectCategory(ProductCategory.all);
                      context.read<AppShellCubit>().setTab(1);
                    },
                    onBannerTap: (banner) => _handleBannerTap(context, banner),
                  ),

                  SizedBox(height: 14.h),

                  // Categories Rail
                  if (categories.isNotEmpty) ...[
                    CategoriesRail(
                      categories: categories,
                      onSelectCategory: (catId) {
                        context.read<CatalogCubit>().selectCategory(catId);
                        context.read<AppShellCubit>().setTab(1);
                      },
                      onSelectCategoryModel: (cat) {
                        context.read<CatalogCubit>().selectCategory(
                              cat.id,
                              serverId: cat.serverId,
                            );
                        context.read<AppShellCubit>().setTab(1);
                      },
                      onViewAll: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => const CategoriesListScreen(),
                          ),
                        );
                      },
                    ),
                    SizedBox(height: 10.h),
                  ],

                  // Brands Rail
                  Builder(builder: (context) {
                    final brandsState = context.watch<BrandsCubit>().state;
                    if (brandsState.brands.isEmpty) return const SizedBox.shrink();
                    return Column(
                      children: [
                        BrandsRail(
                          brands: brandsState.brands,
                          onSelectBrand: (brand) {
                            context.read<CatalogCubit>().selectBrand(
                                  brand.id,
                                  brandName: brand.displayName,
                                );
                            context.read<AppShellCubit>().setTab(1);
                          },
                          onViewAll: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const BrandsListScreen()),
                            );
                          },
                        ),
                        SizedBox(height: 20.h),
                      ],
                    );
                  }),

                  // Best Sellers Section
                  if (bestSellers.isNotEmpty) ...[
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16.w),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            AppStrings.bestSellers,
                            style: AppTypography.titleMedium.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.primaryDark,
                            ),
                          ),
                          GestureDetector(
                            onTap: () {
                              context.read<AppShellCubit>().setTab(1);
                            },
                            child: Row(
                              children: [
                                Text(
                                  AppStrings.viewAll,
                                  style: TextStyle(
                                    fontSize: 11.sp,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.goldDark,
                                  ),
                                ),
                                Icon(Icons.chevron_right_rounded, size: 16.w, color: AppColors.goldDark),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 12.h),

                    // Best Sellers Horizontal Rail
                    SizedBox(
                      height: 285.h,
                      child: ListView.separated(
                        padding: EdgeInsets.symmetric(horizontal: 16.w),
                        scrollDirection: Axis.horizontal,
                        itemCount: bestSellers.length,
                        separatorBuilder: (_, __) => SizedBox(width: 14.w),
                        itemBuilder: (context, index) {
                          final product = bestSellers[index];
                          return SizedBox(
                            width: 170.w,
                            child: ProductCard(
                              product: product,
                              variation: CardVariation.classic,
                              onTap: () {
                                context.read<AppShellCubit>().openProductDetails(product);
                              },
                            ),
                          );
                        },
                      ),
                    ),
                    SizedBox(height: 20.h),
                  ],

                  // Promotional Flash Offer Card
                  Container(
                    margin: EdgeInsets.symmetric(horizontal: 16.w),
                    padding: EdgeInsets.all(18.w),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppColors.primaryContainer, AppColors.primaryDark],
                        begin: Alignment.topRight,
                        end: Alignment.bottomLeft,
                      ),
                      borderRadius: BorderRadius.circular(24.r),
                      border: Border.all(color: AppColors.gold.withValues(alpha: 0.3), width: 1),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primaryDark.withValues(alpha: 0.2),
                          blurRadius: 16,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
                                decoration: BoxDecoration(
                                  color: AppColors.gold,
                                  borderRadius: BorderRadius.circular(12.r),
                                ),
                                child: Text(
                                  'كود خصم حصري: BEAUTY20',
                                  style: TextStyle(
                                    fontSize: 9.5.sp,
                                    fontWeight: FontWeight.w900,
                                    color: AppColors.primaryDark,
                                  ),
                                ),
                              ),
                              SizedBox(height: 8.h),
                              Text(
                                'خصم 20% على باقات الهدايا الملكية',
                                style: TextStyle(
                                  fontSize: 14.sp,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.textLight,
                                  height: 1.3,
                                ),
                              ),
                              SizedBox(height: 4.h),
                              Text(
                                'احصل على عينات عطور نيش فاخرة وتغليف مجاني مع كل طلب.',
                                style: TextStyle(
                                  fontSize: 10.5.sp,
                                  color: AppColors.goldLight,
                                ),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(width: 12.w),
                        ElevatedButton(
                          onPressed: () {
                            context.read<CatalogCubit>().selectCategory(ProductCategory.gifts);
                            context.read<AppShellCubit>().setTab(1);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: AppColors.primaryDark,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14.r),
                            ),
                            padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
                          ),
                          child: Text(
                            'تسوق الآن',
                            style: TextStyle(fontSize: 11.sp, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ),

                  SizedBox(height: 20.h),

                  // Exclusive Section if available
                  if (exclusive.isNotEmpty) ...[
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16.w),
                      child: Text(
                        'منتجات حصرية',
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                    SizedBox(height: 12.h),
                    SizedBox(
                      height: 285.h,
                      child: ListView.separated(
                        padding: EdgeInsets.symmetric(horizontal: 16.w),
                        scrollDirection: Axis.horizontal,
                        itemCount: exclusive.length,
                        separatorBuilder: (_, __) => SizedBox(width: 14.w),
                        itemBuilder: (context, index) {
                          final product = exclusive[index];
                          return SizedBox(
                            width: 170.w,
                            child: ProductCard(
                              product: product,
                              variation: CardVariation.classic,
                              onTap: () {
                                context.read<AppShellCubit>().openProductDetails(product);
                              },
                            ),
                          );
                        },
                      ),
                    ),
                    SizedBox(height: 20.h),
                  ],

                  // New Arrivals Section
                  if (newArrivals.isNotEmpty) ...[
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16.w),
                      child: Text(
                        AppStrings.newArrivals,
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                    SizedBox(height: 12.h),

                    // New Arrivals Horizontal Rail
                    SizedBox(
                      height: 285.h,
                      child: ListView.separated(
                        padding: EdgeInsets.symmetric(horizontal: 16.w),
                        scrollDirection: Axis.horizontal,
                        itemCount: newArrivals.length,
                        separatorBuilder: (_, __) => SizedBox(width: 14.w),
                        itemBuilder: (context, index) {
                          final product = newArrivals[index];
                          return SizedBox(
                            width: 170.w,
                            child: ProductCard(
                              product: product,
                              variation: CardVariation.classic,
                              onTap: () {
                                context.read<AppShellCubit>().openProductDetails(product);
                              },
                            ),
                          );
                        },
                      ),
                    ),
                    SizedBox(height: 16.h),
                  ],

                  // Recently viewed fallback rail
                  const RecentlyViewedRail(),
                ],
              ),
            );
          },
        );
      },
    );
  }

  List<Widget> _buildServerDrivenSections(
    BuildContext context,
    HomeLoaded homeState,
  ) {
    final widgets = <Widget>[];

    for (final section in homeState.homeSections) {
      switch (section.type) {
        case HomeSectionTypes.banners:
          final banners = section.bannerItems.isNotEmpty
              ? section.bannerItems
              : homeState.banners;
          if (banners.isEmpty) break;
          widgets.add(HomeBannerAutoSlider(
            banners: banners,
            onExploreTap: () {
              context
                  .read<CatalogCubit>()
                  .selectCategory(ProductCategory.all);
              context.read<AppShellCubit>().setTab(1);
            },
            onBannerTap: (banner) => _handleBannerTap(context, banner),
          ));
          widgets.add(SizedBox(height: 14.h));
          break;

        case HomeSectionTypes.categories:
          final cats = (section.categoryItems.isNotEmpty
                  ? section.categoryItems
                  : homeState.featuredCategories)
              .where((c) => c.isRoot)
              .map(CategoryModel.fromHomeSummary)
              .toList();
          if (cats.isEmpty) break;
          widgets.add(CategoriesRail(
            categories: cats,
            onSelectCategory: (catId) {
              context.read<CatalogCubit>().selectCategory(catId);
              context.read<AppShellCubit>().setTab(1);
            },
            onSelectCategoryModel: (cat) {
              context.read<CatalogCubit>().selectCategory(
                    cat.id,
                    serverId: cat.serverId,
                  );
              context.read<AppShellCubit>().setTab(1);
            },
            onViewAll: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const CategoriesListScreen(),
                ),
              );
            },
          ));
          widgets.add(SizedBox(height: 10.h));
          break;

        case HomeSectionTypes.brands:
          final brands = section.brandItems;
          if (brands.isEmpty) {
            widgets.add(Builder(builder: (context) {
              final brandsState = context.watch<BrandsCubit>().state;
              if (brandsState.brands.isEmpty) return const SizedBox.shrink();
              return Column(
                children: [
                  BrandsRail(
                    brands: brandsState.brands,
                    onSelectBrand: (brand) {
                      context.read<CatalogCubit>().selectBrand(
                            brand.id,
                            brandName: brand.displayName,
                          );
                      context.read<AppShellCubit>().setTab(1);
                    },
                    onViewAll: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                            builder: (_) => const BrandsListScreen()),
                      );
                    },
                  ),
                  SizedBox(height: 20.h),
                ],
              );
            }));
          } else {
            widgets.add(BrandsRail(
              brands: brands,
              onSelectBrand: (brand) {
                context.read<CatalogCubit>().selectBrand(
                      brand.id,
                      brandName: brand.displayName,
                    );
                context.read<AppShellCubit>().setTab(1);
              },
              onViewAll: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => const BrandsListScreen()),
                );
              },
            ));
            widgets.add(SizedBox(height: 20.h));
          }
          break;

        case HomeSectionTypes.bestSellers:
        case HomeSectionTypes.newProducts:
        case HomeSectionTypes.exclusiveProducts:
          final products = section.productItems
              .map(ProductModel.fromApiListItem)
              .toList();
          if (products.isEmpty) break;
          final title = section.displayTitle.isNotEmpty
              ? section.displayTitle
              : (section.type == HomeSectionTypes.bestSellers
                  ? AppStrings.bestSellers
                  : section.type == HomeSectionTypes.newProducts
                      ? AppStrings.newArrivals
                      : 'منتجات حصرية');
          widgets.add(Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Text(
              title,
              style: AppTypography.titleMedium.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primaryDark,
              ),
            ),
          ));
          widgets.add(SizedBox(height: 12.h));
          widgets.add(SizedBox(
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
          ));
          widgets.add(SizedBox(height: 20.h));
          break;

        case HomeSectionTypes.collection:
          widgets.add(HomeCollectionSection(section: section));
          break;

        case HomeSectionTypes.recentlyViewed:
          final products = section.productItems
              .map(ProductModel.fromApiListItem)
              .toList();
          widgets.add(RecentlyViewedRail(
            title: section.displayTitle.isNotEmpty
                ? section.displayTitle
                : 'شاهدتها مؤخراً',
            products: products.isNotEmpty ? products : null,
          ));
          break;
      }
    }

    // Guest fallback: if no recently_viewed section or empty server data.
    final hasRecentlyViewedSection = homeState.homeSections
        .any((s) => s.type == HomeSectionTypes.recentlyViewed);
    if (!hasRecentlyViewedSection) {
      widgets.add(const RecentlyViewedRail());
    }

    return widgets;
  }
}
