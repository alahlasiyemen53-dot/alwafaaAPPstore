import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/network/connectivity_cubit.dart';

/// Compact top banner shown when the device reports no network.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ConnectivityCubit, ConnectivityState>(
      buildWhen: (prev, next) => prev.isOffline != next.isOffline,
      builder: (context, state) {
        if (!state.isOffline) return const SizedBox.shrink();
        return Material(
          color: AppColors.primaryDark,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 8.h),
            child: Row(
              children: [
                Icon(Icons.wifi_off_rounded,
                    size: 16.w, color: AppColors.goldLight),
                SizedBox(width: 8.w),
                Expanded(
                  child: Text(
                    'لا يوجد اتصال بالإنترنت — بعض الميزات قد لا تعمل',
                    style: TextStyle(
                      fontSize: 11.sp,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textLight,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
