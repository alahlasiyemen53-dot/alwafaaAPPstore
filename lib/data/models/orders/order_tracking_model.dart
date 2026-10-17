import 'package:equatable/equatable.dart';

class OrderTrackingModel extends Equatable {
  final String orderNumber;
  final String statusCode;
  final String statusLabel;
  final DateTime? occurredAt;
  final DateTime? customerConfirmDeadlineAt;
  final bool canConfirmDelivery;
  final bool canRate;
  final List<OrderTrackingEventModel> timeline;

  const OrderTrackingModel({
    required this.orderNumber,
    required this.statusCode,
    required this.statusLabel,
    this.occurredAt,
    this.customerConfirmDeadlineAt,
    this.canConfirmDelivery = false,
    this.canRate = false,
    required this.timeline,
  });

  factory OrderTrackingModel.fromJson(Map<String, dynamic> json) {
    return OrderTrackingModel(
      orderNumber: json['order_number'] as String? ?? '',
      statusCode: json['status'] as String? ?? 'under_review',
      statusLabel: json['label_ar'] as String? ?? 'قيد المراجعة',
      occurredAt: json['occurred_at'] != null
          ? DateTime.tryParse(json['occurred_at'].toString())
          : null,
      customerConfirmDeadlineAt: json['customer_confirm_deadline_at'] != null
          ? DateTime.tryParse(json['customer_confirm_deadline_at'].toString())
          : null,
      canConfirmDelivery: json['can_confirm_delivery'] as bool? ?? false,
      canRate: json['can_rate'] as bool? ?? false,
      timeline: (json['timeline'] as List<dynamic>?)
              ?.map((e) =>
                  OrderTrackingEventModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  @override
  List<Object?> get props => [
        orderNumber,
        statusCode,
        statusLabel,
        occurredAt,
        customerConfirmDeadlineAt,
        canConfirmDelivery,
        canRate,
        timeline,
      ];
}

class OrderTrackingEventModel extends Equatable {
  final String statusCode;
  final String statusLabel;
  final DateTime? occurredAt;

  const OrderTrackingEventModel({
    required this.statusCode,
    required this.statusLabel,
    this.occurredAt,
  });

  factory OrderTrackingEventModel.fromJson(Map<String, dynamic> json) {
    return OrderTrackingEventModel(
      statusCode: json['status'] as String? ?? '',
      statusLabel: json['label_ar'] as String? ?? '',
      occurredAt: json['occurred_at'] != null
          ? DateTime.tryParse(json['occurred_at'].toString())
          : null,
    );
  }

  @override
  List<Object?> get props => [statusCode, statusLabel, occurredAt];
}
