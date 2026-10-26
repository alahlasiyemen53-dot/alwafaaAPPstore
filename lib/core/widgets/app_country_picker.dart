import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/data/models/reference/yemen_reference_models.dart';
import 'package:store/data/repositories/reference_repository.dart';

/// Helper to convert standard ISO 3166-1 alpha-2 country codes into Unicode flag emojis.
String countryCodeToEmoji(String countryCode) {
  final clean = countryCode.trim().toUpperCase();
  if (clean.length != 2) return '🌐';
  final first = clean.codeUnitAt(0) - 0x41 + 0x1F1E6;
  final second = clean.codeUnitAt(1) - 0x41 + 0x1F1E6;
  return String.fromCharCode(first) + String.fromCharCode(second);
}

/// Reusable country picker fed only by Customer Countries API.
/// Designed with luxury Alexandria typography, native emoji flags, and live search.
class AppCountryPicker extends StatefulWidget {
  final ReferenceRepository repository;
  final int? selectedCountryId;
  final ValueChanged<CountryModel> onSelected;
  final String label;
  final bool enabled;
  final bool autofocusLoad;

  const AppCountryPicker({
    super.key,
    required this.repository,
    required this.onSelected,
    this.selectedCountryId,
    this.label = 'الدولة',
    this.enabled = true,
    this.autofocusLoad = true,
  });

  @override
  State<AppCountryPicker> createState() => _AppCountryPickerState();
}

