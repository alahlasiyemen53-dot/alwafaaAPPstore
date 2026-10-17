import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:image_picker/image_picker.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/core/utils/relative_time_ar.dart';
import 'package:store/data/models/support/support_chat_message_model.dart';
import 'package:store/data/models/support/support_conversation_model.dart';
import 'package:store/features/support_chat/cubit/support_chat_badge_cubit.dart';
import 'package:store/features/support_chat/cubit/support_chat_cubit.dart';

class SupportChatThreadScreen extends StatefulWidget {
  const SupportChatThreadScreen({
    super.key,
    required this.conversationId,
    this.seedComposerText,
  });

  final String conversationId;
  final String? seedComposerText;

  @override
  State<SupportChatThreadScreen> createState() =>
      _SupportChatThreadScreenState();
}

class _SupportChatThreadScreenState extends State<SupportChatThreadScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _focusNode = FocusNode();
  final _picker = ImagePicker();
  bool _seedApplied = false;

  static const _quickReplies = [
    ('أين طلبي؟', Icons.local_shipping_outlined),
    ('مشكلة في الدفع', Icons.payment_outlined),
    ('استفسار عن منتج', Icons.inventory_2_outlined),
    ('تعديل العنوان', Icons.location_on_outlined),
  ];

  bool _showScrollToBottom = false;

  @override
  void initState() {
    super.initState();
    final seed = widget.seedComposerText?.trim();
    if (seed != null && seed.isNotEmpty) {
      _controller.text = seed;
      _seedApplied = true;
    }
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    final show = _scrollController.hasClients && _scrollController.offset > 240;
    if (show != _showScrollToBottom) {
      setState(() {
        _showScrollToBottom = show;
      });
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      if (_scrollController.offset > 0) {
        _scrollController.animateTo(
          0.0,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  static String _formatDateHeader(DateTime date) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final messageDate = DateTime(date.year, date.month, date.day);
    final difference = today.difference(messageDate).inDays;

    if (difference == 0) {
      return 'اليوم';
    } else if (difference == 1) {
      return 'أمس';
    } else {
      const months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
      ];
      return '${date.day} ${months[date.month - 1]} ${date.year}';
    }
  }

  static bool _shouldShowDateDivider(DateTime? prevDate, DateTime? currDate) {
    if (currDate == null) return false;
    if (prevDate == null) return true;
    return prevDate.year != currDate.year ||
        prevDate.month != currDate.month ||
        prevDate.day != currDate.day;
  }

  Future<void> _submitText() async {
    final text = _controller.text;
    if (text.trim().isEmpty) return;
    HapticFeedback.lightImpact();
    _controller.clear();
    await context.read<SupportChatCubit>().sendText(text);
  }

  Future<void> _pickAttachment() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('اختيار من المعرض'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('التقاط صورة / مستند'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (source == null || !mounted) return;

    final file = await _picker.pickImage(
      source: source,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 78,
    );
    if (file == null || !mounted) return;

    final sizeError =
        await SupportChatCubit.validateImageFile(file.path);
    if (!mounted) return;
    if (sizeError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(sizeError)),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('معاينة الصورة'),
        content: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.file(
            File(file.path),
            fit: BoxFit.contain,
            height: 280,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('إرسال'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await context.read<SupportChatCubit>().sendImage(file.path);
  }

  bool get _useEnterToSend {
    if (kIsWeb) return true;
    final shortest = MediaQuery.sizeOf(context).shortestSide;
    return shortest >= 600;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'محادثة الدعم',
          style:
              AppTypography.titleMedium.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      body: BlocConsumer<SupportChatCubit, SupportChatState>(
        listenWhen: (p, c) =>
            p.messages.length != c.messages.length ||
            p.messages.lastOrNull?.id != c.messages.lastOrNull?.id ||
            p.activeConversation?.supportLastReadAt !=
                c.activeConversation?.supportLastReadAt,
        listener: (context, state) {
          _scrollToEnd();
          final active = state.activeConversation;
          if (active != null) {
            try {
              context
                  .read<SupportChatBadgeCubit>()
                  .markConversationRead(active.id);
            } catch (_) {}
          }
          if (!_seedApplied) {
            final seed = widget.seedComposerText?.trim();
            if (seed != null &&
                seed.isNotEmpty &&
                _controller.text.isEmpty) {
              _controller.text = seed;
              _seedApplied = true;
            }
          }
        },
        builder: (context, state) {
          final closed = state.activeConversation?.isClosed == true;
          final conversationId =
              state.activeConversation?.id ?? widget.conversationId;
          final showQuickReplies =
              !closed && state.messages.isEmpty && !state.sending;

          return Column(
            children: [
              if (state.errorMessage != null)
                MaterialBanner(
                  content: Text(state.errorMessage!),
                  actions: [
                    TextButton(
                      onPressed: () =>
                          context.read<SupportChatCubit>().clearError(),
                      child: const Text('حسناً'),
                    ),
                  ],
                ),
              Expanded(
                child: Stack(
                  children: [
                    state.status == SupportChatStatus.loading &&
                            state.messages.isEmpty
                        ? const Center(child: CircularProgressIndicator())
                        : state.messages.isEmpty
                            ? _ThreadEmptyHint(closed: closed)
                            : RefreshIndicator(
                                onRefresh: () => context
                                    .read<SupportChatCubit>()
                                    .refreshMessages(),
                                child: ListView.builder(
                                  controller: _scrollController,
                                  reverse: true,
                                  physics:
                                      const AlwaysScrollableScrollPhysics(),
                                  padding: EdgeInsets.fromLTRB(
                                      14.w, 14.h, 14.w, 14.h),
                                  itemCount: state.messages.length,
                                  itemBuilder: (context, index) {
                                    final origIndex =
                                        state.messages.length - 1 - index;
                                    final msg = state.messages[origIndex];
                                    final prevMsg = origIndex > 0
                                        ? state.messages[origIndex - 1]
                                        : null;
                                    final showDate = _shouldShowDateDivider(
                                      prevMsg?.createdAt,
                                      msg.createdAt,
                                    );

                                    return Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        if (showDate && msg.createdAt != null)
                                          _DateDivider(
                                            text: _formatDateHeader(
                                              msg.createdAt!,
                                            ),
                                          ),
                                        _MessageBubble(
                                          message: msg,
                                          conversation:
                                              state.activeConversation,
                                          conversationId: conversationId,
                                          onRetryFailed: msg.failed
                                              ? () => context
                                                  .read<SupportChatCubit>()
                                                  .retryFailedMessage(msg)
                                              : null,
                                        ),
                                      ],
                                    );
                                  },
                                ),
                              ),
                    if (_showScrollToBottom)
                      Positioned(
                        bottom: 12.h,
                        left: 14.w,
                        child: Material(
                          color: Colors.white,
                          shape: const CircleBorder(),
                          elevation: 3,
                          shadowColor: Colors.black26,
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: () {
                              HapticFeedback.lightImpact();
                              _scrollToEnd();
                            },
                            child: Padding(
                              padding: EdgeInsets.all(8.w),
                              child: Icon(
                                Icons.keyboard_arrow_down_rounded,
                                size: 22.w,
                                color: AppColors.primaryDark,
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              if (showQuickReplies)
                Container(
                  height: 48.h,
                  margin: EdgeInsets.only(bottom: 6.h),
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    padding: EdgeInsets.symmetric(horizontal: 14.w),
                    itemCount: _quickReplies.length,
                    separatorBuilder: (_, __) => SizedBox(width: 8.w),
                    itemBuilder: (context, index) {
                      final item = _quickReplies[index];
                      return ActionChip(
                        avatar: Icon(
                          item.$2,
                          size: 14.w,
                          color: AppColors.primaryDark,
                        ),
                        label: Text(
                          item.$1,
                          style: TextStyle(
                            fontSize: 11.5.sp,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        backgroundColor: AppColors.surface,
                        side: BorderSide(
                          color: AppColors.border.withValues(alpha: 0.8),
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20.r),
                        ),
                        onPressed: state.sending
                            ? null
                            : () async {
                                HapticFeedback.lightImpact();
                                await context
                                    .read<SupportChatCubit>()
                                    .sendText(item.$1);
                              },
                      );
                    },
                  ),
                ),
              Container(
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  border: Border(
                    top: BorderSide(
                      color: AppColors.border.withValues(alpha: 0.6),
                    ),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 6,
                      offset: const Offset(0, -2),
                    ),
                  ],
                ),
                child: SafeArea(
                  top: false,
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(10.w, 8.h, 10.w, 8.h),
                    child: closed
                        ? Padding(
                            padding: EdgeInsets.symmetric(vertical: 8.h),
                            child: Text(
                              'المحادثة مغلقة. تواصل عبر مراسلة الدعم أو انتظر إعادة الفتح.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: AppColors.textMuted,
                                fontSize: 12.sp,
                              ),
                            ),
                          )
                        : CallbackShortcuts(
                            bindings: _useEnterToSend
                                ? {
                                    const SingleActivator(LogicalKeyboardKey.enter):
                                        () {
                                      if (!state.sending) {
                                        _submitText();
                                      }
                                    },
                                    const SingleActivator(
                                      LogicalKeyboardKey.enter,
                                      shift: true,
                                    ): () {
                                      final text = _controller.text;
                                      final sel = _controller.selection;
                                      final offset = sel.baseOffset < 0
                                          ? text.length
                                          : sel.baseOffset;
                                      final next =
                                          '${text.substring(0, offset)}\n${text.substring(offset)}';
                                      _controller.value = TextEditingValue(
                                        text: next,
                                        selection: TextSelection.collapsed(
                                          offset: offset + 1,
                                        ),
                                      );
                                    },
                                  }
                                : const {},
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                IconButton(
                                  onPressed:
                                      state.sending ? null : _pickAttachment,
                                  icon: Icon(
                                    Icons.add_photo_alternate_outlined,
                                    color: AppColors.primaryDark,
                                    size: 22.w,
                                  ),
                                  tooltip: 'إرفاق صورة / مستند',
                                ),
                                Expanded(
                                  child: TextField(
                                    controller: _controller,
                                    focusNode: _focusNode,
                                    minLines: 1,
                                    maxLines: 4,
                                    textInputAction: _useEnterToSend
                                        ? TextInputAction.newline
                                        : TextInputAction.send,
                                    onSubmitted: _useEnterToSend || state.sending
                                        ? null
                                        : (_) => _submitText(),
                                    decoration: InputDecoration(
                                      hintText: 'اكتب رسالتك هنا…',
                                      hintStyle: TextStyle(
                                        fontSize: 13.sp,
                                        color: AppColors.textMuted,
                                      ),
                                      contentPadding: EdgeInsets.symmetric(
                                        horizontal: 16.w,
                                        vertical: 10.h,
                                      ),
                                      filled: true,
                                      fillColor: AppColors.surfaceSoft,
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(22.r),
                                        borderSide: BorderSide.none,
                                      ),
                                    ),
                                  ),
                                ),
                                SizedBox(width: 6.w),
                                IconButton.filled(
                                  onPressed:
                                      state.sending ? null : _submitText,
                                  style: IconButton.styleFrom(
                                    backgroundColor: AppColors.primaryDark,
                                    minimumSize: Size(44.w, 44.w),
                                    shape: const CircleBorder(),
                                  ),
                                  icon: state.sending
                                      ? SizedBox(
                                          width: 16.w,
                                          height: 16.w,
                                          child: const CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: Colors.white,
                                          ),
                                        )
                                      : Icon(
                                          Icons.send_rounded,
                                          size: 19.w,
                                        ),
                                  tooltip: 'إرسال',
                                ),
                              ],
                            ),
                          ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ThreadEmptyHint extends StatelessWidget {
  const _ThreadEmptyHint({required this.closed});

  final bool closed;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(24.w),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.forum_outlined,
              size: 40.w,
              color: AppColors.primaryDark.withValues(alpha: 0.55),
            ),
            SizedBox(height: 12.h),
            Text(
              closed
                  ? 'هذه المحادثة مغلقة.'
                  : 'ابدأ برسالتك أو اختر ردّاً سريعاً بالأسفل.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: AppColors.textMuted,
                fontSize: 13.sp,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DateDivider extends StatelessWidget {
  const _DateDivider({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.symmetric(vertical: 12.h),
      padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 4.h),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(14.r),
        border: Border.all(color: AppColors.border.withValues(alpha: 0.5)),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 10.5.sp,
          fontWeight: FontWeight.w600,
          color: AppColors.textMuted,
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.message,
    required this.conversationId,
    this.conversation,
    this.onRetryFailed,
  });

  final SupportChatMessageModel message;
  final String conversationId;
  final SupportConversationModel? conversation;
  final VoidCallback? onRetryFailed;

  @override
  Widget build(BuildContext context) {
    final mine = message.isMine;
    final isReadBySupport =
        mine && conversation?.isMessageReadBySupport(message) == true;

    return Align(
      alignment:
          mine ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
      child: GestureDetector(
        onLongPress: () {
          final body = message.text?.trim();
          if (body != null && body.isNotEmpty) {
            HapticFeedback.mediumImpact();
            Clipboard.setData(ClipboardData(text: body));
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: const Row(
                  children: [
                    Icon(Icons.check_circle_outline, color: Colors.white, size: 16),
                    SizedBox(width: 8),
                    Text('تم نسخ نص الرسالة'),
                  ],
                ),
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 2),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10.r),
                ),
              ),
            );
          }
        },
        child: Container(
          margin: EdgeInsets.only(bottom: 6.h),
          padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
          constraints: BoxConstraints(maxWidth: 290.w),
          decoration: BoxDecoration(
            color: mine ? AppColors.primaryDark : Colors.white,
            borderRadius: BorderRadiusDirectional.only(
              topStart: Radius.circular(16.r),
              topEnd: Radius.circular(16.r),
              bottomStart: Radius.circular(mine ? 16.r : 4.r),
              bottomEnd: Radius.circular(mine ? 4.r : 16.r),
            ),
            border: mine
                ? null
                : Border.all(
                    color: AppColors.border.withValues(alpha: 0.6),
                    width: 1,
                  ),
            boxShadow: [
              BoxShadow(
                color: mine
                    ? AppColors.primaryDark.withValues(alpha: 0.16)
                    : Colors.black.withValues(alpha: 0.04),
                blurRadius: 4,
                offset: const Offset(0, 1.5),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (message.isImage)
              Padding(
                padding: EdgeInsets.only(bottom: message.text == null ? 0 : 8.h),
                child: _ChatImage(
                  message: message,
                  conversationId: conversationId,
                  onDark: mine,
                ),
              ),
            if (message.text != null && message.text!.isNotEmpty)
              Text(
                message.text!,
                style: TextStyle(
                  color: mine ? Colors.white : AppColors.textPrimary,
                  fontSize: 13.sp,
                ),
              ),
            if (!message.isImage &&
                (message.text == null || message.text!.isEmpty))
              Text(
                'رسالة',
                style: TextStyle(
                  color: mine ? Colors.white70 : AppColors.textMuted,
                  fontSize: 13.sp,
                ),
              ),
            SizedBox(height: 4.h),
            Wrap(
              spacing: 6.w,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                if (message.createdAt != null)
                  Text(
                    relativeTimeAr(message.createdAt),
                    style: TextStyle(
                      fontSize: 10.sp,
                      color: mine ? Colors.white70 : AppColors.textMuted,
                    ),
                  ),
                if (mine) ...[
                  if (message.pending)
                    Text(
                      'جاري الإرسال…',
                      style: TextStyle(
                        fontSize: 10.sp,
                        color: Colors.white70,
                      ),
                    )
                  else if (message.failed)
                    GestureDetector(
                      onTap: onRetryFailed,
                      child: Text(
                        'فشل الإرسال · إعادة',
                        style: TextStyle(
                          fontSize: 10.sp,
                          fontWeight: FontWeight.w700,
                          color: Colors.amber.shade200,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    )
                  else if (isReadBySupport)
                    Tooltip(
                      message: 'تمت القراءة من قبل الدعم',
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.done_all_rounded,
                            size: 14.w,
                            color: const Color(0xFF64D2FF),
                          ),
                          SizedBox(width: 3.w),
                          Text(
                            'تمت القراءة',
                            style: TextStyle(
                              fontSize: 9.sp,
                              fontWeight: FontWeight.bold,
                              color: const Color(0xFF64D2FF),
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    Tooltip(
                      message: 'تم الإرسال للدعم (بانتظار القراءة)',
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.done_rounded,
                            size: 13.w,
                            color: Colors.white70,
                          ),
                          SizedBox(width: 3.w),
                          Text(
                            'تم الإرسال',
                            style: TextStyle(
                              fontSize: 9.sp,
                              color: Colors.white70,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ],
        ),
      ),
    ),
  );
}
}

class _ChatImage extends StatefulWidget {
  const _ChatImage({
    required this.message,
    required this.conversationId,
    required this.onDark,
  });

  final SupportChatMessageModel message;
  final String conversationId;
  final bool onDark;

  @override
  State<_ChatImage> createState() => _ChatImageState();
}

class _ChatImageState extends State<_ChatImage> {
  Future<Uint8List?>? _bytesFuture;
  Uint8List? _cachedBytes;

  @override
  void initState() {
    super.initState();
    _loadIfNeeded();
  }

  @override
  void didUpdateWidget(covariant _ChatImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.message.id != widget.message.id ||
        oldWidget.message.attachmentPath != widget.message.attachmentPath) {
      _loadIfNeeded();
    }
  }

  void _loadIfNeeded() {
    final local = widget.message.localFilePath;
    if (local != null && local.isNotEmpty) {
      _bytesFuture = null;
      return;
    }
    if (widget.message.id.startsWith('local-')) {
      _bytesFuture = null;
      return;
    }
    _bytesFuture = context
        .read<SupportChatCubit>()
        .loadAttachmentBytes(widget.message.id)
        .then((bytes) {
      _cachedBytes = bytes;
      return bytes;
    });
  }

  void _openFullscreen(ImageProvider provider) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => Scaffold(
          backgroundColor: Colors.black,
          appBar: AppBar(
            backgroundColor: Colors.black,
            foregroundColor: Colors.white,
            title: const Text('معاينة الصورة'),
          ),
          body: Center(
            child: InteractiveViewer(
              minScale: 0.8,
              maxScale: 4,
              child: Image(image: provider, fit: BoxFit.contain),
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final local = widget.message.localFilePath;
    if (local != null && local.isNotEmpty) {
      final provider = FileImage(File(local));
      return GestureDetector(
        onTap: () => _openFullscreen(provider),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(10.r),
          child: Image.file(
            File(local),
            width: 220.w,
            height: 180.h,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => _placeholder(),
          ),
        ),
      );
    }

    return FutureBuilder<Uint8List?>(
      future: _bytesFuture,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return SizedBox(
            width: 220.w,
            height: 140.h,
            child: Center(
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: widget.onDark ? Colors.white : AppColors.primaryDark,
              ),
            ),
          );
        }
        final bytes = snap.data ?? _cachedBytes;
        if (bytes == null) return _placeholder();
        final provider = MemoryImage(bytes);
        return GestureDetector(
          onTap: () => _openFullscreen(provider),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(10.r),
            child: Image.memory(
              bytes,
              width: 220.w,
              height: 180.h,
              fit: BoxFit.cover,
            ),
          ),
        );
      },
    );
  }

  Widget _placeholder() {
    return Container(
      width: 220.w,
      height: 120.h,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.black12,
        borderRadius: BorderRadius.circular(10.r),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.image_outlined,
            color: widget.onDark ? Colors.white70 : AppColors.textMuted,
          ),
          SizedBox(height: 4.h),
          Text(
            'صورة / مستند',
            style: TextStyle(
              fontSize: 11.sp,
              color: widget.onDark ? Colors.white70 : AppColors.textMuted,
            ),
          ),
        ],
      ),
    );
  }
}

extension _LastOrNull<E> on List<E> {
  E? get lastOrNull => isEmpty ? null : last;
}
