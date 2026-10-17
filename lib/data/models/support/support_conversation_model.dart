import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:equatable/equatable.dart';
import 'support_chat_message_model.dart';

class SupportConversationModel extends Equatable {
  const SupportConversationModel({
    required this.id,
    required this.status,
    this.relatedOrderId,
    this.lastMessageAt,
    this.lastMessagePreview,
    this.customerLastReadAt,
    this.supportLastReadAt,
  });

  final String id;
  final String status;
  final int? relatedOrderId;
  final DateTime? lastMessageAt;
  final String? lastMessagePreview;
  final DateTime? customerLastReadAt;
  final DateTime? supportLastReadAt;

  factory SupportConversationModel.fromJson(Map<String, dynamic> json) {
    return SupportConversationModel(
      id: (json['id'] ?? '').toString(),
      status: (json['status'] ?? 'open').toString(),
      relatedOrderId: (json['related_order_id'] as num?)?.toInt(),
      lastMessageAt: DateTime.tryParse('${json['last_message_at'] ?? ''}'),
      lastMessagePreview: json['last_message_preview'] as String?,
      customerLastReadAt:
          DateTime.tryParse('${json['customer_last_read_at'] ?? ''}'),
      supportLastReadAt:
          DateTime.tryParse('${json['support_last_read_at'] ?? ''}'),
    );
  }

  factory SupportConversationModel.fromFirestore(
    String id,
    Map<String, dynamic> data,
  ) {
    return SupportConversationModel(
      id: id,
      status: (data['status'] ?? 'open').toString(),
      relatedOrderId: (data['related_order_id'] as num?)?.toInt(),
      lastMessageAt: _parseFirestoreDate(data['last_message_at']),
      lastMessagePreview: data['last_message_preview'] as String?,
      customerLastReadAt: _parseFirestoreDate(data['customer_last_read_at']),
      supportLastReadAt: _parseFirestoreDate(data['support_last_read_at']),
    );
  }

  static DateTime? _parseFirestoreDate(Object? value) {
    if (value == null) return null;
    if (value is Timestamp) return value.toDate();
    if (value is DateTime) return value;
    if (value is int) {
      return value > 20000000000
          ? DateTime.fromMillisecondsSinceEpoch(value)
          : DateTime.fromMillisecondsSinceEpoch(value * 1000);
    }
    if (value is double) return _parseFirestoreDate(value.round());
    return DateTime.tryParse(value.toString());
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'status': status,
        'related_order_id': relatedOrderId,
        'last_message_at': lastMessageAt?.toIso8601String(),
        'last_message_preview': lastMessagePreview,
        'customer_last_read_at': customerLastReadAt?.toIso8601String(),
        'support_last_read_at': supportLastReadAt?.toIso8601String(),
      };

  bool get isClosed => status == 'closed';

  bool get isActive => status == 'open' || status == 'assigned';

  bool get hasContent {
    final preview = lastMessagePreview?.trim() ?? '';
    return preview.isNotEmpty || lastMessageAt != null;
  }

  /// Unread for the customer when support wrote after the last read marker.
  bool get hasUnread {
    if (lastMessageAt == null) return false;
    final readAt = customerLastReadAt;
    if (readAt == null) return hasContent;
    return lastMessageAt!.isAfter(readAt);
  }

  /// Whether a specific customer message has been read by support team.
  bool isMessageReadBySupport(SupportChatMessageModel message) {
    if (!message.isMine || message.pending || message.failed) return false;
    if (supportLastReadAt == null || message.createdAt == null) return false;
    // Allow small 1-second drift between server clocks
    return !message.createdAt!.isAfter(
      supportLastReadAt!.add(const Duration(seconds: 1)),
    );
  }

  String get statusLabelAr {
    switch (status) {
      case 'assigned':
        return 'قيد المعالجة';
      case 'closed':
        return 'مغلقة';
      case 'open':
      default:
        return 'مفتوحة';
    }
  }

  SupportConversationModel copyWith({
    String? status,
    int? relatedOrderId,
    DateTime? lastMessageAt,
    String? lastMessagePreview,
    DateTime? customerLastReadAt,
    DateTime? supportLastReadAt,
    bool clearRelatedOrder = false,
  }) {
    return SupportConversationModel(
      id: id,
      status: status ?? this.status,
      relatedOrderId:
          clearRelatedOrder ? null : (relatedOrderId ?? this.relatedOrderId),
      lastMessageAt: lastMessageAt ?? this.lastMessageAt,
      lastMessagePreview: lastMessagePreview ?? this.lastMessagePreview,
      customerLastReadAt: customerLastReadAt ?? this.customerLastReadAt,
      supportLastReadAt: supportLastReadAt ?? this.supportLastReadAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        status,
        relatedOrderId,
        lastMessageAt,
        lastMessagePreview,
        customerLastReadAt,
        supportLastReadAt,
      ];
}
