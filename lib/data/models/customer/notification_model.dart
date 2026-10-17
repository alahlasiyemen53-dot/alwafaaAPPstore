import 'package:equatable/equatable.dart';

class CustomerNotificationModel extends Equatable {
  final int id;
  final String type;
  final String title;
  final String body;
  final String destinationType;
  final int? destinationId;
  /// String destinations (e.g. support chat UUID) when not a numeric id.
  final String? destinationKey;
  final bool openRating;
  final DateTime? readAt;
  final DateTime? createdAt;

  const CustomerNotificationModel({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.destinationType = 'none',
    this.destinationId,
    this.destinationKey,
    this.openRating = false,
    this.readAt,
    this.createdAt,
  });

  bool get isUnread => readAt == null;

  factory CustomerNotificationModel.fromJson(Map<String, dynamic> json) {
    final destId = json['destination_id'];
    final destRaw = destId?.toString();
    final asInt = destId is int
        ? destId
        : int.tryParse(destRaw ?? '');
    return CustomerNotificationModel(
      id: json['id'] as int? ?? 0,
      type: json['type'] as String? ?? '',
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      destinationType: json['destination_type'] as String? ?? 'none',
      destinationId: asInt,
      destinationKey: (asInt == null && destRaw != null && destRaw.isNotEmpty)
          ? destRaw
          : (json['conversation_id']?.toString()),
      openRating: json['open_rating'] == true ||
          json['open_rating']?.toString() == '1',
      readAt: DateTime.tryParse(json['read_at'] as String? ?? ''),
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
    );
  }

  CustomerNotificationModel copyWith({DateTime? readAt}) {
    return CustomerNotificationModel(
      id: id,
      type: type,
      title: title,
      body: body,
      destinationType: destinationType,
      destinationId: destinationId,
      destinationKey: destinationKey,
      openRating: openRating,
      readAt: readAt ?? this.readAt,
      createdAt: createdAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        type,
        title,
        body,
        destinationType,
        destinationId,
        destinationKey,
        openRating,
        readAt,
        createdAt,
      ];
}

class NotificationsPaginationModel extends Equatable {
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const NotificationsPaginationModel({
    this.currentPage = 1,
    this.perPage = 20,
    this.total = 0,
    this.lastPage = 1,
    this.hasMore = false,
  });

  const NotificationsPaginationModel.empty()
      : currentPage = 1,
        perPage = 20,
        total = 0,
        lastPage = 1,
        hasMore = false;

  factory NotificationsPaginationModel.fromJson(Map<String, dynamic> json) {
    return NotificationsPaginationModel(
      currentPage: json['current_page'] as int? ?? 1,
      perPage: json['per_page'] as int? ?? 20,
      total: json['total'] as int? ?? 0,
      lastPage: json['last_page'] as int? ?? 1,
      hasMore: json['has_more'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [currentPage, perPage, total, lastPage, hasMore];
}

class NotificationsResponseModel extends Equatable {
  final List<CustomerNotificationModel> items;
  final int unreadCount;
  final NotificationsPaginationModel pagination;

  const NotificationsResponseModel({
    this.items = const [],
    this.unreadCount = 0,
    this.pagination = const NotificationsPaginationModel.empty(),
  });

  factory NotificationsResponseModel.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    final items = raw is List
        ? raw
            .whereType<Map>()
            .map((e) => CustomerNotificationModel.fromJson(
                Map<String, dynamic>.from(e)))
            .toList()
        : <CustomerNotificationModel>[];

    return NotificationsResponseModel(
      items: items,
      unreadCount: json['unread_count'] as int? ?? 0,
      pagination: json['pagination'] is Map<String, dynamic>
          ? NotificationsPaginationModel.fromJson(
              json['pagination'] as Map<String, dynamic>)
          : const NotificationsPaginationModel.empty(),
    );
  }

  @override
  List<Object?> get props => [items, unreadCount, pagination];
}
