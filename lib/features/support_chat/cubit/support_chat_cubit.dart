import 'dart:async';
import 'dart:io';

import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/core/services/crashlytics_service.dart';
import 'package:store/core/support_chat/firebase_chat_session.dart';
import 'package:store/core/support_chat/support_chat_inbox_cache.dart';
import 'package:store/data/datasources/support_chat_remote_datasource.dart';
import 'package:store/data/models/support/support_chat_message_model.dart';
import 'package:store/data/models/support/support_conversation_model.dart';
import 'package:uuid/uuid.dart';

enum SupportChatStatus { initial, loading, ready, error }

enum SupportChatLiveMode { unknown, firestore, polling }

final class SupportChatState extends Equatable {
  const SupportChatState({
    this.status = SupportChatStatus.initial,
    this.conversations = const [],
    this.activeConversation,
    this.messages = const [],
    this.errorMessage,
    this.sending = false,
    this.liveMode = SupportChatLiveMode.unknown,
    this.fromCache = false,
  });

  final SupportChatStatus status;
  final List<SupportConversationModel> conversations;
  final SupportConversationModel? activeConversation;
  final List<SupportChatMessageModel> messages;
  final String? errorMessage;
  final bool sending;
  final SupportChatLiveMode liveMode;
  final bool fromCache;

  int get unreadCount => conversations.where((c) => c.hasUnread).length;

  /// Visible inbox rows (hides empty closed/old shells).
  List<SupportConversationModel> get visibleConversations {
    final withContent = conversations.where((c) => c.hasContent).toList();
    if (withContent.isNotEmpty) return withContent;
    return conversations.where((c) => c.isActive).toList();
  }

  SupportConversationModel? get soleActiveConversation {
    final active = conversations.where((c) => c.isActive).toList();
    if (active.length == 1) return active.first;
    final visibleActive =
        visibleConversations.where((c) => c.isActive).toList();
    if (visibleActive.length == 1) return visibleActive.first;
    return null;
  }

  SupportChatState copyWith({
    SupportChatStatus? status,
    List<SupportConversationModel>? conversations,
    SupportConversationModel? activeConversation,
    List<SupportChatMessageModel>? messages,
    String? errorMessage,
    bool? sending,
    SupportChatLiveMode? liveMode,
    bool? fromCache,
    bool clearActive = false,
    bool clearError = false,
  }) {
    return SupportChatState(
      status: status ?? this.status,
      conversations: conversations ?? this.conversations,
      activeConversation:
          clearActive ? null : (activeConversation ?? this.activeConversation),
      messages: messages ?? this.messages,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      sending: sending ?? this.sending,
      liveMode: liveMode ?? this.liveMode,
      fromCache: fromCache ?? this.fromCache,
    );
  }

  @override
  List<Object?> get props => [
        status,
        conversations,
        activeConversation,
        messages,
        errorMessage,
        sending,
        liveMode,
        fromCache,
      ];
}

class SupportChatCubit extends Cubit<SupportChatState> {
  SupportChatCubit({
    SupportChatRemoteDatasource? datasource,
    FirebaseChatSession? session,
    SupportChatInboxCache? cache,
  })  : _datasource = datasource ?? SupportChatRemoteDatasource(),
        _session = session ?? FirebaseChatSession(),
        _cache = cache ?? SupportChatInboxCache(),
        super(const SupportChatState());

  static const maxImageBytes = 5 * 1024 * 1024;

  final SupportChatRemoteDatasource _datasource;
  final FirebaseChatSession _session;
  final SupportChatInboxCache _cache;
  final _uuid = const Uuid();
  StreamSubscription<List<SupportChatMessageModel>>? _messagesSub;
  StreamSubscription<SupportConversationModel?>? _activeConversationSub;
  StreamSubscription<List<SupportConversationModel>>? _inboxSub;
  Timer? _pollTimer;
  bool _refreshing = false;

  @override
  void emit(SupportChatState state) {
    if (isClosed) return;
    super.emit(state);
  }

  Future<Uint8List> loadAttachmentBytes(String messageId) {
    final conversationId = state.activeConversation?.id;
    if (conversationId == null) {
      return Future.error(StateError('No active conversation.'));
    }
    return _datasource.fetchAttachmentBytes(
      conversationId: conversationId,
      messageId: messageId,
    );
  }

