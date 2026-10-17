import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/luxury_button.dart';
import '../../../../core/widgets/luxury_text_field.dart';
import '../../cubit/checkout_cubit.dart';

/// Collects Jaib purchase code only; customer phone is taken from auth on the backend.
class JaibPaymentSheet extends StatefulWidget {
  const JaibPaymentSheet({super.key});

  static Future<bool?> show(BuildContext context) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: false,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider.value(
        value: context.read<CheckoutCubit>(),
        child: const JaibPaymentSheet(),
      ),
    );
  }

  @override
  State<JaibPaymentSheet> createState() => _JaibPaymentSheetState();
}

class _JaibPaymentSheetState extends State<JaibPaymentSheet> {
  late final TextEditingController _codeController;
  String? _localError;
  bool _didInit = false;

  @override
  void initState() {
    super.initState();
    _codeController = TextEditingController();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didInit) return;
    _didInit = true;
    _codeController.text = context.read<CheckoutCubit>().state.jaibCode;
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  void _save() {
    final code = _codeController.text.trim();
    if (code.isEmpty) {
      setState(() => _localError = 'أدخل رمز الشراء من تطبيق جيب.');
      return;
    }
    context.read<CheckoutCubit>().saveJaibPurchaseCode(code);
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final method =
        context.watch<CheckoutCubit>().state.selectedPaymentMethodModel;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

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
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        method?.nameAr ?? 'دفع جيب',
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                Text(
                  'سيتم استخدام رقم جوال حسابك المسجّل لدى لمّة تلقائياً.',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                if ((method?.instructionsAr ?? '').isNotEmpty) ...[
                  SizedBox(height: 8.h),
                  Text(
                    method!.instructionsAr!,
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                SizedBox(height: 12.h),
                Text(
                  'رمز الشراء',
                  style: AppTypography.bodySmall.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                SizedBox(height: 6.h),
                LuxuryTextField(
                  controller: _codeController,
                  hintText: 'أدخل رمز الشراء من تطبيق جيب',
                  keyboardType: TextInputType.number,
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
                  text: 'حفظ ومتابعة',
                  width: double.infinity,
                  onPressed: _save,
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
