import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/skeletons/screen_skeletons.dart';
import '../../../../data/models/account/customer_address_model.dart';
import '../../../../data/models/reference/yemen_reference_models.dart';
import '../../cubit/account_cubit.dart';
import '../../cubit/address_cubit.dart';
import 'address_location_picker.dart';

class AddressFormSheet extends StatefulWidget {
  final CustomerAddressModel? existingAddress;

  const AddressFormSheet({super.key, this.existingAddress});

  static Future<bool?> show(BuildContext context,
      {CustomerAddressModel? existingAddress}) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: false,
      backgroundColor: Colors.transparent,
      builder: (_) => MultiBlocProvider(
        providers: [
          BlocProvider.value(value: context.read<AddressCubit>()),
          BlocProvider.value(value: context.read<AccountCubit>()),
        ],
        child: AddressFormSheet(existingAddress: existingAddress),
      ),
    );
  }

  @override
  State<AddressFormSheet> createState() => _AddressFormSheetState();
}

class _AddressFormSheetState extends State<AddressFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _labelController;
  late final TextEditingController _contactNameController;
  late final TextEditingController _contactPhoneController;
  late final TextEditingController _addressController;
  bool _isDefault = false;
  String? _localError;
  bool _didInitCubit = false;

  @override
  void initState() {
    super.initState();
    final addr = widget.existingAddress;
    _labelController = TextEditingController(text: addr?.label ?? '');
    _contactNameController = TextEditingController(
      text: addr?.contactName ?? '',
    );
    _contactPhoneController = TextEditingController(
      text: addr?.contactPhone ?? '',
    );
    _addressController = TextEditingController(
      text: (addr?.formattedAddress?.trim().isNotEmpty == true)
          ? addr!.formattedAddress!
          : (addr?.street ?? ''),
    );
    _isDefault = addr?.isDefault ?? false;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didInitCubit) return;
    _didInitCubit = true;

    final cubit = context.read<AddressCubit>();
    final addr = widget.existingAddress;
    final user = context.read<AccountCubit>().state.user;
    if (_contactNameController.text.trim().isEmpty && user?.name != null) {
      _contactNameController.text = user!.name;
    }
    if (_contactPhoneController.text.trim().isEmpty && user?.phone != null) {
      _contactPhoneController.text = user!.phone;
    }

    final profileCountryId = user?.countryId;
    final hasExistingPin = addr?.latitude != null && addr?.longitude != null;

    // Load reference data then geography sequentially to avoid a frame where
    // selectedGovernorateId is set but Dropdown items don't contain it yet.
    () async {
      await cubit.loadReferenceData(
        preferredCountryId: profileCountryId,
        includeCountryId: addr?.countryId,
      );
      if (!mounted) return;
      if (addr != null) {
        await cubit.setGeographyFromAddress(addr);
      } else {
        cubit.resetFormSelection(preferredCountryId: profileCountryId);
      }
    }();

    // Prompt location after sheet presentation transition completes
    // to avoid iOS modal presentation conflict / crash.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      Future.delayed(const Duration(milliseconds: 500), () {
        if (!mounted) return;
        cubit.promptLocationAccessOnFormOpen(moveToCurrent: !hasExistingPin);
      });
    });
  }

  @override
  void dispose() {
    _labelController.dispose();
    _contactNameController.dispose();
    _contactPhoneController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  void _setLocalError(String message) {
    setState(() => _localError = message.trim());
  }

  Future<void> _submit() async {
    final cubit = context.read<AddressCubit>();
    setState(() => _localError = null);

    if (!_formKey.currentState!.validate()) {
      _setLocalError('يرجى تعبئة الحقول المطلوبة');
      return;
    }

    final contactName = _contactNameController.text.trim();
    if (contactName.isEmpty) {
      _setLocalError('يرجى إدخال اسم المستلم');
      return;
    }

    final contactPhone = _contactPhoneController.text.trim();
    if (contactPhone.isEmpty) {
      _setLocalError('يرجى إدخال رقم هاتف المستلم');
      return;
    }

    final addressText = _addressController.text.trim();
    if (addressText.isEmpty) {
      _setLocalError('يرجى كتابة العنوان بوضوح');
      return;
    }

    if (cubit.state.selectedCountryId == null) {
      _setLocalError('يرجى اختيار الدولة');
      return;
    }

    if (cubit.state.selectedGovernorateId == null) {
      _setLocalError('يرجى اختيار المحافظة');
      return;
    }

    if (cubit.state.selectedLatitude == null ||
        cubit.state.selectedLongitude == null) {
      _setLocalError('يرجى تحديد الموقع على خريطة Google');
      return;
    }

    final payload = <String, dynamic>{
      'label': _labelController.text.trim(),
      'contact_name': contactName,
      'contact_phone': contactPhone,
      'country_id': cubit.state.selectedCountryId,
      'governorate_id': cubit.state.selectedGovernorateId,
      'formatted_address': addressText,
      'street': addressText,
      'latitude': cubit.state.selectedLatitude,
      'longitude': cubit.state.selectedLongitude,
      'is_default': _isDefault,
    };

    final bool success;
    if (widget.existingAddress != null) {
      success = await cubit.updateAddress(widget.existingAddress!.id, payload);
    } else {
      success = await cubit.createAddress(payload);
    }

    if (success && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isEdit = widget.existingAddress != null;
    final media = MediaQuery.of(context);
    final keyboardInset = media.viewInsets.bottom;
    // Read insets from the platform view — nested MediaQuery in sheets
    // can report padding.bottom / viewPadding.bottom as 0.
    final systemBottom =
        MediaQueryData.fromView(View.of(context)).viewPadding.bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: keyboardInset),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: media.size.height * 0.9,
        ),
        padding: EdgeInsets.fromLTRB(
          20.w,
          16.h,
          20.w,
          16.h + (keyboardInset > 0 ? 0.0 : systemBottom),
        ),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28.r)),
        ),
        child: BlocBuilder<AddressCubit, AddressState>(
          builder: (context, state) {
            final bannerError = _localError ?? state.errorMessage;

            return Form(
              key: _formKey,
              child: Column(
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
                SizedBox(height: 14.h),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        isEdit ? 'تعديل العنوان' : 'إضافة عنوان جديد',
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
                SizedBox(height: 4.h),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: Text(
                    'اكتب العنوان بوضوح وحدد الموقع على الخريطة فقط',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 11.5.sp,
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
                SizedBox(height: 8.h),
                if (bannerError != null && bannerError.trim().isNotEmpty)
                  _OneLineErrorBanner(message: bannerError),
                Expanded(
                  child: ListView(
                    children: [
                      _buildQuickLabelChips(),
                      SizedBox(height: 10.h),
                      _buildTextField(
                        controller: _labelController,
                        label: 'تسمية العنوان (مثل: المنزل، العمل) *',
                        icon: Icons.bookmark_border_rounded,
                        required: true,
                        errorText: state.fieldErrors['label'],
                      ),
                      SizedBox(height: 12.h),
                      _buildTextField(
                        controller: _contactNameController,
                        label: 'اسم المستلم *',
                        icon: Icons.person_outline_rounded,
                        required: true,
                        errorText: state.fieldErrors['contact_name'],
                      ),
                      SizedBox(height: 12.h),
                      _buildTextField(
                        controller: _contactPhoneController,
                        label: 'رقم هاتف المستلم *',
                        icon: Icons.phone_outlined,
                        required: true,
                        keyboardType: TextInputType.phone,
                        textDirection: TextDirection.ltr,
                        errorText: state.fieldErrors['contact_phone'],
                      ),
                      SizedBox(height: 12.h),
                      _buildCountryDropdown(state),
                      SizedBox(height: 12.h),
                      _buildGovernorateDropdown(state),
                      SizedBox(height: 12.h),
                      _buildTextField(
                        controller: _addressController,
                        label: 'العنوان بالتفصيل (الشارع، الحي، أقرب معلم) *',
                        icon: Icons.home_work_outlined,
                        maxLines: 2,
                        required: true,
                        errorText: state.fieldErrors['formatted_address'] ??
                            state.fieldErrors['street'],
                      ),
                      SizedBox(height: 16.h),
                      AddressLocationPicker(state: state),
                      if (state.fieldErrors['latitude'] != null ||
                          state.fieldErrors['longitude'] != null) ...[
                        SizedBox(height: 6.h),
                        _OneLineFieldError(
                          message: state.fieldErrors['latitude'] ??
                              state.fieldErrors['longitude']!,
                        ),
                      ],
                      SizedBox(height: 8.h),
                      Material(
                        color: Colors.transparent,
                        child: SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            'تعيين كعنوان رئيسي افتراضي',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                                fontSize: 13.sp, fontWeight: FontWeight.w600),
                          ),
                          value: _isDefault,
                          activeThumbColor: AppColors.goldDark,
                          onChanged: (val) => setState(() => _isDefault = val),
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(
                  width: double.infinity,
                  height: 48.h,
                  child: ElevatedButton(
                    onPressed: state.isSubmitting ? null : _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primaryDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14.r),
                      ),
                    ),
                    child: state.isSubmitting
                        ? const ActionButtonSkeleton()
                        : Text(
                            isEdit ? 'حفظ التعديلات' : 'إضافة العنوان',
                            style: TextStyle(
                                fontSize: 14.sp, fontWeight: FontWeight.bold),
                          ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
      ),
    );
  }

  Widget _buildQuickLabelChips() {
    const options = ['المنزل 🏠', 'العمل 🏢', 'شقة 🚪', 'أخرى 📍'];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: options.map((opt) {
          final cleanText = opt.split(' ').first;
          final isSelected = _labelController.text.trim() == cleanText;
          return Padding(
            padding: EdgeInsetsDirectional.only(end: 8.w),
            child: ChoiceChip(
              label: Text(opt, style: TextStyle(fontSize: 11.5.sp)),
              selected: isSelected,
              selectedColor: AppColors.goldDark.withValues(alpha: 0.15),
              labelStyle: TextStyle(
                color: isSelected ? AppColors.goldDark : AppColors.textPrimary,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              ),
              side: BorderSide(
                color: isSelected ? AppColors.goldDark : AppColors.border,
                width: isSelected ? 1.2 : 0.8,
              ),
              onSelected: (_) {
                setState(() {
                  _labelController.text = cleanText;
                  if (_localError != null) _localError = null;
                });
              },
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildCountryDropdown(AddressState state) {
    final items = _uniqueDropdownItems<CountryModel>(
      source: state.countries,
      idOf: (c) => c.id,
      labelOf: (c) => c.isDeliveryActive
          ? c.nameAr
          : '${c.nameAr} (التوصيل غير متاح)',
      mutedOf: (c) => !c.isDeliveryActive,
    );
    final safeCountryId = _safeDropdownValue(state.selectedCountryId, items);

    return DropdownButtonFormField<int>(
      key: ValueKey(
        'country-${state.selectedCountryId}-$safeCountryId-${items.length}',
      ),
      initialValue: safeCountryId,
      decoration: InputDecoration(
        labelText: 'الدولة *',
        prefixIcon:
            Icon(Icons.public_rounded, size: 18.w, color: AppColors.textMuted),
        filled: true,
        fillColor: AppColors.surfaceSoft,
        errorText: state.fieldErrors['country_id'],
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12.r)),
      ),
      items: items,
      onChanged: items.isEmpty
          ? null
          : (value) => context.read<AddressCubit>().selectCountry(value),
      validator: (value) => value == null ? 'الدولة مطلوبة' : null,
    );
  }

  Widget _buildGovernorateDropdown(AddressState state) {
    final items = _uniqueDropdownItems<GovernorateModel>(
      source: state.governorates,
      idOf: (g) => g.id,
      labelOf: (g) => g.isDeliveryActive
          ? g.nameAr
          : '${g.nameAr} (التوصيل غير متاح)',
      mutedOf: (g) => !g.isDeliveryActive,
    );
    final safeGovId = _safeDropdownValue(state.selectedGovernorateId, items);

    return DropdownButtonFormField<int>(
      // Remount when country/items/selection change so FormField never keeps
      // a stale value that is missing from [items].
      key: ValueKey(
        'gov-${state.selectedCountryId}-$safeGovId-${items.map((e) => e.value).join(',')}',
      ),
      initialValue: safeGovId,
      decoration: InputDecoration(
        labelText: 'المحافظة *',
        prefixIcon:
            Icon(Icons.map_outlined, size: 18.w, color: AppColors.textMuted),
        filled: true,
        fillColor: AppColors.surfaceSoft,
        errorText: state.fieldErrors['governorate_id'],
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12.r)),
      ),
      items: items,
      onChanged: items.isEmpty
          ? null
          : (value) => context.read<AddressCubit>().selectGovernorate(value),
      validator: (value) => value == null ? 'المحافظة مطلوبة' : null,
    );
  }

  List<DropdownMenuItem<int>> _uniqueDropdownItems<T>({
    required List<T> source,
    required int Function(T) idOf,
    required String Function(T) labelOf,
    required bool Function(T) mutedOf,
  }) {
    final unique = <int, T>{};
    for (final item in source) {
      unique[idOf(item)] = item;
    }
    return unique.entries
        .map(
          (e) => DropdownMenuItem<int>(
            value: e.key,
            child: Text(
              labelOf(e.value),
              style: TextStyle(
                fontSize: 13.sp,
                color: mutedOf(e.value) ? AppColors.textMuted : null,
              ),
            ),
          ),
        )
        .toList();
  }

  int? _safeDropdownValue(int? selected, List<DropdownMenuItem<int>> items) {
    if (selected == null || items.isEmpty) return null;
    final matches = items.where((item) => item.value == selected);
    return matches.length == 1 ? selected : null;
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    IconData? icon,
    int maxLines = 1,
    bool required = false,
    String? errorText,
    TextInputType? keyboardType,
    TextDirection? textDirection,
  }) {
    return TextFormField(
      controller: controller,
      maxLines: maxLines,
      keyboardType: keyboardType,
      textDirection: textDirection,
      style: TextStyle(fontSize: 13.sp),
      onChanged: (_) {
        if (_localError != null) {
          setState(() => _localError = null);
        }
      },
      validator: required
          ? (value) {
              if (value == null || value.trim().isEmpty) {
                return 'هذا الحقل مطلوب';
              }
              return null;
            }
          : null,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: icon != null
            ? Icon(icon, size: 18.w, color: AppColors.textMuted)
            : null,
        filled: true,
        fillColor: AppColors.surfaceSoft,
        contentPadding:
            EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
        errorText: errorText,
        errorMaxLines: 1,
        errorStyle: TextStyle(
          color: AppColors.error,
          fontSize: 11.sp,
          height: 1.2,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12.r),
          borderSide: BorderSide(color: AppColors.border, width: 0.8),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12.r),
          borderSide: BorderSide(
            color: errorText != null ? AppColors.error : AppColors.border,
            width: 0.8,
          ),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12.r),
          borderSide: BorderSide(color: AppColors.error, width: 1),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12.r),
          borderSide: BorderSide(color: AppColors.error, width: 1),
        ),
      ),
    );
  }
}

class _OneLineErrorBanner extends StatelessWidget {
  const _OneLineErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: EdgeInsets.only(bottom: 12.h),
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 8.h),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, size: 16.w, color: AppColors.error),
          SizedBox(width: 6.w),
          Expanded(
            child: Text(
              message,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: AppColors.error,
                fontSize: 12.sp,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OneLineFieldError extends StatelessWidget {
  const _OneLineFieldError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Text(
      message,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(color: AppColors.error, fontSize: 11.sp),
    );
  }
}
