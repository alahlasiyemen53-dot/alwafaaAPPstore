import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/skeletons/screen_skeletons.dart';
import '../../cubit/loyalty_cubit.dart';

class LoyaltySheet extends StatefulWidget {
  const LoyaltySheet({super.key});

  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider.value(
        value: context.read<LoyaltyCubit>()..fetchLoyalty(),
        child: const LoyaltySheet(),
      ),
    );
  }

  @override
  State<LoyaltySheet> createState() => _LoyaltySheetState();
}

class _LoyaltySheetState extends State<LoyaltySheet> {
  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.85,
      ),
      padding: EdgeInsets.only(
        left: 20.w,
        right: 20.w,
        top: 16.h,
        bottom: 24.h + MediaQuery.of(context).padding.bottom,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
      ),
      child: BlocBuilder<LoyaltyCubit, LoyaltyState>(
        builder: (context, state) {
          final loyalty = state.loyalty;

          return Column(
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
                      'برنامج الولاء والـ VIP',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.titleLarge.copyWith(
                        color: AppColors.primaryDark,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              SizedBox(height: 12.h),

              if (state.status == LoyaltyStatus.loading && loyalty.availablePoints == 0)
                const Expanded(child: LoyaltySheetSkeleton())
              else
                Expanded(
                  child: ListView(
                    children: [
                      // VIP Tier Card
                      Container(
                        padding: EdgeInsets.all(18.w),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [AppColors.primaryDark, AppColors.primaryContainer],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(20.r),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.15),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.workspace_premium_rounded,
                                    color: AppColors.gold, size: 24.w),
                                SizedBox(width: 8.w),
                                Expanded(
                                  child: Text(
                                    loyalty.currentTier?.nameAr ??
                                        'عضو لِمّة المميز',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 15.sp,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                                SizedBox(width: 8.w),
                                Container(
                                  padding: EdgeInsets.symmetric(
                                      horizontal: 10.w, vertical: 4.h),
                                  decoration: BoxDecoration(
                                    color: AppColors.gold.withValues(alpha: 0.2),
                                    borderRadius: BorderRadius.circular(12.r),
                                    border: Border.all(color: AppColors.gold),
                                  ),
                                  child: Text(
                                    'VIP',
                                    style: TextStyle(
                                      color: AppColors.gold,
                                      fontSize: 11.sp,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            SizedBox(height: 16.h),
                            Text(
                              'الرصيد المتاح',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.7),
                                fontSize: 12.sp,
                              ),
                            ),
                            SizedBox(height: 4.h),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.baseline,
                              textBaseline: TextBaseline.alphabetic,
                              children: [
                                Text(
                                  '${loyalty.availablePoints}',
                                  style: TextStyle(
                                    color: AppColors.gold,
                                    fontSize: 32.sp,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                SizedBox(width: 6.w),
                                Text(
                                  'نقطة ولاء',
                                  style: TextStyle(
                                    color: Colors.white70,
                                    fontSize: 13.sp,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      SizedBox(height: 14.h),

                      // Stats Row
                      Row(
                        children: [
                          Expanded(
                            child: Container(
                              padding: EdgeInsets.all(12.w),
                              decoration: BoxDecoration(
                                color: AppColors.surfaceSoft,
                                borderRadius: BorderRadius.circular(14.r),
                                border: Border.all(color: AppColors.border, width: 0.8),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('إجمالي المكتسب',
                                      style: TextStyle(
                                          fontSize: 11.sp, color: AppColors.textMuted)),
                                  SizedBox(height: 4.h),
                                  Text(
                                    '${loyalty.lifetimeEarned} نقطة',
                                    style: TextStyle(
                                        fontSize: 14.sp,
                                        fontWeight: FontWeight.bold,
                                        color: AppColors.primaryDark),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          SizedBox(width: 10.w),
                          Expanded(
                            child: Container(
                              padding: EdgeInsets.all(12.w),
                              decoration: BoxDecoration(
                                color: AppColors.surfaceSoft,
                                borderRadius: BorderRadius.circular(14.r),
                                border: Border.all(color: AppColors.border, width: 0.8),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('إجمالي المستبدل',
                                      style: TextStyle(
                                          fontSize: 11.sp, color: AppColors.textMuted)),
                                  SizedBox(height: 4.h),
                                  Text(
                                    '${loyalty.lifetimeRedeemed} نقطة',
                                    style: TextStyle(
                                        fontSize: 14.sp,
                                        fontWeight: FontWeight.bold,
                                        color: AppColors.primaryDark),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                      SizedBox(height: 14.h),

                      // Invariant Disclaimer Note (Sprint 37 Invariant: Points != Money)
                      Container(
                        padding: EdgeInsets.all(12.w),
                        decoration: BoxDecoration(
                          color: AppColors.gold.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(14.r),
                          border: Border.all(color: AppColors.gold.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.info_outline_rounded,
                                size: 18.w, color: AppColors.goldDark),
                            SizedBox(width: 8.w),
                            Expanded(
                              child: Text(
                                'نقاط الولاء مخصصة لمكافآت التقدير وترقيات العضوية، وليست رصيداً نقدياً أو محفظة مالية قابلة للسحب.',
                                style: TextStyle(
                                  fontSize: 11.sp,
                                  color: AppColors.goldDark,
                                  height: 1.4,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      SizedBox(height: 16.h),

                      // History Header
                      Text(
                        'سجل العمليات الأخيرة',
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.primaryDark,
                        ),
                      ),
                      SizedBox(height: 10.h),

                      if (loyalty.recentEntries.isEmpty)
                        Padding(
                          padding: EdgeInsets.symmetric(vertical: 24.h),
                          child: Center(
                            child: Text(
                              'لا توجد عمليات ولاء مسجلة حتى الآن',
                              style: TextStyle(
                                fontSize: 12.sp,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ),
                        )
                      else
                        ...loyalty.recentEntries.map((entry) {
                          final isPositive = entry.points > 0;
                          return Container(
                            margin: EdgeInsets.only(bottom: 8.h),
                            padding: EdgeInsets.symmetric(
                                horizontal: 14.w, vertical: 10.h),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12.r),
                              border: Border.all(
                                  color: AppColors.borderLight, width: 0.8),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 34.w,
                                  height: 34.w,
                                  decoration: BoxDecoration(
                                    color: isPositive
                                        ? AppColors.success.withValues(alpha: 0.1)
                                        : AppColors.warning.withValues(alpha: 0.1),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(
                                    isPositive
                                        ? Icons.arrow_downward_rounded
                                        : Icons.arrow_upward_rounded,
                                    color: isPositive ? AppColors.success : AppColors.warning,
                                    size: 16.w,
                                  ),
                                ),
                                SizedBox(width: 12.w),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        entry.description ?? 'حركة نقاط ولاء',
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          fontSize: 12.sp,
                                          fontWeight: FontWeight.w600,
                                          color: AppColors.textPrimary,
                                        ),
                                      ),
                                      if (entry.occurredAt != null)
                                        Text(
                                          '${entry.occurredAt!.year}-${entry.occurredAt!.month.toString().padLeft(2, '0')}-${entry.occurredAt!.day.toString().padLeft(2, '0')}',
                                          style: TextStyle(
                                            fontSize: 10.5.sp,
                                            color: AppColors.textMuted,
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                                Text(
                                  '${isPositive ? '+' : ''}${entry.points}',
                                  style: TextStyle(
                                    fontSize: 13.5.sp,
                                    fontWeight: FontWeight.bold,
                                    color: isPositive ? AppColors.success : AppColors.warning,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