class _AppCountryPickerState extends State<AppCountryPicker> {
  List<CountryModel> _countries = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.autofocusLoad) {
      _load();
    }
  }

  Future<void> _load() async {
    final cached = await widget.repository.peekCachedCountries();
    if (cached.isNotEmpty && mounted) {
      setState(() {
        _countries = _sortCountries(cached);
        _loading = false;
        _error = null;
      });
      if (widget.selectedCountryId == null && _countries.isNotEmpty) {
        final yemen = _countries.where((c) => c.isoCode.toUpperCase() == 'YE');
        widget.onSelected(yemen.isNotEmpty ? yemen.first : _countries.first);
      }
    } else {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final countries = await widget.repository.getCountries();
      if (!mounted) return;
      setState(() {
        _countries = _sortCountries(countries);
        _loading = false;
        _error = null;
      });

      if (widget.selectedCountryId == null && _countries.isNotEmpty) {
        final yemen = _countries.where((c) => c.isoCode.toUpperCase() == 'YE');
        widget.onSelected(yemen.isNotEmpty ? yemen.first : _countries.first);
      }
    } catch (_) {
      if (!mounted) return;
      if (_countries.isEmpty) {
        setState(() {
          _loading = false;
          _error = 'تعذر تحميل الدول. تحقق من الاتصال ثم أعد المحاولة.';
        });
      }
    }
  }

  List<CountryModel> _sortCountries(List<CountryModel> list) {
    return List<CountryModel>.from(list)
      ..sort((a, b) {
        final byOrder = a.sortOrder.compareTo(b.sortOrder);
        if (byOrder != 0) return byOrder;
        return a.displayName.compareTo(b.displayName);
      });
  }

  CountryModel? get _selected {
    final id = widget.selectedCountryId;
    if (id == null) return null;
    for (final c in _countries) {
      if (c.id == id) return c;
    }
    return null;
  }

  Future<void> _openSheet() async {
    if (!widget.enabled || _loading) return;
    if (_error != null) {
      await _load();
      return;
    }
    if (_countries.isEmpty) {
      await _load();
      if (!mounted || _countries.isEmpty) return;
    }

    final selected = await showModalBottomSheet<CountryModel>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CountryPickerSheet(
        countries: _countries,
        selectedId: widget.selectedCountryId,
      ),
    );
    if (selected != null) {
      widget.onSelected(selected);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selected;

    return InkWell(
      onTap: widget.enabled ? _openSheet : null,
      borderRadius: BorderRadius.circular(16.r),
      child: Container(
        padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 12.h),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16.r),
          border: Border.all(
            color: _error != null ? AppColors.error : AppColors.border,
            width: 1.2,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.primaryDark.withValues(alpha: 0.03),
              blurRadius: 10,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            if (_loading) ...[
              SizedBox(
                width: 20.w,
                height: 20.w,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: AppColors.primaryDark,
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Text(
                  'جاري تحميل الدول...',
                  style: GoogleFonts.alexandria(
                    fontSize: 11.5.sp,
                    color: AppColors.textMuted,
                  ),
                ),
              ),
            ] else if (_error != null) ...[
              Icon(Icons.error_outline_rounded, color: AppColors.error, size: 22.w),
              SizedBox(width: 10.w),
              Expanded(
                child: Text(
                  _error!,
                  style: GoogleFonts.alexandria(
                    fontSize: 10.5.sp,
                    color: AppColors.error,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Icon(Icons.refresh_rounded, color: AppColors.primaryDark, size: 20.w),
            ] else if (selected != null) ...[
              Container(
                width: 36.w,
                height: 36.w,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(10.r),
                  border: Border.all(color: AppColors.borderLight),
                ),
                alignment: Alignment.center,
                child: Text(
                  countryCodeToEmoji(selected.isoCode),
                  style: TextStyle(fontSize: 18.sp),
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      widget.label,
                      style: GoogleFonts.alexandria(
                        fontSize: 9.sp,
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    SizedBox(height: 2.h),
                    Text(
                      selected.displayName,
                      style: GoogleFonts.alexandria(
                        fontSize: 12.sp,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              Container(
                padding: EdgeInsets.symmetric(horizontal: 9.w, vertical: 3.5.h),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: AppColors.borderLight),
                ),
                child: Text(
                  selected.callingCode,
                  textDirection: TextDirection.ltr,
                  style: GoogleFonts.alexandria(
                    fontSize: 10.5.sp,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                ),
              ),
              SizedBox(width: 8.w),
              Icon(
                Icons.keyboard_arrow_down_rounded,
                color: AppColors.textMuted,
                size: 22.w,
              ),
            ] else ...[
              Icon(Icons.public_rounded, color: AppColors.primaryDark, size: 22.w),
              SizedBox(width: 12.w),
              Expanded(
                child: Text(
                  _countries.isEmpty ? 'لا توجد دول متاحة' : 'اختر الدولة',
                  style: GoogleFonts.alexandria(
                    fontSize: 11.5.sp,
                    color: AppColors.textMuted,
                  ),
                ),
              ),
              Icon(
                Icons.keyboard_arrow_down_rounded,
                color: AppColors.textMuted,
                size: 22.w,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _CountryPickerSheet extends StatefulWidget {
  final List<CountryModel> countries;
  final int? selectedId;

  const _CountryPickerSheet({
    required this.countries,
    required this.selectedId,
  });

  @override
  State<_CountryPickerSheet> createState() => _CountryPickerSheetState();
}

class _CountryPickerSheetState extends State<_CountryPickerSheet> {
  late final TextEditingController _search;
  String _query = '';
  bool _filterOnlyDelivery = false;

  @override
  void initState() {
    super.initState();
    _search = TextEditingController();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<CountryModel> get _filtered {
    var list = widget.countries;
    if (_filterOnlyDelivery) {
      list = list.where((c) => c.isDeliveryActive).toList();
    }

    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return list;

    final qCleanCalling = q.replaceAll('+', '').trim();

    return list.where((c) {
      final nameArMatch = c.nameAr.toLowerCase().contains(q);
      final nameEnMatch = c.nameEn.toLowerCase().contains(q);
      final isoMatch = c.isoCode.toLowerCase().contains(q);
      final callingCodeClean = c.callingCode.replaceAll('+', '').trim();
      final callingMatch = callingCodeClean.contains(qCleanCalling);
      return nameArMatch || nameEnMatch || isoMatch || callingMatch;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    final filtered = _filtered;
    final deliveryActiveCount =
        widget.countries.where((c) => c.isDeliveryActive).length;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32.r)),
        clipBehavior: Clip.antiAlias,
        child: Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(context).height * 0.88,
          ),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(32.r)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // 1. Drag Handle
              SizedBox(height: 12.h),
              Container(
                width: 48.w,
                height: 5.h,
                decoration: BoxDecoration(
                  color: AppColors.primaryDark.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(10.r),
                ),
              ),
              SizedBox(height: 16.h),

              // 2. Luxury Header
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 20.w),
                child: Row(
                  children: [
                    Container(
                      width: 44.w,
                      height: 44.w,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppColors.primaryDark.withValues(alpha: 0.1),
                            AppColors.surfaceSoft,
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.borderLight),
                      ),
                      alignment: Alignment.center,
                      child: Icon(
                        Icons.public_rounded,
                        color: AppColors.primaryDark,
                        size: 22.w,
                      ),
                    ),
                    SizedBox(width: 14.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'اختر الدولة',
                            style: GoogleFonts.alexandria(
                              fontSize: 16.sp,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primaryDark,
                              letterSpacing: -0.2,
                            ),
                          ),
                          SizedBox(height: 3.h),
                          Text(
                            'اختر دولتك للبدء برقم هاتفك ومتابعة التسوق',
                            style: GoogleFonts.alexandria(
                              fontSize: 10.sp,
                              fontWeight: FontWeight.w400,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    InkWell(
                      onTap: () {
                        FocusScope.of(context).unfocus();
                        Navigator.pop(context);
                      },
                      borderRadius: BorderRadius.circular(20.r),
                      child: Container(
                        width: 36.w,
                        height: 36.w,
                        decoration: BoxDecoration(
                          color: AppColors.surfaceSoft,
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.borderLight),
                        ),
                        alignment: Alignment.center,
                        child: Icon(
                          Icons.close_rounded,
                          color: AppColors.textSecondary,
                          size: 18.w,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 16.h),

              // 3. Search Bar with Alexandria font
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 20.w),
                child: Container(
                  height: 48.h,
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(16.r),
                    border: Border.all(color: AppColors.borderLight),
                  ),
                  child: TextField(
                    controller: _search,
                    onChanged: (v) => setState(() => _query = v),
                    style: GoogleFonts.alexandria(
                      fontSize: 11.5.sp,
                      fontWeight: FontWeight.w500,
                      color: AppColors.textPrimary,
                    ),
                    decoration: InputDecoration(
                      hintText: 'ابحث باسم الدولة، الرمز، أو مفتاح الاتصال...',
                      hintStyle: GoogleFonts.alexandria(
                        fontSize: 11.sp,
                        fontWeight: FontWeight.w400,
                        color: AppColors.textMuted,
                      ),
                      prefixIcon: Icon(
                        Icons.search_rounded,
                        color: AppColors.primaryDark,
                        size: 20.w,
                      ),
                      suffixIcon: _query.isNotEmpty
                          ? IconButton(
                              icon: Icon(
                                Icons.close_rounded,
                                color: AppColors.textMuted,
                                size: 17.w,
                              ),
                              onPressed: () {
                                _search.clear();
                                setState(() => _query = '');
                              },
                            )
                          : null,
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 14.w,
                        vertical: 12.h,
                      ),
                    ),
                  ),
                ),
              ),

              SizedBox(height: 12.h),

              // 4. Filter Pills & Total Counter
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 20.w),
                child: Row(
                  children: [
                    _buildFilterChip(
                      label: 'الكل (${widget.countries.length})',
                      isSelected: !_filterOnlyDelivery,
                      onTap: () => setState(() => _filterOnlyDelivery = false),
                    ),
                    SizedBox(width: 8.w),
                    _buildFilterChip(
                      label: 'توصيل متاح ($deliveryActiveCount) 🚚',
                      isSelected: _filterOnlyDelivery,
                      onTap: () => setState(() => _filterOnlyDelivery = true),
                    ),
                    const Spacer(),
                    Text(
                      '${filtered.length} دولة',
                      style: GoogleFonts.alexandria(
                        fontSize: 10.sp,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primaryDark,
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 10.h),
              Divider(height: 1, color: AppColors.borderLight),

              // 5. Countries List
              Flexible(
                child: filtered.isEmpty
                    ? _buildEmptyState()
                    : ListView.separated(
                        padding: EdgeInsets.only(
                          left: 20.w,
                          right: 20.w,
                          top: 14.h,
                          bottom: 20.h + (keyboard > 0 ? keyboard : bottom),
                        ),
                        shrinkWrap: true,
                        itemCount: filtered.length,
                        separatorBuilder: (_, _) => SizedBox(height: 10.h),
                        itemBuilder: (context, index) {
                          final country = filtered[index];
                          final isSelected = country.id == widget.selectedId;
                          return _buildCountryCard(country, isSelected);
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFilterChip({
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10.r),
      child: Container(
        padding: EdgeInsets.symmetric(horizontal: 11.w, vertical: 5.h),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primaryDark : AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(10.r),
          border: Border.all(
            color: isSelected ? AppColors.primaryDark : AppColors.borderLight,
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.alexandria(
            fontSize: 9.5.sp,
            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
            color: isSelected ? Colors.white : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }

  Widget _buildCountryCard(CountryModel country, bool isSelected) {
    final flag = countryCodeToEmoji(country.isoCode);

    return InkWell(
      onTap: () {
        FocusScope.of(context).unfocus();
        Navigator.pop(context, country);
      },
      borderRadius: BorderRadius.circular(16.r),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 11.h),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.surfaceSoft : AppColors.surface,
          borderRadius: BorderRadius.circular(16.r),
          border: Border.all(
            color: isSelected ? AppColors.primaryDark : AppColors.borderLight,
            width: isSelected ? 1.5 : 1.0,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? AppColors.primaryDark.withValues(alpha: 0.08)
                  : AppColors.primaryDark.withValues(alpha: 0.02),
              blurRadius: isSelected ? 10 : 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            // Flag Box
            Container(
              width: 40.w,
              height: 40.w,
              decoration: BoxDecoration(
                color: isSelected ? Colors.white : AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(11.r),
                border: Border.all(
                  color: isSelected
                      ? AppColors.primaryDark.withValues(alpha: 0.25)
                      : AppColors.borderLight,
                ),
              ),
              alignment: Alignment.center,
              child: Text(
                flag,
                style: TextStyle(fontSize: 20.sp),
              ),
            ),
            SizedBox(width: 12.w),

            // Country Name & ISO
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          country.displayName,
                          style: GoogleFonts.alexandria(
                            fontSize: 12.sp,
                            fontWeight:
                                isSelected ? FontWeight.w700 : FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (country.isDeliveryActive) ...[
                        SizedBox(width: 8.w),
                        Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: 6.w,
                            vertical: 2.h,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(6.r),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.local_shipping_rounded,
                                size: 10.w,
                                color: AppColors.success,
                              ),
                              SizedBox(width: 3.w),
                              Text(
                                'توصيل متاح',
                                style: GoogleFonts.alexandria(
                                  fontSize: 8.sp,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.success,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                  SizedBox(height: 2.h),
                  Row(
                    children: [
                      Text(
                        country.nameEn,
                        style: GoogleFonts.alexandria(
                          fontSize: 9.5.sp,
                          fontWeight: FontWeight.w400,
                          color: AppColors.textMuted,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      SizedBox(width: 6.w),
                      Container(
                        width: 3.w,
                        height: 3.w,
                        decoration: BoxDecoration(
                          color: AppColors.textMuted.withValues(alpha: 0.5),
                          shape: BoxShape.circle,
                        ),
                      ),
                      SizedBox(width: 6.w),
                      Container(
                        padding: EdgeInsets.symmetric(horizontal: 5.w, vertical: 1.h),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceSoft,
                          borderRadius: BorderRadius.circular(4.r),
                        ),
                        child: Text(
                          country.isoCode,
                          style: GoogleFonts.alexandria(
                            fontSize: 8.sp,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            SizedBox(width: 10.w),

            // Calling Code Badge
            Container(
              padding: EdgeInsets.symmetric(horizontal: 9.w, vertical: 5.h),
              decoration: BoxDecoration(
                color: isSelected
                    ? AppColors.primaryDark
                    : AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(9.r),
                border: Border.all(
                  color: isSelected
                      ? AppColors.primaryDark
                      : AppColors.borderLight,
                ),
              ),
              child: Text(
                country.callingCode,
                textDirection: TextDirection.ltr,
                style: GoogleFonts.alexandria(
                  fontSize: 10.5.sp,
                  fontWeight: FontWeight.w700,
                  color: isSelected ? Colors.white : AppColors.primaryDark,
                ),
              ),
            ),

            SizedBox(width: 10.w),

            // Radio / Check Indicator
            Icon(
              isSelected
                  ? Icons.check_circle_rounded
                  : Icons.radio_button_unchecked_rounded,
              color: isSelected ? AppColors.primaryDark : AppColors.border,
              size: 20.w,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: 40.h, horizontal: 20.w),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 58.w,
            height: 58.w,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Icon(
              Icons.search_off_rounded,
              size: 28.w,
              color: AppColors.textMuted,
            ),
          ),
          SizedBox(height: 14.h),
          Text(
            'لا توجد دولة مطابقة',
            style: GoogleFonts.alexandria(
              fontSize: 13.sp,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: 5.h),
          Text(
            _query.isNotEmpty
                ? 'لم نتمكن من العثور على "$_query"'
                : 'لا توجد عناصر في هذا التصنيف',
            textAlign: TextAlign.center,
            style: GoogleFonts.alexandria(
              fontSize: 10.5.sp,
              color: AppColors.textMuted,
            ),
          ),
          if (_query.isNotEmpty || _filterOnlyDelivery) ...[
            SizedBox(height: 14.h),
            TextButton.icon(
              onPressed: () {
                _search.clear();
                setState(() {
                  _query = '';
                  _filterOnlyDelivery = false;
                });
              },
              icon: const Icon(Icons.refresh_rounded),
              label: Text(
                'عرض كل الدول',
                style: GoogleFonts.alexandria(
                  fontSize: 11.sp,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
