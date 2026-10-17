import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:store/data/datasources/support_chat_remote_datasource.dart';
import 'package:store/data/models/support/support_chat_message_model.dart';
import 'package:store/data/models/support/support_conversation_model.dart';

/// Bridges Customer JWT → Firebase custom token and exposes Firestore reads.
class FirebaseChatSession {
  FirebaseChatSession({
    SupportChatRemoteDatasource? datasource,
    FirebaseAuth? auth,
    FirebaseFirestore? firestore,
  })  : _datasource = datasource ?? SupportChatRemoteDatasource(),
        _authOverride = auth,
        _firestoreOverride = firestore;

  final SupportChatRemoteDatasource _datasource;
  final FirebaseAuth? _authOverride;
  final FirebaseFirestore? _firestoreOverride;

  String? _uid;

  FirebaseAuth get _auth => _authOverride ?? FirebaseAuth.instance;
  FirebaseFirestore get _firestore =>
      _firestoreOverride ?? FirebaseFirestore.instance;

  String? get uid => _uid ?? _auth.currentUser?.uid;

  Future<void> ensureSignedIn() async {
    final current = _auth.currentUser;
    if (current != null && current.uid.startsWith('customer:')) {
      _uid = current.uid;
      return;
    }
    final issued = await _datasource.fetchFirebaseToken();
    try {
      await _auth.signInWithCustomToken(issued.token);
      _uid = issued.uid;
    } catch (e) {
      if (kDebugMode) {
        if (e.toString().contains('CONFIGURATION_NOT_FOUND')) {
          debugPrint(
            '⚠️ [FirebaseChatSession] Firebase Authentication is not activated in Firebase Console for project alwafaaapp! '
            'Please open Firebase Console -> Authentication and click "Get started", then enable a sign-in method.',
          );
        } else {
          debugPrint('FirebaseChatSession.signInWithCustomToken failed: $e');
        }
      }
      rethrow;
    }
  }

  Stream<List<SupportChatMessageModel>> watchMessages(String conversationId) {
    return _firestore
        .collection('support_conversations')
        .doc(conversationId)
        .collection('messages')
        .orderBy('created_at')
        .limitToLast(80)
        .snapshots()
        .map((snap) => snap.docs
            .map((d) => SupportChatMessageModel.fromFirestore(d.id, d.data()))
            .toList());
  }

  Stream<SupportConversationModel?> watchConversation(String conversationId) {
    return _firestore
        .collection('support_conversations')
        .doc(conversationId)
        .snapshots()
        .map((doc) => doc.exists && doc.data() != null
            ? SupportConversationModel.fromFirestore(doc.id, doc.data()!)
            : null);
  }

  /// Live inbox rows for the signed-in customer (`member_uids` array-contains).
  Stream<List<SupportConversationModel>> watchConversations() {
    final resolvedUid = uid;
    if (resolvedUid == null || resolvedUid.isEmpty) {
      return const Stream.empty();
    }
    return _firestore
        .collection('support_conversations')
        .where('member_uids', arrayContains: resolvedUid)
        .snapshots()
        .map((snap) {
      final items = snap.docs
          .map((d) => SupportConversationModel.fromFirestore(d.id, d.data()))
          .toList();
      items.sort((a, b) {
        final aAt = a.lastMessageAt ?? DateTime.fromMillisecondsSinceEpoch(0);
        final bAt = b.lastMessageAt ?? DateTime.fromMillisecondsSinceEpoch(0);
        return bAt.compareTo(aAt);
      });
      return items;
    });
  }

  Future<void> signOut() async {
    try {
      await _auth.signOut();
    } catch (e) {
      if (kDebugMode) debugPrint('FirebaseChatSession.signOut: $e');
    }
    _uid = null;
  }
}
