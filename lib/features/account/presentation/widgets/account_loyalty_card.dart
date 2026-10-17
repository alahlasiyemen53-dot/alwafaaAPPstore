import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/features/account/cubit/loyalty_cubit.dart';
import 'package:store/features/account/presentation/widgets/loyalty_sheet.dart';

/// Compact loyalty brand card shown on the account screen.
class AccountLoyaltyCard extends StatelessWidget {
  const AccountLoyaltyCard({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<LoyaltyCubit, LoyaltyState>(
      builder: (context, state) {
        final loyalty = state.loyalty;
        final tier = loyalty.currentTier?.nameAr ?? 'عضو لِمّة';
        final points = loyalty.availablePoints;
        final loading = state.status == LoyaltyStatus.loading && points == 0;

        return Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(20.r),
            onTap: () => LoyaltySheet.show(context),
            child: Ink(
              padding: EdgeInsets.all(16.w),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primaryDark, AppColors.primaryContainer],
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                ),
                borderRadius: BorderRadius.circular(20.r),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.35),
                  width: 1,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primaryDark.withValues(alpha: 0.18),
                    blurRadius: 14,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    width: 44.w,
                    height: 44.w,
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(14.r),
                    ),
                    child: Icon(
                      Icons.workspace_premium_rounded,
                      color: AppColors.gold,
                      size: 24.w,
                    ),
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'نقاط الولاء',
                          style: TextStyle(
                            fontSize: 11.sp,
                            fontWeight: FontWeight.w600,
                            color: AppColors.goldLight,
                          ),
                        ),
                        SizedBox(height: 2.h),
                        Text(
                          loading ? 'جارٍ التحميل...' : tier,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 14.sp,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textLight,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        loading ? '—' : '$points',
                        style: TextStyle(
                          fontSize: 20.sp,
                          fontWeight: FontWeight.w900,
                          color: AppColors.gold,
                          height: 1.1,
                        ),
                      ),
                      Text(
                        'نقطة',
                        style: TextStyle(
                          fontSize: 10.sp,
                          color: AppColors.goldLight,
                        ),
                      ),
                    ],
                  ),
                  SizedBox(width: 4.w),
                  Icon(
                    Icons.chevron_left_rounded,
                    color: AppColors.goldLight,
                    size: 22.w,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
