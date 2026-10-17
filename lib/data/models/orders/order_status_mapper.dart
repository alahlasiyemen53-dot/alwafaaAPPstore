import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';

class OrderStatusInfo {
  final String code;
  final String label;
  final IconData icon;
  final Color color;
  final Color backgroundColor;

  const OrderStatusInfo({
    required this.code,
    required this.label,
    required this.icon,
    required this.color,
    required this.backgroundColor,
  });
}

class OrderStatusMapper {
  OrderStatusMapper._();

  /// Active = still in fulfillment (not delivered / cancelled / returned / failed).
  static bool isActiveStatus(String? rawStatus) {
    switch (rawStatus?.toLowerCase().trim() ?? '') {
      case 'delivered':
      case 'cancelled':
      case 'returned':
      case 'returned_to_warehouse':
      case 'delivery_failed':
      case 'failed':
        return false;
      default:
        return true;
    }
  }

  static OrderStatusInfo getInfo(String? rawStatus) {
    final status = rawStatus?.toLowerCase().trim() ?? '';

    switch (status) {
      case 'under_review':
      case 'draft':
      case 'preparing':
        return OrderStatusInfo(
          code: status,
          label: 'قيد المراجعة',
          icon: Icons.hourglass_top_rounded,
          color: AppColors.warning,
          backgroundColor: AppColors.warning.withValues(alpha: 0.12),
        );

      case 'confirmed':
        return OrderStatusInfo(
          code: status,
          label: 'تم تأكيد طلبك',
          icon: Icons.verified_outlined,
          color: AppColors.info,
          backgroundColor: AppColors.info.withValues(alpha: 0.12),
        );

      case 'ready_for_delivery':
      case 'assigned_to_driver':
      case 'with_courier':
        return OrderStatusInfo(
          code: status,
          label: 'جاهز للتوصيل',
          icon: Icons.local_shipping_outlined,
          color: AppColors.primaryDark,
          backgroundColor: AppColors.surfaceMuted,
        );

      case 'delivered_by_courier':
      case 'awaiting_customer_confirmation':
        return OrderStatusInfo(
          code: status,
          label: 'تم تسليم الطلب من قبل المندوب',
          icon: Icons.delivery_dining_rounded,
          color: AppColors.success,
          backgroundColor: AppColors.success.withValues(alpha: 0.12),
        );

      case 'delivered':
        return OrderStatusInfo(
          code: status,
          label: 'مكتمل',
          icon: Icons.check_circle_rounded,
          color: AppColors.success,
          backgroundColor: AppColors.success.withValues(alpha: 0.12),
        );

      case 'delivery_failed':
      case 'failed':
        return OrderStatusInfo(
          code: status,
          label: 'تعذر التوصيل',
          icon: Icons.error_outline_rounded,
          color: AppColors.error,
          backgroundColor: AppColors.error.withValues(alpha: 0.12),
        );

      case 'returned':
      case 'returned_to_warehouse':
        return OrderStatusInfo(
          code: status,
          label: 'تم إرجاع الطلب',
          icon: Icons.assignment_return_outlined,
          color: AppColors.textSecondary,
          backgroundColor: AppColors.surfaceSoft,
        );

      case 'cancelled':
        return OrderStatusInfo(
          code: status,
          label: 'تم إلغاء الطلب',
          icon: Icons.cancel_outlined,
          color: AppColors.textMuted,
          backgroundColor: AppColors.borderLight,
        );

      default:
        return OrderStatusInfo(
          code: status,
          label: 'قيد المتابعة',
          icon: Icons.info_outline_rounded,
          color: AppColors.primaryDark,
          backgroundColor: AppColors.surfaceSoft,
        );
    }
  }
}
