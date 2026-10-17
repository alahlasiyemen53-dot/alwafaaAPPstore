import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/widgets/app_country_picker.dart';
import 'package:store/data/models/reference/yemen_reference_models.dart';
import 'package:store/data/repositories/reference_repository.dart';

/// Country picker + calling-code prefix + national phone input.
class CountryPhoneField extends StatelessWidget {
  final ReferenceRepository repository;
  final int? selectedCountryId;
  final String? callingCode;
  final ValueChanged<CountryModel> onCountrySelected;
  final TextEditingController phoneController;
  final String phoneHint;
  final bool enabled;

  const CountryPhoneField({
    super.key,
    required this.repository,
    required this.onCountrySelected,
    required this.phoneController,
    this.selectedCountryId,
    this.callingCode,
    this.phoneHint = 'رقم الهاتف',
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    final dial = (callingCode != null && callingCode!.trim().isNotEmpty)
        ? callingCode!.trim()
        : '—';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppCountryPicker(
          repository: repository,
          selectedCountryId: selectedCountryId,
          onSelected: onCountrySelected,
          enabled: enabled,
        ),
        SizedBox(height: 12.h),
        Container(
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(16.r),
            border: Border.all(color: AppColors.border),
          ),
          child: Directionality(
            textDirection: TextDirection.ltr,
            child: Row(
              children: [
                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 14.w),
                  child: Text(
                    dial,
                    style: GoogleFonts.alexandria(
                      fontSize: 12.sp,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
                Container(width: 1.2, height: 28.h, color: AppColors.borderLight),
                Expanded(
                  child: TextField(
                    controller: phoneController,
                    enabled: enabled,
                    keyboardType: TextInputType.phone,
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9+\s-]')),
                    ],
                    textDirection: TextDirection.ltr,
                    textAlign: TextAlign.left,
                    style: GoogleFonts.alexandria(
                      fontSize: 12.sp,
                      fontWeight: FontWeight.w500,
                      color: AppColors.textPrimary,
                    ),
                    decoration: InputDecoration(
                      hintText: phoneHint,
                      hintTextDirection: TextDirection.rtl,
                      hintStyle: GoogleFonts.alexandria(
                        fontSize: 11.sp,
                        color: AppColors.textMuted,
                      ),
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 14.w,
                        vertical: 14.h,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
