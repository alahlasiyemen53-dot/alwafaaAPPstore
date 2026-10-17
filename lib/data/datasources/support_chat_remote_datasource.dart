import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:store/core/network/api_endpoints.dart';
import 'package:store/core/network/dio_client.dart';
import 'package:store/data/models/support/support_chat_message_model.dart';
import 'package:store/data/models/support/support_conversation_model.dart';

class SupportChatRemoteDatasource {
  SupportChatRemoteDatasource({DioClient? dioClient})
      : _dio = dioClient ?? DioClient();

  final DioClient _dio;

  Future<({String token, String uid})> fetchFirebaseToken() async {
    final response = await _dio.post(ApiEndpoints.supportFirebaseToken);
    final data = Map<String, dynamic>.from(response.data['data'] as Map);
    return (
      token: data['token'] as String,
      uid: data['uid'] as String,
    );
  }

  Future<List<SupportConversationModel>> listConversations() async {
    final response = await _dio.get(ApiEndpoints.supportConversations);
    final data = response.data['data'];
    final rawItems = data is Map ? data['items'] : null;
    final items = rawItems is List ? rawItems : const [];
    return items
        .whereType<Map>()
        .map((e) => SupportConversationModel.fromJson(
              Map<String, dynamic>.from(e),
            ))
        .toList();
  }

  Future<SupportConversationModel> createConversation({int? relatedOrderId}) async {
    final response = await _dio.post(
      ApiEndpoints.supportConversations,
      data: {
        if (relatedOrderId != null) 'related_order_id': relatedOrderId,
      },
    );
    return SupportConversationModel.fromJson(
      Map<String, dynamic>.from(response.data['data'] as Map),
    );
  }

  Future<SupportConversationModel> getConversation(String conversationId) async {
    final response = await _dio.get(
      ApiEndpoints.supportConversation(conversationId),
    );
    return SupportConversationModel.fromJson(
      Map<String, dynamic>.from(response.data['data'] as Map),
    );
  }

  Future<List<SupportChatMessageModel>> listMessages(String conversationId) async {
    final response = await _dio.get(
      ApiEndpoints.supportConversationMessages(conversationId),
    );
    final data = response.data['data'];
    final rawItems = data is Map ? data['items'] : null;
    final items = rawItems is List ? rawItems : const [];
    return items
        .whereType<Map>()
        .map((e) => SupportChatMessageModel.fromJson(
              Map<String, dynamic>.from(e),
            ))
        .toList();
  }

  Future<SupportChatMessageModel> sendText({
    required String conversationId,
    required String body,
    required String clientMessageId,
  }) async {
    final response = await _dio.post(
      ApiEndpoints.supportConversationMessages(conversationId),
      data: {
        'body': body,
        'client_message_id': clientMessageId,
      },
    );
    return SupportChatMessageModel.fromJson(
      Map<String, dynamic>.from(response.data['data'] as Map),
    );
  }

  Future<SupportChatMessageModel> sendImage({
    required String conversationId,
    required String filePath,
    required String clientMessageId,
    String? body,
  }) async {
    final form = FormData.fromMap({
      'client_message_id': clientMessageId,
      if (body != null && body.isNotEmpty) 'body': body,
      'image': await MultipartFile.fromFile(filePath),
    });
    final response = await _dio.post(
      ApiEndpoints.supportConversationMessages(conversationId),
      data: form,
    );
    return SupportChatMessageModel.fromJson(
      Map<String, dynamic>.from(response.data['data'] as Map),
    );
  }

  Future<void> markRead(String conversationId) async {
    await _dio.post(ApiEndpoints.supportConversationRead(conversationId));
  }

  Future<Uint8List> fetchAttachmentBytes({
    required String conversationId,
    required String messageId,
  }) async {
    final response = await _dio.get(
      ApiEndpoints.supportConversationMessageAttachment(
        conversationId,
        messageId,
      ),
      options: Options(responseType: ResponseType.bytes),
    );
    final data = response.data;
    if (data is Uint8List) return data;
    if (data is List<int>) return Uint8List.fromList(data);
    throw StateError('Unexpected attachment payload.');
  }
}