  Future<void> loadInbox({String? openConversationId}) async {
    final cached = await _cache.load();
    if (isClosed) return;
    if (cached.isNotEmpty && state.conversations.isEmpty) {
      emit(state.copyWith(
        status: SupportChatStatus.ready,
        conversations: cached,
        fromCache: true,
        clearError: true,
      ));
    } else {
      emit(state.copyWith(status: SupportChatStatus.loading, clearError: true));
    }

    try {
      final items = await _datasource.listConversations();
      if (isClosed) return;
      await _cache.save(items);
      if (isClosed) return;
      emit(state.copyWith(
        status: SupportChatStatus.ready,
        conversations: items,
        fromCache: false,
      ));
      unawaited(_softSignInFirebase());
      unawaited(_attachInboxRealtime());

      final targetId = openConversationId?.trim();
      if (targetId != null && targetId.isNotEmpty) {
        for (final item in items) {
          if (item.id == targetId) {
            await openConversation(item);
            break;
          }
        }
      }
    } on ApiFailure catch (e) {
      if (isClosed) return;
      if (state.conversations.isNotEmpty) {
        emit(state.copyWith(
          status: SupportChatStatus.ready,
          errorMessage: e.message,
          fromCache: true,
        ));
        return;
      }
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: e.message,
      ));
    } catch (e) {
      if (isClosed) return;
      if (kDebugMode) debugPrint('SupportChat.loadInbox: $e');
      if (state.conversations.isNotEmpty) {
        emit(state.copyWith(
          status: SupportChatStatus.ready,
          errorMessage: 'تعذر التحديث — عرض آخر صندوق محفوظ.',
          fromCache: true,
        ));
        return;
      }
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: 'تعذر تحميل محادثات الدعم.',
      ));
    }
  }

  Future<void> linkRelatedOrder(int relatedOrderId) async {
    try {
      final updated =
          await _datasource.createConversation(relatedOrderId: relatedOrderId);
      final next = state.conversations.map((c) {
        if (c.id != updated.id) return c;
        return updated;
      }).toList();
      final has = next.any((c) => c.id == updated.id);
      emit(state.copyWith(
        conversations: has ? next : [updated, ...next],
        activeConversation: state.activeConversation?.id == updated.id
            ? updated
            : state.activeConversation,
      ));
    } catch (e) {
      if (kDebugMode) debugPrint('SupportChat.linkRelatedOrder: $e');
    }
  }

  Future<void> ensureSingleConversation({
    int? relatedOrderId,
    String? seedText,
    String? openConversationId,
  }) async {
    if (state.activeConversation != null) {
      if (relatedOrderId != null &&
          state.activeConversation?.relatedOrderId == null) {
        unawaited(linkRelatedOrder(relatedOrderId));
      }
      return;
    }

    final cached = await _cache.load();
    if (isClosed) return;

    SupportConversationModel? target;
    if (openConversationId != null && openConversationId.trim().isNotEmpty) {
      for (final c in cached) {
        if (c.id == openConversationId.trim()) {
          target = c;
          break;
        }
      }
    }
    target ??= cached.isNotEmpty ? cached.first : null;

    if (target != null) {
      emit(state.copyWith(
        status: SupportChatStatus.ready,
        conversations: [target],
        activeConversation: target,
        fromCache: true,
      ));
      unawaited(openConversation(target));
    } else {
      emit(state.copyWith(
        status: SupportChatStatus.loading,
        clearError: true,
      ));
    }

    try {
      final items = await _datasource.listConversations();
      if (isClosed) return;
      await _cache.save(items);
      if (isClosed) return;

      SupportConversationModel? conversation;
      if (openConversationId != null && openConversationId.trim().isNotEmpty) {
        for (final c in items) {
          if (c.id == openConversationId.trim()) {
            conversation = c;
            break;
          }
        }
      }
      conversation ??= items.isNotEmpty ? items.first : null;

      if (conversation == null) {
        conversation = await _datasource.createConversation(
          relatedOrderId: relatedOrderId,
        );
      } else if (relatedOrderId != null && conversation.relatedOrderId == null) {
        await linkRelatedOrder(relatedOrderId);
      }

      emit(state.copyWith(
        status: SupportChatStatus.ready,
        conversations: [conversation],
        activeConversation: conversation,
        fromCache: false,
      ));

      await openConversation(conversation);
      unawaited(_softSignInFirebase());
      unawaited(_attachInboxRealtime());

      final seed = seedText?.trim();
      if (seed != null && seed.isNotEmpty && state.messages.isEmpty) {
        await sendText(seed);
      }
    } on ApiFailure catch (e) {
      if (isClosed) return;
      if (state.activeConversation != null) {
        emit(state.copyWith(
          status: SupportChatStatus.ready,
          errorMessage: e.message,
        ));
        return;
      }
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: e.message,
      ));
    } catch (e) {
      if (isClosed) return;
      if (state.activeConversation != null) {
        emit(state.copyWith(status: SupportChatStatus.ready));
        return;
      }
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: 'تعذر الاتصال بمحادثة الدعم.',
      ));
    }
  }

  Future<void> startConversation({
    int? relatedOrderId,
    String? seedText,
  }) async {
    emit(state.copyWith(status: SupportChatStatus.loading, clearError: true));
    try {
      SupportConversationModel? localOpen;
      for (final item in state.conversations) {
        if (item.isActive) {
          localOpen = item;
          break;
        }
      }
      final conversation = localOpen ??
          await _datasource.createConversation(relatedOrderId: relatedOrderId);
      if (localOpen != null && relatedOrderId != null) {
        await linkRelatedOrder(relatedOrderId);
      }
      await openConversation(conversation);
      final seed = seedText?.trim();
      if (seed != null && seed.isNotEmpty) {
        await sendText(seed);
      }
    } on ApiFailure catch (e) {
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: e.message,
      ));
    } catch (e) {
      if (kDebugMode) debugPrint('SupportChat.startConversation: $e');
      emit(state.copyWith(
        status: SupportChatStatus.error,
        errorMessage: 'تعذر بدء محادثة الدعم.',
      ));
    }
  }

  Future<void> openConversation(SupportConversationModel conversation) async {
    await _stopMessageUpdates();
    if (isClosed) return;
    final cached = await _cache.loadMessages(conversation.id);
    if (isClosed) return;
    if (cached.isNotEmpty) {
      emit(state.copyWith(
        activeConversation: conversation,
        messages: cached,
        status: SupportChatStatus.ready,
        fromCache: true,
        liveMode: SupportChatLiveMode.unknown,
        clearError: true,
      ));
    } else {
      emit(state.copyWith(
        activeConversation: conversation,
        messages: const [],
        status: SupportChatStatus.loading,
        liveMode: SupportChatLiveMode.unknown,
        clearError: true,
      ));
    }
    try {
      final history = await _datasource.listMessages(conversation.id);
      if (isClosed) return;
      unawaited(_cache.saveMessages(conversation.id, history));
      emit(state.copyWith(
        status: SupportChatStatus.ready,
        messages: history,
        fromCache: false,
      ));
      unawaited(_datasource.markRead(conversation.id));
      _markLocalRead(conversation.id);
      await _attachRealtime(conversation.id);
    } on ApiFailure catch (e) {
      if (cached.isEmpty) {
        emit(state.copyWith(
          status: SupportChatStatus.error,
          errorMessage: e.message,
        ));
      }
    }
  }

  Future<void> refreshMessages() async {
    final conversation = state.activeConversation;
    if (conversation == null || _refreshing || isClosed) return;

    _refreshing = true;
    try {
      final convFuture = _datasource
          .getConversation(conversation.id)
          .catchError((_) => conversation);
      final historyFuture = _datasource.listMessages(conversation.id);
      final results = await Future.wait([convFuture, historyFuture]);
      if (isClosed || state.activeConversation?.id != conversation.id) return;

      final freshConv = results[0] as SupportConversationModel;
      final history = results[1] as List<SupportChatMessageModel>;

      final pendingLocals = state.messages.where((m) => m.pending).toList();
      final failedLocals = state.messages.where((m) => m.failed).toList();
      final merged = _mergeServerWithPending(
        history,
        [...pendingLocals, ...failedLocals],
      );
      unawaited(_cache.saveMessages(conversation.id, history));
      emit(state.copyWith(
        activeConversation: freshConv,
        messages: merged,
        status: SupportChatStatus.ready,
        fromCache: false,
        clearError: true,
      ));
    } on ApiFailure catch (e) {
      if (kDebugMode) debugPrint('SupportChat.refreshMessages: ${e.message}');
    } catch (e) {
      if (kDebugMode) debugPrint('SupportChat.refreshMessages: $e');
    } finally {
      _refreshing = false;
    }
  }

  Future<void> sendText(String raw) async {
    final text = raw.trim();
    final conversation = state.activeConversation;
    if (text.isEmpty || conversation == null || conversation.isClosed) return;

    final clientId = _uuid.v4();
    final optimistic = SupportChatMessageModel(
      id: 'local-$clientId',
      senderType: 'customer',
      senderUid: _session.uid ?? 'customer',
      type: 'text',
      text: text,
      clientMessageId: clientId,
      createdAt: DateTime.now(),
      pending: true,
    );
    emit(state.copyWith(
      messages: [...state.messages, optimistic],
      sending: true,
      clearError: true,
    ));
    await _dispatchText(conversation.id, text, clientId);
  }

  Future<void> sendImage(String filePath, {String? caption}) async {
    final conversation = state.activeConversation;
    if (conversation == null || conversation.isClosed) return;

    final sizeError = await validateImageFile(filePath);
    if (sizeError != null) {
      emit(state.copyWith(errorMessage: sizeError));
      return;
    }

    final clientId = _uuid.v4();
    final optimistic = SupportChatMessageModel(
      id: 'local-$clientId',
      senderType: 'customer',
      senderUid: _session.uid ?? 'customer',
      type: 'image',
      text: caption?.trim().isEmpty == true ? null : caption?.trim(),
      localFilePath: filePath,
      clientMessageId: clientId,
      createdAt: DateTime.now(),
      pending: true,
    );
    emit(state.copyWith(
      messages: [...state.messages, optimistic],
      sending: true,
      clearError: true,
    ));
    await _dispatchImage(
      conversation.id,
      filePath,
      clientId,
      caption: caption,
    );
  }

  Future<void> retryFailedMessage(SupportChatMessageModel message) async {
    if (!message.failed || message.clientMessageId == null) return;
    final conversation = state.activeConversation;
    if (conversation == null || conversation.isClosed) return;

    final clientId = message.clientMessageId!;
    final next = state.messages
        .map((m) => m.clientMessageId == clientId
            ? m.copyWith(pending: true, failed: false)
            : m)
        .toList();
    emit(state.copyWith(messages: next, sending: true, clearError: true));

    if (message.isImage) {
      final path = message.localFilePath;
      if (path == null || path.isEmpty) {
        emit(state.copyWith(
          sending: false,
          errorMessage: 'تعذر إعادة إرسال الصورة — الملف غير متاح.',
        ));
        return;
      }
      await _dispatchImage(
        conversation.id,
        path,
        clientId,
        caption: message.text,
      );
      return;
    }

    final text = message.text?.trim() ?? '';
    if (text.isEmpty) {
      emit(state.copyWith(sending: false, errorMessage: 'لا يوجد نص لإعادة الإرسال.'));
      return;
    }
    await _dispatchText(conversation.id, text, clientId);
  }

  static Future<String?> validateImageFile(String filePath) async {
    try {
      final file = File(filePath);
      if (!await file.exists()) {
        return 'تعذر قراءة الصورة المحددة.';
      }
      final length = await file.length();
      if (length > maxImageBytes) {
        return 'حجم الصورة أكبر من 5 ميجابايت. اختر صورة أصغر أو قلّل الجودة.';
      }
    } catch (_) {
      return 'تعذر التحقق من حجم الصورة.';
    }
    return null;
  }

  void clearError() {
    if (state.errorMessage != null) {
      emit(state.copyWith(clearError: true));
    }
  }

  Future<void> _dispatchText(
    String conversationId,
    String text,
    String clientId,
  ) async {
    try {
      await _datasource.sendText(
        conversationId: conversationId,
        body: text,
        clientMessageId: clientId,
      );
      if (isClosed) return;
      emit(state.copyWith(sending: false));
      if (_messagesSub == null) {
        await refreshMessages();
      }
    } on ApiFailure catch (e) {
      _markFailed(clientId, e.message);
    }
  }

  Future<void> _dispatchImage(
    String conversationId,
    String filePath,
    String clientId, {
    String? caption,
  }) async {
    try {
      await _datasource.sendImage(
        conversationId: conversationId,
        filePath: filePath,
        clientMessageId: clientId,
        body: caption,
      );
      if (isClosed) return;
      emit(state.copyWith(sending: false));
      if (_messagesSub == null) {
        await refreshMessages();
      }
    } on ApiFailure catch (e) {
      _markFailed(clientId, e.message);
    }
  }

  void _markFailed(String clientId, String message) {
    final next = state.messages
        .map((m) => m.clientMessageId == clientId
            ? m.copyWith(pending: false, failed: true)
            : m)
        .toList();
    emit(state.copyWith(
      messages: next,
      sending: false,
      errorMessage: message,
    ));
  }

  void _markLocalRead(String conversationId) {
    final now = DateTime.now();
    final next = state.conversations.map((c) {
      if (c.id != conversationId) return c;
      return c.copyWith(customerLastReadAt: now);
    }).toList();
    final active = state.activeConversation?.id == conversationId
        ? state.activeConversation!.copyWith(customerLastReadAt: now)
        : state.activeConversation;
    emit(state.copyWith(
      conversations: next,
      activeConversation: active,
    ));
    unawaited(_cache.save(next));
  }

  Future<void> _attachInboxRealtime() async {
    try {
      await _session.ensureSignedIn();
      if (isClosed) return;
      await _inboxSub?.cancel();
      _inboxSub = _session.watchConversations().listen(
        (live) {
          if (isClosed) return;
          final merged = _mergeInboxLive(live, state.conversations);
          SupportConversationModel? updatedActive = state.activeConversation;
          if (updatedActive != null) {
            final match =
                merged.where((c) => c.id == updatedActive!.id).firstOrNull;
            if (match != null) {
              updatedActive = match;
            }
          }
          emit(state.copyWith(
            conversations: merged,
            activeConversation: updatedActive,
            fromCache: false,
          ));
          unawaited(_cache.save(merged));
        },
        onError: (Object e) {
          if (kDebugMode) {
            debugPrint('SupportChat inbox Firestore listener: $e');
          }
        },
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('SupportChat inbox realtime unavailable: $e');
      }
    }
  }

  List<SupportConversationModel> _mergeInboxLive(
    List<SupportConversationModel> live,
    List<SupportConversationModel> local,
  ) {
    final byId = {for (final c in local) c.id: c};
    return live.map((row) {
      final prev = byId[row.id];
      if (prev == null) return row;
      return row.copyWith(
        customerLastReadAt: row.customerLastReadAt ?? prev.customerLastReadAt,
        supportLastReadAt: row.supportLastReadAt ?? prev.supportLastReadAt,
      );
    }).toList();
  }

  Future<void> _attachRealtime(String conversationId) async {
    try {
      await _session.ensureSignedIn();
      if (isClosed || state.activeConversation?.id != conversationId) return;

      await _messagesSub?.cancel();
      await _activeConversationSub?.cancel();
      _pollTimer?.cancel();
      _pollTimer = null;

      _activeConversationSub =
          _session.watchConversation(conversationId).listen(
        (updated) {
          if (isClosed ||
              updated == null ||
              state.activeConversation?.id != conversationId) {
            return;
          }
          final prev = state.activeConversation;
          if (prev?.supportLastReadAt != updated.supportLastReadAt ||
              prev?.status != updated.status ||
              prev?.customerLastReadAt != updated.customerLastReadAt) {
            final nextInbox = state.conversations
                .map((c) => c.id == updated.id ? updated : c)
                .toList();
            emit(state.copyWith(
              activeConversation: updated,
              conversations: nextInbox,
            ));
          }
        },
        onError: (Object e) {
          if (kDebugMode) {
            debugPrint('SupportChat active conversation listener: $e');
          }
        },
      );

      _messagesSub = _session.watchMessages(conversationId).listen(
        (live) {
          if (isClosed || state.activeConversation?.id != conversationId) {
            return;
          }
          if (live.isEmpty && state.messages.isNotEmpty) return;

          final pending = state.messages.where((m) => m.pending).toList();
          final failed = state.messages.where((m) => m.failed).toList();
          final merged =
              _mergeServerWithPending(live, [...pending, ...failed]);
          final withLocalPreview = merged.map((m) {
            if ((!m.pending && !m.failed) || m.localFilePath != null) {
              return m;
            }
            SupportChatMessageModel? match;
            for (final p in [...pending, ...failed]) {
              if (p.clientMessageId != null &&
                  p.clientMessageId == m.clientMessageId) {
                match = p;
                break;
              }
            }
            return match?.localFilePath == null
                ? m
                : m.copyWith(localFilePath: match!.localFilePath);
          }).toList();

            unawaited(_cache.saveMessages(conversationId, live));

            emit(state.copyWith(
              messages: withLocalPreview,
              status: SupportChatStatus.ready,
              liveMode: SupportChatLiveMode.firestore,
              clearError: true,
            ));
          },
          onError: (Object e, StackTrace stack) {
            if (kDebugMode) {
              debugPrint('SupportChat Firestore listener: $e');
            }
            final errStr = e.toString();
            final isExpected = errStr.contains('permission-denied') ||
                errStr.contains('unavailable');
            if (!isExpected) {
              unawaited(CrashlyticsService.instance.recordError(
                e,
                stack,
                reason: 'SupportChat Firestore listener error',
              ));
            }
            _startPolling(conversationId);
          },
        );
        if (!isClosed) {
          emit(state.copyWith(liveMode: SupportChatLiveMode.firestore));
        }
      } catch (e, stack) {
        if (kDebugMode) {
          debugPrint('SupportChat realtime unavailable, API polling fallback: $e');
        }
        final errStr = e.toString();
        final isExpected = errStr.contains('CONFIGURATION_NOT_FOUND') ||
            errStr.contains('network-request-failed') ||
            errStr.contains('unavailable') ||
            errStr.contains('permission-denied');
        if (!isExpected) {
          unawaited(CrashlyticsService.instance.recordError(
            e,
            stack,
            reason: 'SupportChat realtime unavailable, fallback to polling',
          ));
        }
        _startPolling(conversationId);
      }
  }

  void _startPolling(String conversationId) {
    if (_pollTimer != null) return;
    emit(state.copyWith(liveMode: SupportChatLiveMode.polling));
    _pollTimer = Timer.periodic(const Duration(milliseconds: 2500), (_) {
      if (isClosed || state.activeConversation?.id != conversationId) return;
      unawaited(refreshMessages());
    });
  }

  Future<void> _stopMessageUpdates() async {
    _pollTimer?.cancel();
    _pollTimer = null;
    await _messagesSub?.cancel();
    _messagesSub = null;
    await _activeConversationSub?.cancel();
    _activeConversationSub = null;
  }

  Future<void> _stopAllUpdates() async {
    await _stopMessageUpdates();
    await _inboxSub?.cancel();
    _inboxSub = null;
  }

  List<SupportChatMessageModel> _mergeServerWithPending(
    List<SupportChatMessageModel> server,
    List<SupportChatMessageModel> pending,
  ) {
    if (pending.isEmpty) return server;

    final serverClientIds = <String>{
      for (final m in server)
        if (m.clientMessageId != null) m.clientMessageId!,
    };
    final stillPending = pending
        .where((m) =>
            m.clientMessageId == null ||
            !serverClientIds.contains(m.clientMessageId))
        .toList();
    return [...server, ...stillPending];
  }

  Future<void> _softSignInFirebase() async {
    try {
      await _session.ensureSignedIn();
    } catch (e) {
      if (kDebugMode) {
        debugPrint('SupportChat Firebase sign-in (non-blocking): $e');
      }
    }
  }

  Future<void> clearOnLogout() async {
    await _stopAllUpdates();
    await _session.signOut();
    await _cache.clear();
    emit(const SupportChatState());
  }

  @override
  Future<void> close() async {
    await _stopAllUpdates();
    return super.close();
  }
}
