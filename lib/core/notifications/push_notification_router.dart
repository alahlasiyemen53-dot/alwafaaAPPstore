import 'dart:convert';

import 'package:flutter/material.dart';

import 'package:store/features/discovery/presentation/screens/collection_detail_screen.dart';
import 'package:store/features/orders/presentation/screens/order_detail_screen.dart';
import 'package:store/features/support_chat/presentation/screens/support_chat_inbox_screen.dart';

/// Routes transactional push/local notification taps into the app navigator.
class PushNotificationRouter {
  PushNotificationRouter._();

  static final GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  static void handleData(Map<String, dynamic> data, {int retryCount = 0}) {
    if (data.isEmpty) return;
    debugPrint('PushNotificationRouter: handling notification data: $data (attempt: $retryCount)');

    final context = navigatorKey.currentContext;
    if (context == null) {
      if (retryCount < 6) {
        debugPrint('PushNotificationRouter: context is null, retrying in 300ms...');
        Future<void>.delayed(const Duration(milliseconds: 300), () {
          handleData(data, retryCount: retryCount + 1);
        });
      } else {
        debugPrint('PushNotificationRouter: gave up waiting for navigator context.');
      }
      return;
    }

    final destinationType = (data['destination_type'] ?? data['destination'])
        ?.toString()
        .toLowerCase();
    final type = data['type']?.toString().toUpperCase() ?? '';
    final destinationId = _readInt(data['destination_id']);
    final orderId = destinationId ?? _readInt(data['order_id']);
    final openRating = data['open_rating']?.toString() == '1' ||
        type == 'ORDER_AWAITING_CUSTOMER_CONFIRMATION' ||
        type == 'ORDER_DELIVERED';

    if (destinationType == 'support_chat' || type == 'SUPPORT_CHAT_MESSAGE') {
      final conversationId = (data['conversation_id'] ?? data['destination_id'])
          ?.toString();
      SupportChatInboxScreen.open(
        context,
        conversationId: conversationId,
      );
      return;
    }

    if (destinationType == 'collection' &&
        destinationId != null &&
        destinationId > 0) {
      CollectionDetailScreen.open(context, destinationId);
      return;
    }

    final isOrder = destinationType == 'order' ||
        (destinationType == null && orderId != null && orderId > 0) ||
        type.startsWith('ORDER_');

    if (isOrder && orderId != null && orderId > 0) {
      debugPrint('PushNotificationRouter: navigating to OrderDetailScreen(orderId: $orderId)');
      OrderDetailScreen.open(
        context,
        orderId,
        openRating: openRating,
      );
      return;
    }

    debugPrint('PushNotificationRouter: unhandled notification payload: $data');
  }

  static void handlePayloadString(String? payload) {
    if (payload == null || payload.trim().isEmpty) return;
    final decoded = decodePayload(payload);
    if (decoded.isEmpty) return;
    handleData(decoded);
  }

  static Map<String, dynamic> decodePayload(String payload) {
    try {
      final dynamic decoded = jsonDecode(payload);
      if (decoded is Map) {
        return decoded.map(
          (key, value) => MapEntry(key.toString(), value),
        );
      }
    } catch (_) {
      // Fall through to key=value parsing for legacy payloads.
    }

    final cleaned = payload.trim();
    if (cleaned.startsWith('{') && cleaned.endsWith('}')) {
      final inner = cleaned.substring(1, cleaned.length - 1);
      final map = <String, dynamic>{};
      for (final part in inner.split(',')) {
        final idx = part.indexOf(':');
        if (idx <= 0) continue;
        final key = part.substring(0, idx).trim();
        var value = part.substring(idx + 1).trim();
        if ((value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))) {
          value = value.substring(1, value.length - 1);
        }
        map[key] = value;
      }
      return map;
    }

    return const {};
  }

  static int? _readInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }
}
