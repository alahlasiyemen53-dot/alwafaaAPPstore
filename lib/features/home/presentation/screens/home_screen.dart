import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
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
import 'package:store/features/home/cubit/home_cubit.dart';
import 'package:store/features/home/cubit/home_state.dart';
import 'package:store/features/discovery/cubit/recently_viewed_cubit.dart';
import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import '../widgets/brands_rail.dart';
import '../widgets/categories_rail.dart';
import '../widgets/home_banner_auto_slider.dart';
import '../widgets/home_collection_section.dart';
import '../widgets/home_product_rail.dart';
import '../widgets/home_promo_banner.dart';
import '../widgets/recently_viewed_rail.dart';
import '../widgets/trust_badges_section.dart';

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
      context
          .read<CatalogCubit>()
          .selectCategory(ProductCategory.all, serverId: dest.id);
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

  void _openCatalog(BuildContext context) {
    context.read<AppShellCubit>().setTab(1);
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
                message:
                    'تحقق من الاتصال ثم أعد المحاولة لتحميل الصفحة والعملات والحساب.',
                onRetry: () => _retryBootstrap(context),
              );
            }

            final HomeApiModel? homeData =
                homeState is HomeLoaded ? homeState.homeData : null;

            final List<ProductModel> bestSellers = homeState is HomeLoaded &&
                    homeState.bestSellers.isNotEmpty
                ? homeState.bestSellers
                : catalogState.allProducts.where((p) => p.isBestSeller).toList();

            final List<ProductModel> newArrivals = homeState is HomeLoaded &&
                    homeState.newArrivals.isNotEmpty
                ? homeState.newArrivals
                : catalogState.allProducts.where((p) => p.isNew).toList();

            final List<ProductModel> exclusive =
                homeState is HomeLoaded ? homeState.exclusive : [];

            final List<CategoryModel> categories = homeData != null &&
                    homeData.featuredCategories.isNotEmpty
                ? homeData.featuredCategories
                    .where((c) => c.isRoot)
                    .map((c) => CategoryModel.fromHomeSummary(c))
                    .toList()
                : catalogState.categories
                    .where((c) => c.serverId != null && c.parentId == null)
                    .toList();

            final List<HomeBannerModel> heroBanners =
                homeData?.banners ?? const <HomeBannerModel>[];

            return DecoratedBox(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0xFFFFFFFF),
                    AppColors.primaryContainer,
                    AppColors.background,
                  ],
                  stops: [0.0, 0.18, 0.42],
                ),
              ),
              child: RefreshIndicator(
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
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: EdgeInsets.only(
                    top: 6.h,
                    bottom: 120.h + MediaQuery.of(context).padding.bottom,
                  ),
                  children: homeState is HomeLoaded &&
                          homeState.hasServerDrivenSections
                      ? _buildServerDrivenSections(context, homeState)
                      : [
                          HomeBannerAutoSlider(
                            banners: heroBanners,
                            onExploreTap: () {
                              context
                                  .read<CatalogCubit>()
                                  .selectCategory(ProductCategory.all);
                              _openCatalog(context);
                            },
                            onBannerTap: (banner) =>
                                _handleBannerTap(context, banner),
                          ),
                          SizedBox(height: 18.h),
                          if (categories.isNotEmpty) ...[
                            CategoriesRail(
                              categories: categories,
                              onSelectCategory: (catId) {
                                context
                                    .read<CatalogCubit>()
                                    .selectCategory(catId);
                                _openCatalog(context);
                              },
                              onSelectCategoryModel: (cat) {
                                context.read<CatalogCubit>().selectCategory(
                                      cat.id,
                                      serverId: cat.serverId,
                                    );
                                _openCatalog(context);
                              },
                              onViewAll: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        const CategoriesListScreen(),
                                  ),
                                );
                              },
                            ),
                            SizedBox(height: 18.h),
                          ],
                          Builder(builder: (context) {
                            final brandsState =
                                context.watch<BrandsCubit>().state;
                            if (brandsState.brands.isEmpty) {
                              return const SizedBox.shrink();
                            }
                            return Column(
                              children: [
                                BrandsRail(
                                  brands: brandsState.brands,
                                  onSelectBrand: (brand) {
                                    context.read<CatalogCubit>().selectBrand(
                                          brand.id,
                                          brandName: brand.displayName,
                                        );
                                    _openCatalog(context);
                                  },
                                  onViewAll: () {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) =>
                                            const BrandsListScreen(),
                                      ),
                                    );
                                  },
                                ),
                                SizedBox(height: 22.h),
                              ],
                            );
                          }),
                          HomeProductRail(
                            title: AppStrings.bestSellers,
                            products: bestSellers,
                            onViewAll: () => _openCatalog(context),
                            onProductTap: (product) {
                              context
                                  .read<AppShellCubit>()
                                  .openProductDetails(product);
                            },
                          ),
                          if (bestSellers.isNotEmpty) SizedBox(height: 20.h),
                          HomePromoBanner(
                            onShopTap: () {
                              context
                                  .read<CatalogCubit>()
                                  .selectCategory(ProductCategory.gifts);
                              _openCatalog(context);
                            },
                          ),
                          SizedBox(height: 20.h),
                          if (exclusive.isNotEmpty) ...[
                            HomeProductRail(
                              title: 'منتجات حصرية',
                              products: exclusive,
                              onViewAll: () => _openCatalog(context),
                              onProductTap: (product) {
                                context
                                    .read<AppShellCubit>()
                                    .openProductDetails(product);
                              },
                            ),
                            SizedBox(height: 20.h),
                          ],
                          HomeProductRail(
                            title: AppStrings.newArrivals,
                            products: newArrivals,
                            onViewAll: () => _openCatalog(context),
                            onProductTap: (product) {
                              context
                                  .read<AppShellCubit>()
                                  .openProductDetails(product);
                            },
                          ),
                          if (newArrivals.isNotEmpty) SizedBox(height: 8.h),
                          const TrustBadgesSection(),
                          const RecentlyViewedRail(),
                        ],
                ),
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
              _openCatalog(context);
            },
            onBannerTap: (banner) => _handleBannerTap(context, banner),
          ));
          widgets.add(SizedBox(height: 18.h));
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
              _openCatalog(context);
            },
            onSelectCategoryModel: (cat) {
              context.read<CatalogCubit>().selectCategory(
                    cat.id,
                    serverId: cat.serverId,
                  );
              _openCatalog(context);
            },
            onViewAll: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const CategoriesListScreen(),
                ),
              );
            },
          ));
          widgets.add(SizedBox(height: 18.h));
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
                      _openCatalog(context);
                    },
                    onViewAll: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const BrandsListScreen(),
                        ),
                      );
                    },
                  ),
                  SizedBox(height: 22.h),
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
                _openCatalog(context);
              },
              onViewAll: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const BrandsListScreen(),
                  ),
                );
              },
            ));
            widgets.add(SizedBox(height: 22.h));
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
          widgets.add(HomeProductRail(
            title: title,
            products: products,
            onViewAll: () => _openCatalog(context),
            onProductTap: (product) =>
                context.read<AppShellCubit>().openProductDetails(product),
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

    final hasRecentlyViewedSection = homeState.homeSections
        .any((s) => s.type == HomeSectionTypes.recentlyViewed);
    if (!hasRecentlyViewedSection) {
      widgets.add(const TrustBadgesSection());
      widgets.add(const RecentlyViewedRail());
    }

    return widgets;
  }
}
