import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../core/constants/app_colors.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/utils/extensions.dart';
import '../../../../core/widgets/cached_image_widget.dart';
import '../../../../core/widgets/luxury_button.dart';
import '../../../../core/widgets/luxury_text_field.dart';
import '../../../../core/widgets/skeletons/skeletons.dart';
import '../../../../data/models/account/customer_address_model.dart';
import '../../../../data/models/checkout/checkout_preview_model.dart';
import '../../../account/cubit/address_cubit.dart';
import '../../../account/presentation/widgets/address_form_sheet.dart';
import '../../../app_shell/cubit/app_shell_cubit.dart';
import '../../../cart/cubit/cart_cubit.dart';
import '../../cubit/checkout_cubit.dart';
import '../widgets/floosak_otp_sheet.dart';
import '../widgets/jaib_payment_sheet.dart';
import 'order_success_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final TextEditingController _giftMessageController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _couponController = TextEditingController();
  bool _initialized = false;
  bool _otpSheetOpen = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initCheckout();
    });
  }

  void _initCheckout() {
    if (!mounted || _initialized) return;
    _initialized = true;

    final addressCubit = context.read<AddressCubit>();
    if (addressCubit.state.addresses.isEmpty) {
      addressCubit.fetchAddresses();
    }

    final cartState = context.read<CartCubit>().state;
    if (cartState.promoCode != null && cartState.promoCode!.isNotEmpty) {
      _couponController.text = cartState.promoCode!;
    }

    context.read<CheckoutCubit>().initializeCheckout(
          addresses: addressCubit.state.addresses,
          couponCode: cartState.promoCode,
          cartItemIds: cartState.selectedCartItemIdsForCheckout,
        );
  }

  @override
  void dispose() {
    _giftMessageController.dispose();
    _notesController.dispose();
    _couponController.dispose();
    super.dispose();
  }

  Future<void> _handlePaymentMethodTap(
    BuildContext context,
    CheckoutPaymentMethodModel method,
  ) async {
    final cubit = context.read<CheckoutCubit>();
    final alreadySelected = cubit.state.paymentMethod == method.code;

    if (!alreadySelected) {
      await cubit.selectPaymentMethod(method.code);
    }

    if (!context.mounted) return;

    if (method.isStep1) {
      await JaibPaymentSheet.show(context);
      return;
    }

    if (method.isStep2) {
      if (cubit.state.isAwaitingPaymentOtp) {
        await _openOtpSheet(context);
        return;
      }
      final ok = await cubit.initiateStep2Payment();
      if (!context.mounted) return;
      if (ok) {
        await _openOtpSheet(context);
      }
    }
  }

  Future<void> _openOtpSheet(BuildContext context) async {
    if (_otpSheetOpen) return;
    _otpSheetOpen = true;
    try {
      await FloosakOtpSheet.show(context);
    } finally {
      _otpSheetOpen = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<CheckoutCubit, CheckoutState>(
      listenWhen: (previous, current) =>
          previous.status != current.status ||
          previous.errorMessage != current.errorMessage,
      listener: (context, state) {
        if (state.isSuccess && state.confirmedOrder != null) {
          context.read<CartCubit>().fetchCart();
        }

        if (state.isAwaitingPaymentOtp &&
            !_otpSheetOpen &&
            ModalRoute.of(context)?.isCurrent == true) {
          _openOtpSheet(context);
        }

        if (state.errorMessage != null &&
            state.status == CheckoutStatus.error) {
          context.showAppSnackBar(
            state.errorMessage!,
            type: AppSnackBarType.error,
          );
        }
      },
      builder: (context, state) {
        if (state.isSuccess && state.confirmedOrder != null) {
          return OrderSuccessScreen(order: state.confirmedOrder!);
        }

        return BlocListener<AddressCubit, AddressState>(
          listenWhen: (previous, current) =>
              previous.addresses != current.addresses,
          listener: (context, addressState) {
            final checkout = context.read<CheckoutCubit>();
            final selected = checkout.state.selectedAddress;
            if (addressState.addresses.isEmpty) return;

            if (selected == null) {
              checkout.selectAddress(
                addressState.defaultAddress ?? addressState.addresses.first,
              );
              return;
            }

            // Refresh shipping when the selected address was edited
            // (e.g. governorate changed) even if the id stayed the same.
            CustomerAddressModel? fresh;
            for (final addr in addressState.addresses) {
              if (addr.id == selected.id) {
                fresh = addr;
                break;
              }
            }
            if (fresh != null &&
                (fresh.governorateId != selected.governorateId ||
                    fresh != selected)) {
              checkout.selectAddress(fresh);
            }
          },
          child: Scaffold(
            backgroundColor: AppColors.background,
            appBar: AppBar(
              leading: IconButton(
                icon: const Icon(Icons.arrow_back_rounded),
                onPressed: () =>
                    context.read<AppShellCubit>().closeCheckout(),
              ),
              title: Text(
                AppStrings.checkoutTitle,
                style: AppTypography.titleMedium
                    .copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            body: state.isLoadingPreview && state.preview == null
                ? const CheckoutSkeleton()
                : _buildCheckoutContent(context, state),
            bottomNavigationBar: _buildBottomBar(context, state),
          ),
        );
      },
    );
  }

  Widget _buildCheckoutContent(BuildContext context, CheckoutState state) {
    final preview = state.preview;

    return RefreshIndicator(
      color: AppColors.primaryDark,
      onRefresh: () => context.read<CheckoutCubit>().fetchPreview(),
      child: ListView(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
        children: [
          // Warnings Banner
          if (preview != null && preview.warnings.isNotEmpty)
            _buildWarningsCard(preview.warnings),

          // 1. Delivery Address Card
          _buildAddressSection(context, state),

          SizedBox(height: 16.h),

          // 2. Shipping Method Card
          _buildShippingMethodSection(context, state),

          SizedBox(height: 16.h),

          // 3. Payment Method Card
          _buildPaymentMethodSection(context, state),

          SizedBox(height: 16.h),

          // 4. Gift Wrapping Card (only when backend feature is enabled)
          if (preview?.giftWrapping?.enabled == true) ...[
            _buildGiftWrappingSection(context, state),
            SizedBox(height: 16.h),
          ],

          // 5. Delivery Notes (Optional)
          _buildNotesSection(context, state),

          SizedBox(height: 16.h),

          // 6. Preview Items Summary
          if (preview != null && preview.items.isNotEmpty) ...[
            _buildItemsPreviewSection(preview),
            SizedBox(height: 16.h),
          ],

          // 7. Coupon Section (moved from Cart to Order Confirmation)
          _buildCouponSection(context, state),

          SizedBox(height: 16.h),

          // 8. Server Totals Breakdown
          Opacity(
            opacity: state.isLoadingPreview ? 0.55 : 1,
            child: _buildTotalsSection(preview),
          ),

          SizedBox(height: 24.h),
        ],
      ),
    );
  }

  Widget _buildWarningsCard(List<String> warnings) {
    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(
          color: AppColors.warning.withValues(alpha: 0.4),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.warning_amber_rounded,
            color: AppColors.warning,
            size: 22.sp,
          ),
          SizedBox(width: 8.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: warnings
                  .map((w) => Padding(
                        padding: EdgeInsets.only(bottom: 2.h),
                        child: Text(
                          w,
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ))
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAddressSection(BuildContext context, CheckoutState state) {
    final address = state.selectedAddress;

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
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
                    Icon(
                      Icons.location_on_rounded,
                      color: AppColors.primaryDark,
                      size: 20.sp,
                    ),
                    SizedBox(width: 8.w),
                    Expanded(
                      child: Text(
                        'عنوان التوصيل',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.titleSmall.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (address != null)
                TextButton(
                  onPressed: () => _showAddressPicker(context),
                  child: Text(
                    'تغيير',
                    style: AppTypography.labelMedium.copyWith(
                      color: AppColors.primaryDark,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
            ],
          ),
          SizedBox(height: 8.h),
          if (address == null)
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'لم يتم تحديد عنوان توصيل بعد.',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textMuted,
                  ),
                ),
                SizedBox(height: 10.h),
                OutlinedButton.icon(
                  onPressed: () => _addNewAddress(context),
                  icon: const Icon(Icons.add_location_alt_outlined),
                  label: const Text('إضافة عنوان جديد'),
                  style: OutlinedButton.styleFrom(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                  ),
                ),
              ],
            )
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (address.label != null && address.label!.isNotEmpty)
                  Container(
                    margin: EdgeInsets.only(bottom: 6.h),
                    padding: EdgeInsets.symmetric(
                      horizontal: 8.w,
                      vertical: 2.h,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.primaryDark.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(6.r),
                    ),
                    child: Text(
                      address.label!,
                      style: AppTypography.labelSmall.copyWith(
                        color: AppColors.primaryDark,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                Text(
                  address.displayFullAddress,
                  style: AppTypography.bodyMedium.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                SizedBox(height: 4.h),
                Text(
                  'المستلم: ${address.contactName ?? 'العميل'} • ${address.contactPhone ?? ''}',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }

  void _showAddressPicker(BuildContext parentContext) {
    final checkoutCubit = parentContext.read<CheckoutCubit>();
    final addressCubit = parentContext.read<AddressCubit>();
    final addresses = addressCubit.state.addresses;
    final selectedAddressId = checkoutCubit.state.selectedAddress?.id;

    showModalBottomSheet(
      context: parentContext,
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24.r)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          top: false,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'اختر عنوان التوصيل',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.titleMedium.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded),
                      onPressed: () => Navigator.pop(sheetContext),
                    ),
                  ],
                ),
                Divider(color: AppColors.border),
                SizedBox(
                  height: 220.h,
                  child: ListView.separated(
                    itemCount: addresses.length,
                    separatorBuilder: (_, __) =>
                        Divider(color: AppColors.border, height: 1),
                    itemBuilder: (_, index) {
                      final addr = addresses[index];
                      final isSelected = selectedAddressId == addr.id;

                      return ListTile(
                        leading: Icon(
                          isSelected
                              ? Icons.radio_button_checked
                              : Icons.radio_button_off,
                          color: isSelected
                              ? AppColors.primaryDark
                              : AppColors.textMuted,
                        ),
                        title: Text(
                          addr.label ?? addr.governorateNameAr ?? 'عنوان',
                          style: AppTypography.bodyMedium.copyWith(
                            fontWeight:
                                isSelected ? FontWeight.w700 : FontWeight.w500,
                          ),
                        ),
                        subtitle: Text(
                          addr.displayFullAddress,
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        onTap: () {
                          Navigator.pop(sheetContext);
                          checkoutCubit.selectAddress(addr);
                        },
                      );
                    },
                  ),
                ),
                SizedBox(height: 12.h),
                Padding(
                  padding: EdgeInsets.only(
                    bottom: MediaQuery.viewPaddingOf(sheetContext).bottom > 0
                        ? 4.h
                        : 8.h,
                  ),
                  child: LuxuryButton(
                    text: '+ إضافة عنوان توصيل جديد',
                    width: double.infinity,
                    variant: LuxuryButtonVariant.outline,
                    onPressed: () {
                      Navigator.pop(sheetContext);
                      _addNewAddress(parentContext);
                    },
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _addNewAddress(BuildContext parentContext) async {
    final addressCubit = parentContext.read<AddressCubit>();
    final checkoutCubit = parentContext.read<CheckoutCubit>();
    final result = await AddressFormSheet.show(parentContext);
    if (result == true && mounted) {
      await addressCubit.fetchAddresses();
      if (!mounted) return;
      if (addressCubit.state.addresses.isNotEmpty) {
        final newAddr = addressCubit.state.defaultAddress ??
            addressCubit.state.addresses.first;
        await checkoutCubit.selectAddress(newAddr);
      }
    }
  }

  Widget _buildShippingMethodSection(
    BuildContext context,
    CheckoutState state,
  ) {
    final preview = state.preview;
    final methods = preview?.shippingMethods ?? const [];
    final selectedCode = state.shippingMethod;
    final symbol = preview?.currency.symbol ?? 'ر.ي';
    final isRefreshing = state.isLoadingPreview;

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.local_shipping_outlined,
                color: AppColors.primaryDark,
                size: 20.sp,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  'طريقة التوصيل',
                  style: AppTypography.titleSmall.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (isRefreshing)
                SizedBox(
                  width: 16.w,
                  height: 16.w,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.primaryDark,
                  ),
                ),
            ],
          ),
          SizedBox(height: 10.h),
          if (methods.isEmpty && !isRefreshing)
            Text(
              'لا توجد طرق توصيل متاحة حالياً',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            )
          else if (methods.isEmpty && isRefreshing)
            Text(
              'جاري تحديث طرق التوصيل...',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            )
          else
            ...methods.map((method) {
              final isSelected = method.code == selectedCode;
              final priceLabel = method.cost <= 0
                  ? 'مجاناً'
                  : '${method.cost.toStringAsFixed(0)} $symbol';

              return Padding(
                padding: EdgeInsets.only(bottom: 8.h),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14.r),
                  onTap: method.enabled && !isRefreshing
                      ? () => context
                          .read<CheckoutCubit>()
                          .selectShippingMethod(method.code)
                      : null,
                  child: Container(
                    padding: EdgeInsets.all(12.w),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.surfaceSoft
                          : Colors.white,
                      borderRadius: BorderRadius.circular(14.r),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.primaryDark.withValues(alpha: 0.45)
                            : AppColors.border,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isSelected
                              ? Icons.radio_button_checked_rounded
                              : Icons.radio_button_off_rounded,
                          color: isSelected
                              ? AppColors.primaryDark
                              : AppColors.textSecondary,
                          size: 20.sp,
                        ),
                        SizedBox(width: 10.w),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                method.nameAr,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.bodyMedium.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text(
                                method.estimatedDelivery,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Flexible(
                          child: Text(
                            priceLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.bodyMedium.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _buildPaymentMethodSection(
    BuildContext context,
    CheckoutState state,
  ) {
    final preview = state.preview;
    final methods =
        preview?.paymentMethods.where((m) => m.enabled).toList() ?? const [];
    final selectedCode = state.paymentMethod;
    final selected = state.selectedPaymentMethodModel;
    final busy = state.isInitiatingPayment || state.isSubmitting;

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.payments_outlined,
                color: AppColors.primaryDark,
                size: 20.sp,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  'طريقة الدفع',
                  style: AppTypography.titleSmall.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (state.isInitiatingPayment)
                SizedBox(
                  width: 16.w,
                  height: 16.w,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.primaryDark,
                  ),
                ),
            ],
          ),
          SizedBox(height: 10.h),
          if (methods.isEmpty)
            Text(
              'لا توجد طرق دفع متاحة حالياً',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            )
          else
            ...methods.map((method) {
              final isSelected = method.code == selectedCode;
              return Padding(
                padding: EdgeInsets.only(bottom: 8.h),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14.r),
                  onTap: method.enabled && !busy
                      ? () => _handlePaymentMethodTap(context, method)
                      : null,
                  child: Container(
                    padding: EdgeInsets.all(12.w),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.surfaceSoft
                          : Colors.white,
                      borderRadius: BorderRadius.circular(14.r),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.primaryDark.withValues(alpha: 0.45)
                            : AppColors.border,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isSelected
                              ? Icons.radio_button_checked_rounded
                              : Icons.radio_button_off_rounded,
                          color: isSelected
                              ? AppColors.primaryDark
                              : AppColors.textSecondary,
                          size: 20.sp,
                        ),
                        SizedBox(width: 10.w),
                        if (method.logoUrl != null &&
                            method.logoUrl!.isNotEmpty) ...[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8.r),
                            child: Image.network(
                              method.logoUrl!,
                              width: 32.w,
                              height: 32.w,
                              fit: BoxFit.contain,
                              errorBuilder: (_, __, ___) => Icon(
                                Icons.account_balance_wallet_outlined,
                                size: 22.sp,
                                color: AppColors.primaryDark,
                              ),
                            ),
                          ),
                          SizedBox(width: 10.w),
                        ],
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                method.nameAr,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppTypography.bodyMedium.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if ((method.descriptionAr ?? '').isNotEmpty)
                                Text(
                                  method.descriptionAr!,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          if (selected?.isStep1 == true) ...[
            SizedBox(height: 8.h),
            Text(
              state.hasJaibCredentials
                  ? 'تم حفظ رمز الشراء. اضغط جيب مجدداً للتعديل.'
                  : 'اضغط على جيب لإدخال رمز الشراء.',
              style: AppTypography.bodySmall.copyWith(
                color: state.hasJaibCredentials
                    ? AppColors.success
                    : AppColors.textSecondary,
              ),
            ),
          ],
          if (selected?.isStep2 == true) ...[
            SizedBox(height: 8.h),
            Text(
              state.isInitiatingPayment
                  ? 'جاري إنشاء فاتورة الدفع...'
                  : state.isAwaitingPaymentOtp
                      ? 'بانتظار رمز التحقق — أكمل من النافذة المنبثقة.'
                      : 'عند الاختيار يتم إنشاء فاتورة مؤقتة لدى المحفظة.',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            if (state.isAwaitingPaymentOtp)
              TextButton(
                onPressed: busy ? null : () => _openOtpSheet(context),
                child: Text(
                  'إدخال رمز التحقق',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.primaryDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildGiftWrappingSection(BuildContext context, CheckoutState state) {
    final gift = state.preview?.giftWrapping;
    final maxLength = gift?.maxLength ?? 250;
    final messageEnabled = gift?.messageEnabled ?? false;

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Row(
                  children: [
                    Icon(
                      Icons.card_giftcard_rounded,
                      color: AppColors.primaryDark,
                      size: 20.sp,
                    ),
                    SizedBox(width: 8.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'تغليف كهدية فاخرة',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.titleSmall.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            'خدمة مجانية بالكامل',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.labelSmall.copyWith(
                              color: AppColors.success,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Switch.adaptive(
                value: state.giftWrappingRequested,
                activeTrackColor: AppColors.primaryDark,
                onChanged: (val) {
                  context.read<CheckoutCubit>().toggleGiftWrapping(val);
                },
              ),
            ],
          ),
          if (state.giftWrappingRequested && messageEnabled) ...[
            SizedBox(height: 12.h),
            Text(
              'رسالة الإهداء (اختياري - $maxLength حرف)',
              style: AppTypography.labelSmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            SizedBox(height: 6.h),
            LuxuryTextField(
              controller: _giftMessageController,
              hintText: 'اكتب كلماتك الرقيقة هنا لتصل مع هديتك...',
              maxLines: 3,
              onChanged: (text) {
                final trimmed =
                    text.length > maxLength ? text.substring(0, maxLength) : text;
                context.read<CheckoutCubit>().setGiftMessage(trimmed);
              },
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildNotesSection(BuildContext context, CheckoutState state) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.note_alt_outlined,
                color: AppColors.primaryDark,
                size: 20.sp,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  'ملاحظات إضافية على الطلب (اختياري)',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.titleSmall.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 8.h),
          LuxuryTextField(
            controller: _notesController,
            hintText: 'أي تفاصيل خاصة بالتوصيل أو التوقيت...',
            maxLines: 2,
            onChanged: (text) {
              final trimmed = text.length > 500 ? text.substring(0, 500) : text;
              context.read<CheckoutCubit>().setNotes(trimmed);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildItemsPreviewSection(CheckoutPreviewModel preview) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'المنتجات (${preview.itemsCount})',
            style: AppTypography.titleSmall.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          SizedBox(height: 8.h),
          ...preview.items.map((item) {
            final imageUrl = item.primaryImageUrl?.trim() ?? '';
            return Padding(
              padding: EdgeInsets.symmetric(vertical: 6.h),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10.r),
                    child: SizedBox(
                      width: 52.w,
                      height: 52.w,
                      child: imageUrl.isNotEmpty
                          ? CachedImageWidget(
                              imageUrl: imageUrl,
                              width: 52.w,
                              height: 52.w,
                              fit: BoxFit.cover,
                            )
                          : ColoredBox(
                              color: AppColors.surfaceSoft,
                              child: Icon(
                                Icons.image_outlined,
                                color: AppColors.textSecondary,
                                size: 22.sp,
                              ),
                            ),
                    ),
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.productName,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.bodySmall.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (item.variantName.trim().isNotEmpty)
                          Text(
                            item.variantName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.bodySmall.copyWith(
                              color: AppColors.textSecondary,
                            ),
                          ),
                        Text(
                          'الكمية: ${item.quantity.toInt()}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                        if (item.warning != null)
                          Text(
                            item.warning!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.bodySmall.copyWith(
                              color: AppColors.error,
                              fontSize: 11.sp,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Text(
                    '${item.lineTotal.toStringAsFixed(0)} ${preview.currency.symbol}',
                    style: AppTypography.bodySmall.copyWith(
                      fontWeight: FontWeight.w800,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildCouponSection(BuildContext context, CheckoutState state) {
    final preview = state.preview;
    final symbol = preview?.currency.symbol ?? 'ر.ي';
    final appliedCouponCode = state.couponCode ?? preview?.pricing.couponCode;
    final hasAppliedCoupon =
        appliedCouponCode != null && appliedCouponCode.trim().isNotEmpty;
    final couponDiscount = preview?.pricing.couponDiscount ?? 0.0;

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.confirmation_number_outlined,
                color: AppColors.goldDark,
                size: 20.sp,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  'كود الخصم / الكوبون',
                  style: AppTypography.titleSmall.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          if (hasAppliedCoupon) ...[
            Container(
              padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14.r),
                border: Border.all(
                  color: AppColors.success.withValues(alpha: 0.3),
                ),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle_rounded,
                      size: 20.w, color: AppColors.success),
                  SizedBox(width: 8.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'تم تطبيق الكوبون ($appliedCouponCode)',
                          style: TextStyle(
                            fontSize: 11.5.sp,
                            color: AppColors.success,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (couponDiscount > 0) ...[
                          SizedBox(height: 2.h),
                          Text(
                            'خصم الكوبون: -${couponDiscount.toStringAsFixed(0)} $symbol',
                            style: TextStyle(
                              fontSize: 10.sp,
                              color: AppColors.success,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  IconButton(
                    icon: Icon(Icons.close_rounded,
                        size: 18.w, color: AppColors.error),
                    onPressed: state.isApplyingCoupon
                        ? null
                        : () async {
                            final checkoutCubit = context.read<CheckoutCubit>();
                            final cartCubit = context.read<CartCubit>();
                            await checkoutCubit.removeCoupon();
                            if (!mounted) return;
                            cartCubit.removeCoupon();
                          },
                    tooltip: 'إزالة الكوبون',
                  ),
                ],
              ),
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: LuxuryTextField(
                    controller: _couponController,
                    hintText: 'أدخل كود الكوبون',
                    prefixIcon: Icon(Icons.sell_outlined,
                        size: 18.w, color: AppColors.goldDark),
                  ),
                ),
                SizedBox(width: 8.w),
                LuxuryButton(
                  text: state.isApplyingCoupon ? 'جاري التحقق...' : 'تطبيق',
                  variant: LuxuryButtonVariant.secondary,
                  onPressed: state.isApplyingCoupon
                      ? null
                      : () async {
                          final code = _couponController.text.trim();
                          if (code.isEmpty) return;
                          final checkoutCubit = context.read<CheckoutCubit>();
                          final cartCubit = context.read<CartCubit>();
                          final success = await checkoutCubit.applyCoupon(code);
                          if (!mounted) return;
                          if (success) {
                            _couponController.clear();
                            cartCubit.applyPromoCode(code);
                          }
                        },
                ),
              ],
            ),
            if (state.couponError != null) ...[
              SizedBox(height: 6.h),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 4.w),
                child: Text(
                  state.couponError!,
                  style: TextStyle(
                    fontSize: 11.sp,
                    color: AppColors.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildTotalsSection(CheckoutPreviewModel? preview) {
    if (preview == null) {
      return Container(
        padding: EdgeInsets.all(18.w),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20.r),
          border: Border.all(color: AppColors.border, width: 0.8),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 16.w,
              height: 16.w,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.primaryDark,
              ),
            ),
            SizedBox(width: 10.w),
            Text(
              'جاري تحديث رسوم التوصيل...',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      );
    }
    final pricing = preview.pricing;
    final symbol = preview.currency.symbol;

    return Container(
      padding: EdgeInsets.all(18.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20.r),
        border: Border.all(color: AppColors.border, width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'ملخص الفاتورة',
            style: AppTypography.titleSmall.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          SizedBox(height: 12.h),
          _buildRow('المجموع الفرعي', '${pricing.subtotal.toStringAsFixed(0)} $symbol'),
          if (pricing.itemsDiscount > 0) ...[
            SizedBox(height: 8.h),
            _buildRow(
              'خصم المنتجات',
              '- ${pricing.itemsDiscount.toStringAsFixed(0)} $symbol',
              isDiscount: true,
            ),
          ],
          if (pricing.couponDiscount > 0) ...[
            SizedBox(height: 8.h),
            _buildRow(
              'خصم الكوبون${pricing.couponCode != null ? ' (${pricing.couponCode})' : ''}',
              '- ${pricing.couponDiscount.toStringAsFixed(0)} $symbol',
              isDiscount: true,
            ),
          ] else if (pricing.discountAmount > 0 && pricing.itemsDiscount <= 0) ...[
            SizedBox(height: 8.h),
            _buildRow(
              'إجمالي الخصم',
              '- ${pricing.discountAmount.toStringAsFixed(0)} $symbol',
              isDiscount: true,
            ),
          ],
          SizedBox(height: 8.h),
          _buildRow(
            'رسوم التوصيل',
            pricing.deliveryFee == 0
                ? 'مجاناً'
                : '${pricing.deliveryFee.toStringAsFixed(0)} $symbol',
          ),
          if (preview.giftWrapping?.enabled == true &&
              preview.giftWrapping?.requested == true) ...[
            SizedBox(height: 8.h),
            _buildRow('تغليف الهدية', 'مجاناً'),
          ],
          Divider(color: AppColors.border, height: 24.h),
          Row(
            children: [
              Expanded(
                child: Text(
                  'المجموع النهائي',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.titleSmall.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Flexible(
                child: Text(
                  '${pricing.total.toStringAsFixed(0)} $symbol',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: AppTypography.titleMedium.copyWith(
                    fontWeight: FontWeight.w900,
                    color: AppColors.primaryDark,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildRow(String label, String value, {bool isDiscount = false}) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ),
        SizedBox(width: 8.w),
        Flexible(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.end,
            style: AppTypography.bodySmall.copyWith(
              fontWeight: FontWeight.w600,
              color: isDiscount ? AppColors.discountBadge : AppColors.textPrimary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBottomBar(BuildContext context, CheckoutState state) {
    final preview = state.preview;
    final method = state.selectedPaymentMethodModel;
    final isStep2 = method?.isStep2 == true;
    final step1Ready =
        method?.isStep1 != true || state.hasJaibCredentials;
    final canSubmit = state.selectedAddress != null &&
        (preview?.canCheckout ?? false) &&
        !state.isSubmitting &&
        !state.isInitiatingPayment &&
        !state.isAwaitingPaymentOtp &&
        !isStep2 &&
        step1Ready;

    final buttonText = state.isSubmitting
        ? 'جاري تأكيد الطلب...'
        : state.isInitiatingPayment
            ? 'جاري بدء الدفع...'
            : state.isAwaitingPaymentOtp
                ? 'بانتظار رمز التحقق'
                : isStep2
                    ? 'اختر فلوسك لإرسال فاتورة الدفع'
                    : 'تأكيد وإتمام الطلب (${preview?.pricing.total.toStringAsFixed(0) ?? '—'} ${preview?.currency.symbol ?? 'ر.ي'})';

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.border, width: 0.8)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: LuxuryButton(
          text: buttonText,
          width: double.infinity,
          isLoading: state.isSubmitting || state.isInitiatingPayment,
          onPressed: canSubmit
              ? () => context.read<CheckoutCubit>().placeOrder()
              : null,
        ),
      ),
    );
  }
}
