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
        final tier = loyalty.currentTier?.nameAr ?? 'عضو الوفاء';
        final points = loyalty.availablePoints;
        final loading = state.status == LoyaltyStatus.loading && points == 0;

        return Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(18.r),
            onTap: () => LoyaltySheet.show(context),
            child: Ink(
              padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 14.h),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(18.r),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  Container(
                    width: 44.w,
                    height: 44.w,
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(14.r),
                    ),
                    child: Icon(
                      Icons.workspace_premium_rounded,
                      color: AppColors.goldDark,
                      size: 22.w,
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
                            color: AppColors.textMuted,
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
                            color: AppColors.primaryDark,
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
                          color: AppColors.burgundy,
                          height: 1.1,
                        ),
                      ),
                      Text(
                        'نقطة',
                        style: TextStyle(
                          fontSize: 10.sp,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                  SizedBox(width: 2.w),
                  Icon(
                    Icons.chevron_left_rounded,
                    color: AppColors.textMuted,
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
