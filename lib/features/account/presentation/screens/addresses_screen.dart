import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/widgets/skeletons/screen_skeletons.dart';
import '../../../../data/models/account/customer_address_model.dart';
import '../../cubit/address_cubit.dart';
import '../widgets/address_form_sheet.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  static Future<void> open(BuildContext context) {
    return Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BlocProvider.value(
          value: context.read<AddressCubit>()..fetchAddresses(),
          child: const AddressesScreen(),
        ),
      ),
    );
  }

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  @override
  void initState() {
    super.initState();
    context.read<AddressCubit>().fetchAddresses();
  }

  @override
  Widget build(BuildContext context) {
    final safeBottom = MediaQuery.viewPaddingOf(context).bottom;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'العناوين المحفوظة',
          style: AppTypography.titleMedium.copyWith(
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.primaryDark),
      ),
      bottomNavigationBar: Material(
        color: AppColors.surface,
        elevation: 8,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            16.w,
            10.h,
            16.w,
            10.h + safeBottom,
          ),
          child: SizedBox(
            height: 48.h,
            child: ElevatedButton.icon(
              onPressed: () async {
                final cubit = context.read<AddressCubit>();
                final added = await AddressFormSheet.show(context);
                if (added == true && mounted) {
                  cubit.fetchAddresses();
                }
              },
              icon: const Icon(Icons.add_location_alt_outlined,
                  color: Colors.white),
              label: Text(
                'إضافة عنوان جديد',
                style: TextStyle(fontSize: 14.sp, fontWeight: FontWeight.bold),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryDark,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14.r),
                ),
              ),
            ),
          ),
        ),
      ),
      body: BlocBuilder<AddressCubit, AddressState>(
        builder: (context, state) {
          if (state.status == AddressStatus.loading && state.addresses.isEmpty) {
            return const AddressesListSkeleton();
          }

          if (state.status == AddressStatus.failure && state.addresses.isEmpty) {
            return Center(
              child: Padding(
                padding: EdgeInsets.all(24.w),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.error_outline_rounded,
                        size: 48.w, color: AppColors.error),
                    SizedBox(height: 12.h),
                    Text(
                      state.errorMessage ?? 'حدث خطأ أثناء جلب العناوين',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 13.sp, color: AppColors.textMuted),
                    ),
                    SizedBox(height: 16.h),
                    OutlinedButton(
                      onPressed: () => context.read<AddressCubit>().fetchAddresses(),
                      child: const Text('إعادة المحاولة'),
                    ),
                  ],
                ),
              ),
            );
          }

          if (state.addresses.isEmpty) {
            return Center(
              child: Padding(
                padding: EdgeInsets.all(32.w),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 80.w,
                      height: 80.w,
                      decoration: BoxDecoration(
                        color: AppColors.surfaceSoft,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.pin_drop_outlined,
                        size: 38.w,
                        color: AppColors.textMuted,
                      ),
                    ),
                    SizedBox(height: 16.h),
                    Text(
                      'لا توجد عناوين مسجلة بعد',
                      style: AppTypography.titleMedium.copyWith(
                        fontWeight: FontWeight.bold,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    SizedBox(height: 8.h),
                    Text(
                      'أضف عنوان التوصيل الخاص بك لتسهيل وسرعة إتمام طلباتك',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 12.5.sp, color: AppColors.textMuted),
                    ),
                  ],
                ),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => context.read<AddressCubit>().fetchAddresses(),
            child: ListView.separated(
              padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
              itemCount: state.addresses.length,
              separatorBuilder: (_, _) => SizedBox(height: 12.h),
              itemBuilder: (context, index) {
                final address = state.addresses[index];
                return _buildAddressCard(context, address);
              },
            ),
          );
        },
      ),
    );
  }

  Widget _buildAddressCard(BuildContext context, CustomerAddressModel address) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18.r),
        border: Border.all(
          color: address.isDefault ? AppColors.goldDark : AppColors.border,
          width: address.isDefault ? 1.4 : 0.8,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Row(
                  children: [
                    Container(
                      padding: EdgeInsets.all(6.w),
                      decoration: BoxDecoration(
                        color: (address.isDefault ? AppColors.goldDark : AppColors.primaryDark)
                            .withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10.r),
                      ),
                      child: Icon(
                        _getLabelIcon(address.label),
                        color: address.isDefault ? AppColors.goldDark : AppColors.primaryDark,
                        size: 18.w,
                      ),
                    ),
                    SizedBox(width: 10.w),
                    Expanded(
                      child: Text(
                        address.label != null && address.label!.isNotEmpty
                            ? address.label!
                            : 'عنوان الشحن',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 14.5.sp,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (address.isDefault)
                Container(
                  padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
                  decoration: BoxDecoration(
                    color: AppColors.goldDark.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(10.r),
                    border: Border.all(
                      color: AppColors.goldDark.withValues(alpha: 0.3),
                      width: 0.8,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.star_rounded, size: 13.w, color: AppColors.goldDark),
                      SizedBox(width: 3.w),
                      Text(
                        'العنوان الرئيسي',
                        style: TextStyle(
                          fontSize: 10.5.sp,
                          fontWeight: FontWeight.bold,
                          color: AppColors.goldDark,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          if (address.contactName != null && address.contactName!.isNotEmpty ||
              address.contactPhone != null && address.contactPhone!.isNotEmpty) ...[
            SizedBox(height: 10.h),
            Container(
              padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 7.h),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(10.r),
              ),
              child: Row(
                children: [
                  Icon(Icons.person_outline_rounded,
                      size: 15.w, color: AppColors.goldDark),
                  SizedBox(width: 6.w),
                  Expanded(
                    child: Text(
                      'المستلم: ${address.contactName ?? 'العميل'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12.sp,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  if (address.contactPhone != null &&
                      address.contactPhone!.isNotEmpty) ...[
                    SizedBox(width: 8.w),
                    Icon(Icons.phone_outlined,
                        size: 14.w, color: AppColors.textMuted),
                    SizedBox(width: 4.w),
                    Directionality(
                      textDirection: TextDirection.ltr,
                      child: Text(
                        address.contactPhone!,
                        style: TextStyle(
                          fontSize: 11.5.sp,
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
          SizedBox(height: 8.h),
          Wrap(
            spacing: 6.w,
            runSpacing: 4.h,
            children: [
              if (address.countryNameAr != null && address.countryNameAr!.isNotEmpty)
                _buildInfoBadge(Icons.public_rounded, address.countryNameAr!),
              if (address.governorateNameAr != null && address.governorateNameAr!.isNotEmpty)
                _buildInfoBadge(Icons.map_outlined, address.governorateNameAr!),
              if (address.latitude != null && address.longitude != null)
                _buildInfoBadge(Icons.location_on, 'محدد على الخريطة', isHighlight: true),
            ],
          ),
          SizedBox(height: 8.h),
          Text(
            address.displayFullAddress,
            style: TextStyle(
              fontSize: 12.5.sp,
              color: AppColors.textPrimary,
              height: 1.4,
            ),
          ),
          SizedBox(height: 8.h),
          Divider(color: AppColors.borderLight, height: 1),
          SizedBox(height: 6.h),
          Row(
            children: [
              if (!address.isDefault)
                Flexible(
                  child: TextButton.icon(
                    onPressed: () {
                      context.read<AddressCubit>().updateAddress(
                        address.id,
                        {'is_default': true},
                      );
                    },
                    icon: Icon(Icons.star_outline_rounded, size: 16.w),
                    label: Text(
                      'تعيين كرئيسي',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 11.5.sp),
                    ),
                    style: TextButton.styleFrom(
                        foregroundColor: AppColors.textPrimary),
                  ),
                ),
              Flexible(
                child: TextButton.icon(
                  onPressed: () async {
                    final cubit = context.read<AddressCubit>();
                    final updated = await AddressFormSheet.show(
                      context,
                      existingAddress: address,
                    );
                    if (updated == true && mounted) {
                      cubit.fetchAddresses();
                    }
                  },
                  icon: Icon(Icons.edit_outlined, size: 16.w),
                  label: Text(
                    'تعديل',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 11.5.sp),
                  ),
                  style: TextButton.styleFrom(
                      foregroundColor: AppColors.goldDark),
                ),
              ),
              Flexible(
                child: TextButton.icon(
                  onPressed: () => _confirmDelete(context, address),
                  icon: Icon(Icons.delete_outline_rounded, size: 16.w),
                  label: Text(
                    'حذف',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 11.5.sp),
                  ),
                  style:
                      TextButton.styleFrom(foregroundColor: AppColors.error),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _confirmDelete(BuildContext context, CustomerAddressModel address) {
    showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Text('حذف العنوان'),
        content: const Text('هل أنت متأكد من رغبتك في حذف هذا العنوان؟'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogCtx).pop(),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(dialogCtx).pop();
              context.read<AddressCubit>().deleteAddress(address.id);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
            ),
            child: const Text('حذف'),
          ),
        ],
      ),
    );
  }

  IconData _getLabelIcon(String? label) {
    if (label == null || label.trim().isEmpty) return Icons.location_on_outlined;
    final l = label.toLowerCase();
    if (l.contains('منزل') || l.contains('بيت') || l.contains('home')) {
      return Icons.home_rounded;
    }
    if (l.contains('عمل') || l.contains('مكتب') || l.contains('work') || l.contains('office')) {
      return Icons.business_rounded;
    }
    if (l.contains('شقة') || l.contains('apt')) {
      return Icons.apartment_rounded;
    }
    return Icons.place_rounded;
  }

  Widget _buildInfoBadge(IconData icon, String text, {bool isHighlight = false}) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
      decoration: BoxDecoration(
        color: isHighlight
            ? AppColors.goldDark.withValues(alpha: 0.1)
            : AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(8.r),
        border: Border.all(
          color: isHighlight
              ? AppColors.goldDark.withValues(alpha: 0.3)
              : AppColors.border,
          width: 0.6,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon,
              size: 12.w,
              color: isHighlight ? AppColors.goldDark : AppColors.textMuted),
          SizedBox(width: 4.w),
          Text(
            text,
            style: TextStyle(
              fontSize: 11.sp,
              color: isHighlight ? AppColors.goldDark : AppColors.textPrimary,
              fontWeight: isHighlight ? FontWeight.bold : FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}
