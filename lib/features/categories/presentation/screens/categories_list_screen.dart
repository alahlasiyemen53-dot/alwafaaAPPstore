import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/widgets/luxury_text_field.dart';
import 'package:store/core/widgets/skeletons/skeletons.dart';
import 'package:store/data/models/category_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/catalog/cubit/catalog_cubit.dart';
import 'package:store/features/categories/cubit/categories_cubit.dart';
import 'package:store/features/categories/presentation/widgets/category_card.dart';

class CategoriesListScreen extends StatefulWidget {
  const CategoriesListScreen({super.key});

  @override
  State<CategoriesListScreen> createState() => _CategoriesListScreenState();
}

class _CategoriesListScreenState extends State<CategoriesListScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openCategoryProducts(CategoryModel category) {
    context.read<CatalogCubit>().selectCategory(
          category.id,
          serverId: category.serverId,
        );
    context.read<AppShellCubit>().setTab(1);
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  List<CategoryModel> _filtered(List<CategoryModel> categories) {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return categories;
    return categories.where((category) {
      return category.nameAr.toLowerCase().contains(q) ||
          category.nameEn.toLowerCase().contains(q) ||
          (category.slug?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        title: Text(
          'التصنيفات',
          style: AppTypography.titleMedium.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.primaryDark,
          ),
        ),
      ),
      body: BlocBuilder<CategoriesCubit, CategoriesState>(
        builder: (context, state) {
          switch (state.status) {
            case CategoriesStatus.initial:
            case CategoriesStatus.loading:
              return const BrandsGridSkeleton();

            case CategoriesStatus.error:
              return _ErrorBody(
                onRetry: () => context.read<CategoriesCubit>().retry(),
              );

            case CategoriesStatus.empty:
              return const _EmptyBody(
                title: 'لا توجد تصنيفات',
                subtitle: 'سيتم عرض التصنيفات هنا عند إضافتها.',
                icon: Icons.category_outlined,
              );

            case CategoriesStatus.success:
              final categories = _filtered(state.categories);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: EdgeInsets.fromLTRB(16.w, 4.h, 16.w, 10.h),
                    child: LuxuryTextField(
                      controller: _searchController,
                      hintText: 'ابحث عن تصنيف...',
                      prefixIcon: Icon(
                        Icons.search_rounded,
                        size: 20.w,
                        color: AppColors.textMuted,
                      ),
                      suffixIcon: _query.isNotEmpty
                          ? IconButton(
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _query = '');
                              },
                              icon: Icon(
                                Icons.close_rounded,
                                size: 18.w,
                                color: AppColors.textMuted,
                              ),
                            )
                          : null,
                      onChanged: (value) => setState(() => _query = value),
                    ),
                  ),
                  Padding(
                    padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 8.h),
                    child: Text(
                      categories.isEmpty
                          ? 'لا نتائج مطابقة'
                          : '${categories.length} تصنيف',
                      style: AppTypography.bodySmall.copyWith(
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  Expanded(
                    child: categories.isEmpty
                        ? const _EmptyBody(
                            title: 'لا نتائج',
                            subtitle: 'جرّب اسماً مختلفاً أو امسح البحث.',
                            icon: Icons.search_off_rounded,
                          )
                        : RefreshIndicator(
                            color: AppColors.primaryDark,
                            onRefresh: () =>
                                context.read<CategoriesCubit>().retry(),
                            child: GridView.builder(
                              clipBehavior: Clip.none,
                              physics: const AlwaysScrollableScrollPhysics(
                                parent: BouncingScrollPhysics(),
                              ),
                              padding: EdgeInsets.fromLTRB(
                                16.w,
                                8.h,
                                16.w,
                                24.h + MediaQuery.paddingOf(context).bottom,
                              ),
                              gridDelegate:
                                  SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: 3,
                                mainAxisSpacing: 16.h,
                                crossAxisSpacing: 12.w,
                                childAspectRatio: 0.74,
                              ),
                              itemCount: categories.length,
                              itemBuilder: (context, index) {
                                final category = categories[index];
                                return CategoryCard(
                                  category: category,
                                  expanded: true,
                                  onTap: () =>
                                      _openCategoryProducts(category),
                                );
                              },
                            ),
                          ),
                  ),
                ],
              );
          }
        },
      ),
    );
  }
}

class _ErrorBody extends StatelessWidget {
  final VoidCallback onRetry;

  const _ErrorBody({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 28.w),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72.w,
              height: 72.w,
              decoration: BoxDecoration(
                color: AppColors.error.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.cloud_off_rounded,
                size: 34.sp,
                color: AppColors.error,
              ),
            ),
            SizedBox(height: 16.h),
            Text(
              'تعذر تحميل التصنيفات',
              textAlign: TextAlign.center,
              style: AppTypography.titleMedium.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primaryDark,
              ),
            ),
            SizedBox(height: 6.h),
            Text(
              'تحقق من الاتصال ثم أعد المحاولة.',
              textAlign: TextAlign.center,
              style: AppTypography.bodyMedium,
            ),
            SizedBox(height: 20.h),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('إعادة المحاولة'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryDark,
                foregroundColor: Colors.white,
                padding:
                    EdgeInsets.symmetric(horizontal: 18.w, vertical: 12.h),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14.r),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyBody extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;

  const _EmptyBody({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 28.w),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72.w,
              height: 72.w,
              decoration: BoxDecoration(
                color: AppColors.surfaceMuted,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 34.sp, color: AppColors.goldDark),
            ),
            SizedBox(height: 16.h),
            Text(
              title,
              textAlign: TextAlign.center,
              style: AppTypography.titleMedium.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.primaryDark,
              ),
            ),
            SizedBox(height: 6.h),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: AppTypography.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}
