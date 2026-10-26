import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/data/models/product_model.dart';
import 'package:store/features/app_shell/cubit/app_shell_cubit.dart';
import 'package:store/features/discovery/cubit/recently_viewed_cubit.dart';
import 'package:store/features/home/presentation/widgets/home_product_rail.dart';

class RecentlyViewedRail extends StatelessWidget {
  final String title;
  final List<ProductModel>? products;

  const RecentlyViewedRail({
    super.key,
    this.title = 'شاهدتها مؤخراً',
    this.products,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<RecentlyViewedCubit, RecentlyViewedState>(
      builder: (context, state) {
        final list = products ?? state.products;
        if (list.isEmpty) return const SizedBox.shrink();

        return Padding(
          padding: EdgeInsets.only(top: 8.h, bottom: 8.h),
          child: HomeProductRail(
            title: title,
            products: list,
            onProductTap: (product) {
              context.read<AppShellCubit>().openProductDetails(product);
            },
          ),
        );
      },
    );
  }
}
