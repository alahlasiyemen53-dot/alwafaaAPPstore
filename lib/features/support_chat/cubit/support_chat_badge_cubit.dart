import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/support_chat/firebase_chat_session.dart';
import 'package:store/core/support_chat/support_chat_inbox_cache.dart';
import 'package:store/data/datasources/support_chat_remote_datasource.dart';
import 'package:store/data/models/support/support_conversation_model.dart';

final class SupportChatBadgeState extends Equatable {
  const SupportChatBadgeState({
    this.unreadCount = 0,
    this.conversations = const [],
  });

  final int unreadCount;
  final List<SupportConversationModel> conversations;

  SupportChatBadgeState copyWith({
    int? unreadCount,
    List<SupportConversationModel>? conversations,
  }) {
    return SupportChatBadgeState(
      unreadCount: unreadCount ?? this.unreadCount,
      conversations: conversations ?? this.conversations,
    );
  }

  @override
  List<Object?> get props => [unreadCount, conversations];
}

/// App-wide unread badge for support chat (account tile + app bar).
class SupportChatBadgeCubit extends Cubit<SupportChatBadgeState> {
  SupportChatBadgeCubit({
    SupportChatRemoteDatasource? datasource,
    FirebaseChatSession? session,
    SupportChatInboxCache? cache,
  })  : _datasource = datasource ?? SupportChatRemoteDatasource(),
        _session = session ?? FirebaseChatSession(),
        _cache = cache ?? SupportChatInboxCache(),
        super(const SupportChatBadgeState());

  final SupportChatRemoteDatasource _datasource;
  final FirebaseChatSession _session;
  final SupportChatInboxCache _cache;
  StreamSubscription<List<SupportConversationModel>>? _inboxSub;
  bool _enabled = false;

  Future<void> start() async {
    _enabled = true;
    final cached = await _cache.load();
    if (cached.isNotEmpty && !isClosed) {
      _emitFrom(cached);
    }
    await refresh();
    unawaited(_attachRealtime());
  }

  Future<void> stop() async {
    _enabled = false;
    await _inboxSub?.cancel();
    _inboxSub = null;
    if (!isClosed) emit(const SupportChatBadgeState());
  }

  Future<void> refresh() async {
    if (!_enabled) return;
    try {
      final items = await _datasource.listConversations();
      if (isClosed || !_enabled) return;
      await _cache.save(items);
      _emitFrom(items);
    } catch (e) {
      if (kDebugMode) debugPrint('SupportChatBadge.refresh: $e');
    }
  }

  void applyConversations(List<SupportConversationModel> items) {
    if (!_enabled || isClosed) return;
    _emitFrom(items);
    unawaited(_cache.save(items));
  }

  void markConversationRead(String conversationId) {
    if (!_enabled || isClosed) return;
    final now = DateTime.now();
    final next = state.conversations.map((c) {
      if (c.id != conversationId) return c;
      return c.copyWith(customerLastReadAt: now);
    }).toList();
    _emitFrom(next);
    unawaited(_cache.save(next));
  }

  Future<void> _attachRealtime() async {
    try {
      await _session.ensureSignedIn();
      if (!_enabled || isClosed) return;
      await _inboxSub?.cancel();
      _inboxSub = _session.watchConversations().listen(
        (live) {
          if (!_enabled || isClosed) return;
          // Preserve customerLastReadAt from API/cache when Firestore omits it.
          final merged = _mergeLiveWithLocal(live, state.conversations);
          _emitFrom(merged);
          unawaited(_cache.save(merged));
        },
        onError: (Object e) {
          if (kDebugMode) {
            debugPrint('SupportChatBadge inbox listener: $e');
          }
        },
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('SupportChatBadge realtime unavailable: $e');
      }
    }
  }

  List<SupportConversationModel> _mergeLiveWithLocal(
    List<SupportConversationModel> live,
    List<SupportConversationModel> local,
  ) {
    final byId = {for (final c in local) c.id: c};
    return live.map((row) {
      final prev = byId[row.id];
      if (prev == null) return row;
      return row.copyWith(
        customerLastReadAt: row.customerLastReadAt ?? prev.customerLastReadAt,
      );
    }).toList();
  }

  void _emitFrom(List<SupportConversationModel> items) {
    final unread = items.where((c) => c.hasUnread).length;
    emit(SupportChatBadgeState(unreadCount: unread, conversations: items));
  }

  @override
  Future<void> close() async {
    await _inboxSub?.cancel();
    return super.close();
  }
}
