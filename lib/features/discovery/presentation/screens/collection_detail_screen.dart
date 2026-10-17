import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/cached_image_widget.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/catalog/presentation/widgets/product_card.dart';
import 'package:store/features/discovery/cubit/collections_cubit.dart';

class CollectionDetailScreen extends StatefulWidget {
  final dynamic idOrSlug;
  final String? initialTitle;

  const CollectionDetailScreen({
    super.key,
    required this.idOrSlug,
    this.initialTitle,
  });

  static void open(
    BuildContext context,
    dynamic idOrSlug, {
    String? title,
  }) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CollectionDetailScreen(
          idOrSlug: idOrSlug,
          initialTitle: title,
        ),
      ),
    );
  }

  @override
  State<CollectionDetailScreen> createState() => _CollectionDetailScreenState();
}

class _CollectionDetailScreenState extends State<CollectionDetailScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    context.read<CollectionsCubit>().loadCollectionDetail(widget.idOrSlug);
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<CollectionsCubit>().loadMoreProducts();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: BlocBuilder<CollectionsCubit, CollectionsState>(
        builder: (context, state) {
          final title = state.selectedCollection?.displayName ??
              widget.initialTitle ??
              'مجموعة المنتجات';
          final cover = state.selectedCollection?.coverUrl;

          return CustomScrollView(
            controller: _scrollController,
            slivers: [
              SliverAppBar(
                pinned: true,
                expandedHeight: cover != null && cover.isNotEmpty ? 180.h : 56.h,
                title: Text(
                  title,
                  style: AppTypography.titleMedium
                      .copyWith(fontWeight: FontWeight.w700),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                flexibleSpace: cover != null && cover.isNotEmpty
                    ? FlexibleSpaceBar(
                        background: CachedImageWidget(
                          imageUrl: cover,
                          fit: BoxFit.cover,
                        ),
                      )
                    : null,
              ),
              if (state.status == CollectionsStatus.loading &&
                  state.products.isEmpty)
                const SliverFillRemaining(
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (state.status == CollectionsStatus.error &&
                  state.products.isEmpty)
                SliverFillRemaining(
                  child: Center(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 24.w),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            state.errorMessage ?? 'تعذر تحميل المجموعة.',
                            textAlign: TextAlign.center,
                          ),
                          SizedBox(height: 12.h),
                          ElevatedButton(
                            onPressed: () => context
                                .read<CollectionsCubit>()
                                .loadCollectionDetail(widget.idOrSlug),
                            child: const Text('إعادة المحاولة'),
                          ),
                        ],
                      ),
                    ),
                  ),
                )
              else if (state.productModels.isEmpty)
                SliverFillRemaining(
                  child: Center(
                    child: Text(
                      'لا توجد منتجات في هذه المجموعة',
                      style: AppTypography.bodyMedium.copyWith(
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                )
              else ...[
                if (state.selectedCollection?.descriptionAr != null &&
                    state.selectedCollection!.descriptionAr!.isNotEmpty)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
                      child: Text(
                        state.selectedCollection!.descriptionAr!,
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ),
                ObstructionAwareSliverPadding(
                  padding: EdgeInsets.fromLTRB(
                    16.w,
                    12.h,
                    16.w,
                    24.h + MediaQuery.paddingOf(context).bottom,
                  ),
                  sliver: SliverGrid(
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      mainAxisSpacing: 14.h,
                      crossAxisSpacing: 12.w,
                      childAspectRatio: 0.58,
                    ),
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        if (index >= state.productModels.length) {
                          return const Center(
                            child: CircularProgressIndicator(),
                          );
                        }
                        final product = state.productModels[index];
                        return ProductCard(
                          product: product,
                          variation: CardVariation.classic,
                          onTap: () => context
                              .read<AppShellCubit>()
                              .openProductDetails(product),
                        );
                      },
                      childCount: state.productModels.length +
                          (state.isLoadingMore ? 1 : 0),
                    ),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// Thin wrapper so padding works with SliverGrid without extra deps.
class ObstructionAwareSliverPadding extends StatelessWidget {
  final EdgeInsetsGeometry padding;
  final Widget sliver;

  const ObstructionAwareSliverPadding({
    super.key,
    required this.padding,
    required this.sliver,
  });

  @override
  Widget build(BuildContext context) {
    return SliverPadding(padding: padding, sliver: sliver);
  }
}
