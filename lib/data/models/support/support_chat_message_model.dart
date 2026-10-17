import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:equatable/equatable.dart';

class SupportChatMessageModel extends Equatable {
  const SupportChatMessageModel({
    required this.id,
    required this.senderType,
    required this.senderUid,
    required this.type,
    this.text,
    this.attachmentPath,
    this.localFilePath,
    this.clientMessageId,
    this.createdAt,
    this.pending = false,
    this.failed = false,
  });

  final String id;
  final String senderType;
  final String senderUid;
  final String type;
  final String? text;
  final String? attachmentPath;
  final String? localFilePath;
  final String? clientMessageId;
  final DateTime? createdAt;
  final bool pending;
  final bool failed;

  bool get isMine => senderType == 'customer';
  bool get isImage => type == 'image';
  bool get hasAttachment =>
      isImage &&
      ((attachmentPath != null && attachmentPath!.isNotEmpty) ||
          (localFilePath != null && localFilePath!.isNotEmpty));

  factory SupportChatMessageModel.fromJson(Map<String, dynamic> json) {
    return SupportChatMessageModel(
      id: (json['id'] ?? '').toString(),
      senderType: (json['sender_type'] ?? '').toString(),
      senderUid: (json['sender_uid'] ?? '').toString(),
      type: (json['type'] ?? 'text').toString(),
      text: json['text'] as String?,
      attachmentPath: json['attachment_path'] as String?,
      localFilePath: json['local_file_path'] as String?,
      clientMessageId: json['client_message_id'] as String?,
      createdAt: DateTime.tryParse('${json['created_at'] ?? ''}'),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'sender_type': senderType,
      'sender_uid': senderUid,
      'type': type,
      'text': text,
      'attachment_path': attachmentPath,
      'local_file_path': localFilePath,
      'client_message_id': clientMessageId,
      'created_at': createdAt?.toIso8601String(),
    };
  }

  factory SupportChatMessageModel.fromFirestore(
    String id,
    Map<String, dynamic> data,
  ) {
    return SupportChatMessageModel(
      id: id,
      senderType: (data['sender_type'] ?? '').toString(),
      senderUid: (data['sender_uid'] ?? '').toString(),
      type: (data['type'] ?? 'text').toString(),
      text: data['text'] as String?,
      attachmentPath: data['attachment_path'] as String?,
      clientMessageId: data['client_message_id'] as String?,
      createdAt: _parseFirestoreDate(data['created_at']),
    );
  }

  static DateTime? _parseFirestoreDate(Object? created) {
    if (created == null) return null;
    if (created is Timestamp) return created.toDate();
    if (created is DateTime) return created;
    if (created is int) {
      return created > 20000000000
          ? DateTime.fromMillisecondsSinceEpoch(created)
          : DateTime.fromMillisecondsSinceEpoch(created * 1000);
    }
    if (created is double) {
      return _parseFirestoreDate(created.round());
    }
    return DateTime.tryParse(created.toString());
  }

  SupportChatMessageModel copyWith({
    bool? pending,
    bool? failed,
    String? id,
    String? attachmentPath,
    String? localFilePath,
    String? text,
  }) {
    return SupportChatMessageModel(
      id: id ?? this.id,
      senderType: senderType,
      senderUid: senderUid,
      type: type,
      text: text ?? this.text,
      attachmentPath: attachmentPath ?? this.attachmentPath,
      localFilePath: localFilePath ?? this.localFilePath,
      clientMessageId: clientMessageId,
      createdAt: createdAt,
      pending: pending ?? this.pending,
      failed: failed ?? this.failed,
    );
  }

  @override
  List<Object?> get props =>
      [id, senderType, type, text, attachmentPath, clientMessageId, pending, failed];
}
