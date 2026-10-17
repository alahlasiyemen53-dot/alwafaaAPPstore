import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/constants/app_strings.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/core/utils/extensions.dart';
import 'package:store/core/widgets/brand_logo.dart';
import 'package:store/core/widgets/country_phone_field.dart';
import 'package:store/core/widgets/luxury_button.dart';
import 'package:store/core/widgets/luxury_otp_field.dart';
import 'package:store/core/widgets/luxury_text_field.dart';
import 'package:store/data/models/reference/yemen_reference_models.dart';
import 'package:store/data/repositories/reference_repository.dart';
import 'package:store/features/account/cubit/account_cubit.dart';

class AuthModalSheet extends StatefulWidget {
  const AuthModalSheet({super.key});

  static Future<bool?> show(BuildContext context) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => RepositoryProvider<ReferenceRepository>.value(
        value: context.read<ReferenceRepository>(),
        child: BlocProvider.value(
          value: context.read<AccountCubit>(),
          child: const AuthModalSheet(),
        ),
      ),
    );
  }

  @override
  State<AuthModalSheet> createState() => _AuthModalSheetState();
}

class _AuthModalSheetState extends State<AuthModalSheet> {
  final _phoneController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _codeController = TextEditingController();
  late final ReferenceRepository _referenceRepository;
  Timer? _timer;
  int _resendSeconds = 0;
  int _expirySeconds = 0;
  String? _localError;
  List<GovernorateModel> _governorates = const [];
  int? _selectedCountryId;
  String? _selectedCallingCode;
  int? _selectedGovernorateId;
  bool _didBindRepository = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didBindRepository) return;
    _didBindRepository = true;
    _referenceRepository = context.read<ReferenceRepository>();
  }

  Future<void> _loadGovernoratesForCountry(int countryId) async {
    try {
      final cached =
          await _referenceRepository.peekCachedGovernoratesByCountry(countryId);
      if (cached.isNotEmpty && mounted) {
        setState(() {
          _governorates = cached;
          _selectedGovernorateId = null;
        });
      }
      final governorates =
          await _referenceRepository.getGovernoratesByCountry(countryId);
      if (!mounted) return;
      setState(() {
        _governorates = governorates;
        _selectedGovernorateId = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _governorates = const [];
        _selectedGovernorateId = null;
      });
    }
  }

  void _onCountrySelected(CountryModel country) {
    setState(() {
      _selectedCountryId = country.id;
      _selectedCallingCode = country.callingCode;
      _selectedGovernorateId = null;
      _governorates = const [];
      _localError = null;
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _phoneController.dispose();
    _firstNameController.dispose();
    _lastNameController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  void _startCountdown(AccountState state) {
    _timer?.cancel();
    final challenge = state.challenge;
    if (challenge == null) return;

    void update() {
      if (!mounted) return;
      setState(() {
        _resendSeconds = challenge.canResendAt
            .difference(DateTime.now())
            .inSeconds
            .clamp(0, challenge.resendAfter);
        _expirySeconds = challenge.expiresAt
            .difference(DateTime.now())
            .inSeconds
            .clamp(0, challenge.expiresIn);
      });
    }

    update();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => update());
  }

  void _requestOtp(AccountCubit cubit) {
    final phone = _phoneController.text.trim();
    if (_selectedCountryId == null) {
      setState(() => _localError = 'اختر الدولة أولاً.');
      return;
    }
    if (phone.isEmpty) {
      setState(() => _localError = 'أدخل رقم الهاتف.');
      return;
    }
    setState(() => _localError = null);
    cubit.requestOtp(phone, countryId: _selectedCountryId);
  }

  void _verifyOtp(AccountCubit cubit) {
    final challenge = cubit.state.challenge;
    final isNew = challenge?.isNewCustomer ?? false;
    final firstName = _firstNameController.text.trim();
    final lastName = _lastNameController.text.trim();
    final name = [firstName, lastName]
        .where((part) => part.isNotEmpty)
        .join(' ');
    if (isNew && (firstName.isEmpty || lastName.isEmpty)) {
      setState(
        () => _localError = 'الاسم الأول واللقب إجباريان عند تسجيل حساب جديد.',
      );
      return;
    }
    if (isNew && _selectedGovernorateId == null) {
      setState(() => _localError = 'اختر المحافظة لإكمال التسجيل.');
      return;
    }
    final code = _codeController.text.trim();
    if (!RegExp(r'^\d{4,10}$').hasMatch(code)) {
      setState(() => _localError = 'أدخل رمز تحقق صحيحًا.');
      return;
    }
    setState(() => _localError = null);
    cubit.verifyOtp(
      code,
      name: name.isNotEmpty ? name : null,
      countryId: _selectedCountryId,
      governorateId: isNew ? _selectedGovernorateId : null,
    );
  }

  String? _failureMessage(ApiFailure? failure) {
    if (failure == null) return null;
    return switch (failure.code) {
      'OTP_INVALID' => 'رمز التحقق غير صحيح. حاول مرة أخرى.',
      'OTP_EXPIRED' => 'انتهت صلاحية الرمز. اطلب رمزًا جديدًا.',
      'OTP_ATTEMPTS_EXHAUSTED' =>
        'تم استنفاد محاولات التحقق. اطلب رمزًا جديدًا.',
      'OTP_CHALLENGE_INVALID' => 'طلب التحقق غير صالح. ابدأ من جديد.',
      'OTP_RATE_LIMITED' => 'طلبات كثيرة. انتظر قبل إعادة المحاولة.',
      'CUSTOMER_ACCESS_RESTRICTED' =>
        'لا يمكن الدخول إلى هذا الحساب حاليًا. تواصل مع الدعم.',
      _ => failure.message,
    };
  }

  @override
  Widget build(BuildContext context) {
    final bottomSafeArea = MediaQuery.paddingOf(context).bottom;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return BlocConsumer<AccountCubit, AccountState>(
      listenWhen: (previous, current) =>
          previous.challenge != current.challenge ||
          previous.status != current.status,
      listener: (context, state) {
        if (state.challenge != null) _startCountdown(state);
        if (state.challenge?.isNewCustomer == true &&
            _selectedCountryId != null &&
            _governorates.isEmpty) {
          _loadGovernoratesForCountry(_selectedCountryId!);
        }
        if (state.status == AuthStatus.authenticated) {
          // Drop focus/selection overlays before the sheet disposes its
          // TextEditingControllers (avoids "used after disposed" crash).
          FocusManager.instance.primaryFocus?.unfocus();
          final messenger = ScaffoldMessenger.of(context);
          Navigator.pop(context, true);
          AppSnackBar.show(
            messenger,
            'تم تسجيل الدخول بنجاح.',
            type: AppSnackBarType.success,
          );
        }
      },
      builder: (context, state) {
        final cubit = context.read<AccountCubit>();
        final isOtpStep = state.challenge != null;
        final error = _localError ??
            (isOtpStep ? state.codeError : state.phoneError) ??
            _failureMessage(state.failure);

        return Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(context).height * 0.9,
          ),
          padding: EdgeInsets.only(
            left: 20.w,
            right: 20.w,
            top: 16.h,
            bottom:
                bottomInset + (bottomInset > 0 ? 28.h : bottomSafeArea + 28.h),
          ),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 44.w,
                  height: 4.h,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(4.r),
                  ),
                ),
                SizedBox(height: 16.h),
                if (isOtpStep) ...[
                  Container(
                    width: 52.w,
                    height: 52.w,
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.borderLight),
                    ),
                    alignment: Alignment.center,
                    child: Icon(
                      Icons.shield_outlined,
                      color: AppColors.primaryDark,
                      size: 26.w,
                    ),
                  ),
                  SizedBox(height: 12.h),
                  Text(
                    'رمز التحقق',
                    style: GoogleFonts.alexandria(
                      fontSize: 16.sp,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                  SizedBox(height: 6.h),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        'تم إرسال الرمز إلى ',
                        style: GoogleFonts.alexandria(
                          fontSize: 11.5.sp,
                          color: AppColors.textMuted,
                        ),
                      ),
                      Text(
                        state.challenge!.phone,
                        textDirection: TextDirection.ltr,
                        style: GoogleFonts.alexandria(
                          fontSize: 12.sp,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryDark,
                        ),
                      ),
                      SizedBox(width: 8.w),
                      InkWell(
                        onTap: state.isBusy
                            ? null
                              : () {
                                FocusManager.instance.primaryFocus?.unfocus();
                                _timer?.cancel();
                                _codeController.clear();
                                _firstNameController.clear();
                                _lastNameController.clear();
                                setState(() {
                                  _governorates = const [];
                                  _selectedGovernorateId = null;
                                });
                                cubit.resetChallenge();
                              },
                        borderRadius: BorderRadius.circular(6.r),
                        child: Padding(
                          padding: EdgeInsets.symmetric(
                            horizontal: 6.w,
                            vertical: 2.h,
                          ),
                          child: Text(
                            'تغيير',
                            style: GoogleFonts.alexandria(
                              fontSize: 11.sp,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primaryDark,
                              decoration: TextDecoration.underline,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ] else ...[
                  const BrandLogo(variant: BrandLogoVariant.full, size: 40),
                  SizedBox(height: 12.h),
                  Text(
                    AppStrings.loginTitle,
                    style: GoogleFonts.alexandria(
                      fontSize: 16.sp,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    ),
                  ),
                  SizedBox(height: 4.h),
                  Text(
                    'أدخل رقم هاتفك للمتابعة عبر رمز تحقق آمن.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.alexandria(
                      fontSize: 11.5.sp,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
                SizedBox(height: 16.h),
                if (!isOtpStep) ...[
                  CountryPhoneField(
                    repository: _referenceRepository,
                    selectedCountryId: _selectedCountryId,
                    callingCode: _selectedCallingCode,
                    phoneController: _phoneController,
                    onCountrySelected: _onCountrySelected,
                    enabled: !state.isBusy,
                  ),
                ] else ...[
                  if (state.challenge?.isNewCustomer == true) ...[
                    Padding(
                      // Keep field shadows/borders from being clipped by scroll.
                      padding: EdgeInsets.symmetric(vertical: 1.h),
                      child: Row(
                        children: [
                          Expanded(
                            child: LuxuryTextField(
                              controller: _firstNameController,
                              hintText: 'الاسم الأول',
                            ),
                          ),
                          SizedBox(width: 10.w),
                          Expanded(
                            child: LuxuryTextField(
                              controller: _lastNameController,
                              hintText: 'اللقب',
                            ),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 10.h),
                    Builder(
                      builder: (context) {
                        final uniqueAuthGovs = <int, GovernorateModel>{};
                        for (final g in _governorates) {
                          uniqueAuthGovs[g.id] = g;
                        }
                        final safeAuthGovId = (_selectedGovernorateId != null &&
                                uniqueAuthGovs.containsKey(_selectedGovernorateId))
                            ? _selectedGovernorateId
                            : null;

                        return DropdownButtonFormField<int>(
                          key: ValueKey('auth-gov-$safeAuthGovId-${uniqueAuthGovs.length}'),
                          initialValue: safeAuthGovId,
                          decoration: const InputDecoration(
                            labelText: 'المحافظة',
                            border: OutlineInputBorder(),
                          ),
                          items: uniqueAuthGovs.values
                              .map(
                                (g) => DropdownMenuItem(
                                  value: g.id,
                                  child: Text(g.nameAr),
                                ),
                              )
                              .toList(),
                          onChanged: uniqueAuthGovs.isEmpty
                              ? null
                              : (value) =>
                                  setState(() => _selectedGovernorateId = value),
                        );
                      },
                    ),
                    SizedBox(height: 12.h),
                  ],
                  LuxuryOtpField(
                    controller: _codeController,
                    length: 6,
                    hasError: error != null,
                    enabled: !state.isBusy,
                    onCompleted: (_) => _verifyOtp(cubit),
                    onChanged: (_) {
                      if (_localError != null) {
                        setState(() => _localError = null);
                      }
                    },
                  ),
                  SizedBox(height: 12.h),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (_resendSeconds > 0) ...[
                        Icon(
                          Icons.timer_outlined,
                          size: 14.w,
                          color: AppColors.textMuted,
                        ),
                        SizedBox(width: 4.w),
                        Text(
                          'إعادة الإرسال بعد $_resendSeconds ثانية',
                          style: GoogleFonts.alexandria(
                            fontSize: 11.sp,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ] else ...[
                        TextButton.icon(
                          onPressed: state.isBusy ? null : () => cubit.resendOtp(),
                          icon: Icon(
                            Icons.refresh_rounded,
                            size: 16.w,
                            color: AppColors.primaryDark,
                          ),
                          label: Text(
                            'إعادة إرسال الرمز',
                            style: GoogleFonts.alexandria(
                              fontSize: 11.5.sp,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (_expirySeconds > 0) ...[
                    SizedBox(height: 4.h),
                    Text(
                      'تنتهي صلاحية الرمز خلال $_expirySeconds ثانية',
                      style: GoogleFonts.alexandria(
                        fontSize: 10.sp,
                        color: AppColors.textMuted.withValues(alpha: 0.8),
                      ),
                    ),
                  ],
                ],
                if (error != null) ...[
                  SizedBox(height: 8.h),
                  Container(
                    padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
                    decoration: BoxDecoration(
                      color: AppColors.error.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(10.r),
                      border: Border.all(
                        color: AppColors.error.withValues(alpha: 0.3),
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.info_outline_rounded,
                            color: AppColors.error, size: 16.w),
                        SizedBox(width: 8.w),
                        Expanded(
                          child: Text(
                            error,
                            style: GoogleFonts.alexandria(
                              fontSize: 11.sp,
                              color: AppColors.error,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                SizedBox(height: 18.h),
                LuxuryButton(
                  text: isOtpStep ? 'تحقق ودخول' : 'إرسال رمز التحقق',
                  width: double.infinity,
                  isLoading: state.isBusy,
                  onPressed: state.isBusy
                      ? null
                      : () =>
                          isOtpStep ? _verifyOtp(cubit) : _requestOtp(cubit),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
