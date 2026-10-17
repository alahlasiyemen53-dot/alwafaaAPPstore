import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:store/data/models/support/support_chat_message_model.dart';
import 'package:store/data/models/support/support_conversation_model.dart';

class SupportChatInboxCache {
  SupportChatInboxCache({SharedPreferences? prefs}) : _prefs = prefs;

  static const _key = 'support_chat_inbox_v1';
  static String _messagesKey(String conversationId) =>
      'support_chat_messages_v1_$conversationId';

  SharedPreferences? _prefs;

  Future<SharedPreferences> _ensurePrefs() async {
    return _prefs ??= await SharedPreferences.getInstance();
  }

  Future<void> save(List<SupportConversationModel> items) async {
    final prefs = await _ensurePrefs();
    final encoded = jsonEncode(items.map((e) => e.toJson()).toList());
    await prefs.setString(_key, encoded);
  }

  Future<List<SupportConversationModel>> load() async {
    final prefs = await _ensurePrefs();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map>()
          .map((e) => SupportConversationModel.fromJson(
                Map<String, dynamic>.from(e),
              ))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> saveMessages(
    String conversationId,
    List<SupportChatMessageModel> messages,
  ) async {
    if (conversationId.isEmpty) return;
    final prefs = await _ensurePrefs();
    // Cache up to 100 recent messages for optimal performance and storage
    final toSave = messages.length > 100
        ? messages.sublist(messages.length - 100)
        : messages;
    final encoded = jsonEncode(toSave.map((e) => e.toJson()).toList());
    await prefs.setString(_messagesKey(conversationId), encoded);
  }

  Future<List<SupportChatMessageModel>> loadMessages(
    String conversationId,
  ) async {
    if (conversationId.isEmpty) return const [];
    final prefs = await _ensurePrefs();
    final raw = prefs.getString(_messagesKey(conversationId));
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map>()
          .map((e) => SupportChatMessageModel.fromJson(
                Map<String, dynamic>.from(e),
              ))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> clear() async {
    final prefs = await _ensurePrefs();
    await prefs.remove(_key);
  }
}
