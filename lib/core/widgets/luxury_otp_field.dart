import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:store/core/constants/app_colors.dart';

/// A luxury, professional square-box OTP / PIN input field with smooth UI & UX.
/// Features:
/// - Individual square boxes with animated borders and active cursor.
/// - Master overlay input handling for 100% reliable paste, backspace, and IME autofill.
/// - Light haptic feedback on each digit entered.
/// - Automatic callback trigger upon entering the complete code.
/// - High-contrast luxury styling tailored to Lamma's brand palette.
class LuxuryOtpField extends StatefulWidget {
  final TextEditingController controller;
  final int length;
  final ValueChanged<String>? onCompleted;
  final ValueChanged<String>? onChanged;
  final bool hasError;
  final bool autoFocus;
  final bool enabled;

  const LuxuryOtpField({
    super.key,
    required this.controller,
    this.length = 6,
    this.onCompleted,
    this.onChanged,
    this.hasError = false,
    this.autoFocus = true,
    this.enabled = true,
  });

  @override
  State<LuxuryOtpField> createState() => _LuxuryOtpFieldState();
}

class _LuxuryOtpFieldState extends State<LuxuryOtpField>
    with SingleTickerProviderStateMixin {
  late final FocusNode _focusNode;
  late final AnimationController _blinkController;
  late final Animation<double> _blinkAnimation;

  @override
  void initState() {
    super.initState();
    _focusNode = FocusNode();
    _focusNode.addListener(_onFocusChanged);
    _blinkController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 550),
    )..repeat(reverse: true);
    _blinkAnimation = Tween<double>(begin: 1.0, end: 0.0).animate(_blinkController);

    widget.controller.addListener(_onTextChanged);

    if (widget.autoFocus) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && widget.enabled) {
          _focusInput();
        }
      });
    }
  }

  void _onFocusChanged() {
    if (_focusNode.hasFocus) {
      _ensureCursorAtEnd();
    }
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onTextChanged);
    _focusNode.removeListener(_onFocusChanged);
    _focusNode.dispose();
    _blinkController.dispose();
    super.dispose();
  }

  void _ensureCursorAtEnd() {
    final textLength = widget.controller.text.length;
    if (widget.controller.selection.baseOffset != textLength ||
        widget.controller.selection.extentOffset != textLength) {
      widget.controller.selection =
          TextSelection.collapsed(offset: textLength);
    }
  }

  void _focusInput() {
    if (!widget.enabled) return;
    _ensureCursorAtEnd();

    final isKeyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 0;

    if (!isKeyboardOpen) {
      if (_focusNode.hasFocus) {
        _focusNode.unfocus();
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted && widget.enabled) {
            _focusNode.requestFocus();
            SystemChannels.textInput.invokeMethod('TextInput.show');
          }
        });
      } else {
        _focusNode.requestFocus();
        SystemChannels.textInput.invokeMethod('TextInput.show');
      }
    } else {
      if (!_focusNode.hasFocus) {
        _focusNode.requestFocus();
      }
      SystemChannels.textInput.invokeMethod('TextInput.show');
    }
  }

  void _deleteLastDigit() {
    if (!widget.enabled) return;
    final text = widget.controller.text;
    if (text.isEmpty) return;
    HapticFeedback.selectionClick();
    widget.controller.text = text.substring(0, text.length - 1);
    _ensureCursorAtEnd();
    _focusInput();
  }

  void _clearAllDigits() {
    if (!widget.enabled) return;
    if (widget.controller.text.isEmpty) return;
    HapticFeedback.mediumImpact();
    widget.controller.clear();
    _ensureCursorAtEnd();
    _focusInput();
  }

  void _onTextChanged() {
    if (!mounted) return;
    _ensureCursorAtEnd();
    setState(() {});
    widget.onChanged?.call(widget.controller.text);

    if (widget.controller.text.length == widget.length) {
      HapticFeedback.mediumImpact();
      widget.onCompleted?.call(widget.controller.text);
    } else if (widget.controller.text.isNotEmpty) {
      HapticFeedback.selectionClick();
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = widget.controller.text;
    final isFocused = _focusNode.hasFocus && widget.enabled;

    return Directionality(
      textDirection: TextDirection.ltr,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          GestureDetector(
            onTap: _focusInput,
            behavior: HitTestBehavior.opaque,
            child: Stack(
              alignment: Alignment.center,
              children: [
                // 1. Transparent Master Input covering entire field
                Positioned.fill(
                  child: Opacity(
                    opacity: 0.0,
                    child: TextField(
                      controller: widget.controller,
                      focusNode: _focusNode,
                      enabled: widget.enabled,
                      keyboardType: TextInputType.number,
                      textInputAction: TextInputAction.done,
                      autofocus: widget.autoFocus,
                      textDirection: TextDirection.ltr,
                      maxLength: widget.length,
                      showCursor: false,
                      enableInteractiveSelection: false,
                      onTap: _focusInput,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(widget.length),
                      ],
                      decoration: const InputDecoration(
                        counterText: '',
                        border: InputBorder.none,
                        contentPadding: EdgeInsets.zero,
                      ),
                    ),
                  ),
                ),

                // 2. Visible Square Digits Row
                IgnorePointer(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: List.generate(widget.length, (index) {
                      final isFilled = index < text.length;
                      final digit = isFilled ? text[index] : '';
                      final isCurrent = index == text.length && isFocused;

                      return _buildDigitBox(
                        digit: digit,
                        isFilled: isFilled,
                        isCurrent: isCurrent,
                      );
                    }),
                  ),
                ),
              ],
            ),
          ),
          if (text.isNotEmpty && widget.enabled) ...[
            SizedBox(height: 8.h),
            Directionality(
              textDirection: TextDirection.rtl,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  InkWell(
                    onTap: _deleteLastDigit,
                    borderRadius: BorderRadius.circular(8.r),
                    child: Padding(
                      padding: EdgeInsets.symmetric(
                        horizontal: 8.w,
                        vertical: 4.h,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.backspace_outlined,
                            size: 15.w,
                            color: AppColors.error,
                          ),
                          SizedBox(width: 4.w),
                          Text(
                            'مسح رقم',
                            style: GoogleFonts.alexandria(
                              fontSize: 11.sp,
                              fontWeight: FontWeight.w600,
                              color: AppColors.error,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (text.length > 1) ...[
                    SizedBox(width: 14.w),
                    InkWell(
                      onTap: _clearAllDigits,
                      borderRadius: BorderRadius.circular(8.r),
                      child: Padding(
                        padding: EdgeInsets.symmetric(
                          horizontal: 8.w,
                          vertical: 4.h,
                        ),
                        child: Text(
                          'مسح الكل',
                          style: GoogleFonts.alexandria(
                            fontSize: 11.sp,
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildDigitBox({
    required String digit,
    required bool isFilled,
    required bool isCurrent,
  }) {
    Color borderColor;
    Color backgroundColor;
    double borderWidth;
    List<BoxShadow>? shadows;

    if (widget.hasError) {
      borderColor = AppColors.error;
      backgroundColor = AppColors.error.withValues(alpha: 0.04);
      borderWidth = 1.5;
    } else if (isCurrent) {
      borderColor = AppColors.primaryDark;
      backgroundColor = Colors.white;
      borderWidth = 2.0;
      shadows = [
        BoxShadow(
          color: AppColors.primaryDark.withValues(alpha: 0.16),
          blurRadius: 10,
          offset: const Offset(0, 3),
        ),
      ];
    } else if (isFilled) {
      borderColor = AppColors.primaryDark.withValues(alpha: 0.6);
      backgroundColor = AppColors.surfaceSoft;
      borderWidth = 1.4;
      shadows = [
        BoxShadow(
          color: AppColors.primaryDark.withValues(alpha: 0.04),
          blurRadius: 6,
          offset: const Offset(0, 2),
        ),
      ];
    } else {
      borderColor = AppColors.borderLight;
      backgroundColor = AppColors.surfaceSoft.withValues(alpha: 0.6);
      borderWidth = 1.0;
    }

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
      width: 48.w,
      height: 56.h,
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(14.r),
        border: Border.all(color: borderColor, width: borderWidth),
        boxShadow: shadows,
      ),
      alignment: Alignment.center,
      child: isCurrent && !isFilled
          ? FadeTransition(
              opacity: _blinkAnimation,
              child: Container(
                width: 2.w,
                height: 24.h,
                decoration: BoxDecoration(
                  color: AppColors.primaryDark,
                  borderRadius: BorderRadius.circular(2.r),
                ),
              ),
            )
          : Text(
              isFilled ? digit : '·',
              style: isFilled
                  ? GoogleFonts.alexandria(
                      fontSize: 18.sp,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDark,
                    )
                  : TextStyle(
                      fontSize: 22.sp,
                      color: AppColors.textMuted.withValues(alpha: 0.35),
                      fontWeight: FontWeight.w900,
                    ),
            ),
    );
  }
}
