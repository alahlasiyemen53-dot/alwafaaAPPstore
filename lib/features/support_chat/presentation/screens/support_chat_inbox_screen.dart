import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';
import 'package:store/core/theme/app_typography.dart';
import 'package:store/features/support_chat/cubit/support_chat_badge_cubit.dart';
import 'package:store/features/support_chat/cubit/support_chat_cubit.dart';
import 'package:store/features/support_chat/presentation/screens/support_chat_thread_screen.dart';

/// Single continuous support chat screen.
/// Always connects the customer to their dedicated single support conversation.
class SupportChatInboxScreen extends StatefulWidget {
  const SupportChatInboxScreen({
    super.key,
    this.initialConversationId,
    this.preferDirectThread = true,
    this.seedText,
    this.relatedOrderId,
  });

  final String? initialConversationId;
  final bool preferDirectThread;
  final String? seedText;
  final int? relatedOrderId;

  static Future<void> open(
    BuildContext context, {
    String? conversationId,
    bool preferDirectThread = true,
    String? seedText,
    int? relatedOrderId,
  }) {
    return push(
      Navigator.of(context),
      conversationId: conversationId,
      preferDirectThread: preferDirectThread,
      seedText: seedText,
      relatedOrderId: relatedOrderId,
    );
  }

  static Future<void> push(
    NavigatorState navigator, {
    String? conversationId,
    bool preferDirectThread = true,
    String? seedText,
    int? relatedOrderId,
  }) {
    return navigator.push(
      MaterialPageRoute(
        builder: (_) => BlocProvider(
          create: (_) => SupportChatCubit()
            ..ensureSingleConversation(
              openConversationId: conversationId,
              relatedOrderId: relatedOrderId,
              seedText: seedText,
            ),
          child: SupportChatInboxScreen(
            initialConversationId: conversationId,
            preferDirectThread: true,
            seedText: seedText,
            relatedOrderId: relatedOrderId,
          ),
        ),
      ),
    );
  }

  @override
  State<SupportChatInboxScreen> createState() => _SupportChatInboxScreenState();
}

class _SupportChatInboxScreenState extends State<SupportChatInboxScreen> {
  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SupportChatCubit, SupportChatState>(
      listenWhen: (p, c) => p.conversations != c.conversations,
      listener: (context, state) {
        try {
          context
              .read<SupportChatBadgeCubit>()
              .applyConversations(state.conversations);
        } catch (_) {/* badge cubit optional in test contexts */}
      },
      builder: (context, state) {
        final active = state.activeConversation;

        // If conversation is resolved (or loaded from cache), show the thread directly
        if (active != null) {
          return SupportChatThreadScreen(
            conversationId: active.id,
            seedComposerText: widget.seedText,
          );
        }

        // Error state
        if (state.status == SupportChatStatus.error) {
          return Scaffold(
            backgroundColor: AppColors.background,
            appBar: AppBar(
              title: Text(
                'خدمة العملاء',
                style: AppTypography.titleMedium.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            body: Center(
              child: Padding(
                padding: EdgeInsets.all(24.w),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.error_outline_rounded,
                      size: 48.sp,
                      color: AppColors.error,
                    ),
                    SizedBox(height: 12.h),
                    Text(
                      state.errorMessage ?? 'تعذر تحميل محادثة الدعم.',
                      textAlign: TextAlign.center,
                      style: AppTypography.bodyMedium,
                    ),
                    SizedBox(height: 16.h),
                    ElevatedButton.icon(
                      onPressed: () => context
                          .read<SupportChatCubit>()
                          .ensureSingleConversation(
                            openConversationId: widget.initialConversationId,
                            relatedOrderId: widget.relatedOrderId,
                            seedText: widget.seedText,
                          ),
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('إعادة المحاولة'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primaryDark,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        // Loading state
        return Scaffold(
          backgroundColor: AppColors.background,
          appBar: AppBar(
            title: Text(
              'خدمة العملاء',
              style: AppTypography.titleMedium.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          body: const Center(
            child: CircularProgressIndicator(color: AppColors.primaryDark),
          ),
        );
      },
    );
  }
}
