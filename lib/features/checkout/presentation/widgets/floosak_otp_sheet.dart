import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/luxury_button.dart';
import '../../../../core/widgets/luxury_otp_field.dart';
import '../../cubit/checkout_cubit.dart';

/// Step2 OTP confirmation after a pending payment attempt was created.
class FloosakOtpSheet extends StatefulWidget {
  const FloosakOtpSheet({super.key});

  static Future<bool?> show(BuildContext context) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: false,
      isDismissible: false,
      enableDrag: false,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider.value(
        value: context.read<CheckoutCubit>(),
        child: const FloosakOtpSheet(),
      ),
    );
  }

  @override
  State<FloosakOtpSheet> createState() => _FloosakOtpSheetState();
}

class _FloosakOtpSheetState extends State<FloosakOtpSheet> {
  final TextEditingController _otpController = TextEditingController();
  String? _localError;

  @override
  void dispose() {
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    final otp = _otpController.text.trim();
    if (otp.isEmpty) {
      setState(() => _localError = 'أدخل رمز التحقق.');
      return;
    }
    setState(() => _localError = null);
    final cubit = context.read<CheckoutCubit>();
    final order = await cubit.confirmPaymentOtp(otp);
    if (!mounted) return;
    if (order != null) {
      Navigator.of(context).pop(true);
      return;
    }
    final message = cubit.state.errorMessage;
    if (message != null && message.isNotEmpty) {
      setState(() => _localError = message);
    }
  }

  void _cancel() {
    context.read<CheckoutCubit>().cancelPendingPayment();
    Navigator.of(context).pop(false);
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<CheckoutCubit>().state;
    final attempt = state.pendingPaymentAttempt;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    final isBusy = state.isSubmitting;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.9,
        ),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24.r)),
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            child: Padding(
              padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
              child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40.w,
                    height: 4.h,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(4.r),
                    ),
                  ),
                ),
                SizedBox(height: 12.h),
                Text(
                  'تأكيد الدفع',
                  style: AppTypography.titleMedium.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                SizedBox(height: 6.h),
                Text(
                  'أدخل رمز التحقق. سيتم استخدام رقم جوال حسابك المسجّل تلقائياً.',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                if (attempt?.customerPhone != null) ...[
                  SizedBox(height: 4.h),
                  Text(
                    'الجوال: ${attempt!.customerPhone}',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
                SizedBox(height: 14.h),
                LuxuryOtpField(
                  controller: _otpController,
                  length: 6,
                  hasError: _localError != null,
                  enabled: !isBusy,
                  onCompleted: (_) => _confirm(),
                  onChanged: (_) {
                    if (_localError != null) {
                      setState(() => _localError = null);
                    }
                  },
                ),
                if (_localError != null) ...[
                  SizedBox(height: 8.h),
                  Text(
                    _localError!,
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.error,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                SizedBox(height: 16.h),
                LuxuryButton(
                  text: isBusy ? 'جاري التأكيد...' : 'تأكيد وإتمام الطلب',
                  width: double.infinity,
                  isLoading: isBusy,
                  onPressed: isBusy ? null : _confirm,
                ),
                SizedBox(height: 8.h),
                TextButton(
                  onPressed: isBusy ? null : _cancel,
                  child: Text(
                    'إلغاء وتغيير طريقة الدفع',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.primaryDark,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
  }
}
