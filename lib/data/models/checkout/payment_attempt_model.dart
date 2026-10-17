import 'package:equatable/equatable.dart';

class PaymentAttemptModel extends Equatable {
  final String uuid;
  final String status;
  final String providerCode;
  final double amount;
  final String currencyCode;
  final DateTime? expiresAt;
  final String? customerPhone;
  final String paymentMethodCode;
  final String paymentMethodLabel;

  const PaymentAttemptModel({
    required this.uuid,
    required this.status,
    required this.providerCode,
    required this.amount,
    required this.currencyCode,
    this.expiresAt,
    this.customerPhone,
    required this.paymentMethodCode,
    required this.paymentMethodLabel,
  });

  factory PaymentAttemptModel.fromJson(Map<String, dynamic> json) {
    final method = json['payment_method'] as Map<String, dynamic>?;

    return PaymentAttemptModel(
      uuid: json['uuid'] as String? ?? '',
      status: json['status'] as String? ?? 'pending_otp',
      providerCode: json['provider_code'] as String? ?? '',
      amount: (json['amount'] as num?)?.toDouble() ?? 0.0,
      currencyCode: json['currency_code'] as String? ?? 'YER',
      expiresAt: json['expires_at'] != null
          ? DateTime.tryParse(json['expires_at'].toString())
          : null,
      customerPhone: json['customer_phone'] as String?,
      paymentMethodCode: method?['code'] as String? ?? '',
      paymentMethodLabel: method?['name_ar'] as String? ?? '',
    );
  }

  @override
  List<Object?> get props => [
        uuid,
        status,
        providerCode,
        amount,
        currencyCode,
        expiresAt,
        customerPhone,
        paymentMethodCode,
        paymentMethodLabel,
      ];
}
