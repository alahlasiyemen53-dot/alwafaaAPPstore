import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:store/core/constants/app_colors.dart';

class FilterOptionItem<T> {
  const FilterOptionItem({
    required this.value,
    required this.label,
    this.subtitle,
  });

  final T value;
  final String label;
  final String? subtitle;
}

/// Single-select option list; applies only via the apply button.
class FilterOptionList<T> extends StatefulWidget {
  const FilterOptionList({
    super.key,
    required this.items,
    required this.initialSelected,
    required this.onApply,
    this.applyLabel = 'تطبيق الفلاتر',
    this.showClearOption = false,
    this.clearLabel = 'الكل',
  });

  final List<FilterOptionItem<T>> items;
  final T? initialSelected;
  final ValueChanged<T?> onApply;
  final String applyLabel;
  final bool showClearOption;
  final String clearLabel;

  @override
  State<FilterOptionList<T>> createState() => _FilterOptionListState<T>();
}

class _FilterOptionListState<T> extends State<FilterOptionList<T>> {
  late T? _selected;

  @override
  void initState() {
    super.initState();
    _selected = widget.initialSelected;
  }

  void _select(T? value) {
    setState(() => _selected = value);
  }

  void _apply() {
    widget.onApply(_selected);
    Navigator.of(context).maybePop();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ConstrainedBox(
          constraints: BoxConstraints(maxHeight: 360.h),
          child: ListView(
            shrinkWrap: true,
            children: [
              if (widget.showClearOption)
                FilterOptionTile(
                  label: widget.clearLabel,
                  selected: _selected == null,
                  onTap: () => _select(null),
                ),
              ...widget.items.map(
                (item) => FilterOptionTile(
                  label: item.label,
                  subtitle: item.subtitle,
                  selected: _selected != null && _selected == item.value,
                  onTap: () => _select(item.value),
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: 12.h),
        FilterApplyButton(label: widget.applyLabel, onPressed: _apply),
      ],
    );
  }
}

/// Multi-select option list; selection is applied only via [onApply].
class FilterMultiOptionList<T> extends StatefulWidget {
  const FilterMultiOptionList({
    super.key,
    required this.items,
    required this.initialSelected,
    required this.onApply,
    this.applyLabel = 'تطبيق الفلاتر',
    this.clearLabel = 'الكل',
    this.showClearOption = true,
  });

  final List<FilterOptionItem<T>> items;
  final Set<T> initialSelected;
  final ValueChanged<Set<T>> onApply;
  final String applyLabel;
  final String clearLabel;
  final bool showClearOption;

  @override
  State<FilterMultiOptionList<T>> createState() =>
      _FilterMultiOptionListState<T>();
}

class _FilterMultiOptionListState<T> extends State<FilterMultiOptionList<T>> {
  late Set<T> _selected;

  @override
  void initState() {
    super.initState();
    _selected = Set<T>.from(widget.initialSelected);
  }

  void _toggle(T value) {
    setState(() {
      if (_selected.contains(value)) {
        _selected.remove(value);
      } else {
        _selected.add(value);
      }
    });
  }

  void _clear() {
    setState(() => _selected.clear());
  }

  void _apply() {
    widget.onApply(Set<T>.from(_selected));
    Navigator.of(context).maybePop();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ConstrainedBox(
          constraints: BoxConstraints(maxHeight: 360.h),
          child: ListView(
            shrinkWrap: true,
            children: [
              if (widget.showClearOption)
                FilterOptionTile(
                  label: widget.clearLabel,
                  selected: _selected.isEmpty,
                  onTap: _clear,
                ),
              ...widget.items.map(
                (item) => FilterOptionTile(
                  label: item.label,
                  subtitle: item.subtitle,
                  selected: _selected.contains(item.value),
                  onTap: () => _toggle(item.value),
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: 12.h),
        FilterApplyButton(label: widget.applyLabel, onPressed: _apply),
      ],
    );
  }
}

class FilterApplyButton extends StatelessWidget {
  const FilterApplyButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primaryDark,
          foregroundColor: Colors.white,
          padding: EdgeInsets.symmetric(vertical: 12.h),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14.r),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13.sp,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class FilterToggleTile extends StatelessWidget {
  const FilterToggleTile({
    super.key,
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      activeThumbColor: AppColors.primaryDark,
      title: Text(
        title,
        style: TextStyle(
          fontSize: 13.sp,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
      ),
      value: value,
      onChanged: onChanged,
    );
  }
}

/// Option row with a checkbox that is always visible (checked or unchecked).
class FilterOptionTile extends StatelessWidget {
  const FilterOptionTile({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.subtitle,
  });

  final String label;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12.r),
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 8.h, horizontal: 4.w),
        child: Row(
          children: [
            Icon(
              selected
                  ? Icons.check_box_rounded
                  : Icons.check_box_outline_blank_rounded,
              size: 22.w,
              color: selected ? AppColors.primaryDark : AppColors.textMuted,
            ),
            SizedBox(width: 10.w),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 13.sp,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      color: selected
                          ? AppColors.primaryDark
                          : AppColors.textPrimary,
                    ),
                  ),
                  if (subtitle != null && subtitle!.isNotEmpty)
                    Text(
                      subtitle!,
                      style: TextStyle(
                        fontSize: 11.sp,
                        color: AppColors.textMuted,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Compact selectable chip used in wrap layouts (sheet / dense pickers).
class FilterSelectChip extends StatelessWidget {
  const FilterSelectChip({
    super.key,
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primaryDark : AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(14.r),
          border: Border.all(
            color: isSelected ? AppColors.primaryDark : AppColors.border,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isSelected
                  ? Icons.check_box_rounded
                  : Icons.check_box_outline_blank_rounded,
              size: 16.w,
              color: isSelected ? Colors.white : AppColors.textMuted,
            ),
            SizedBox(width: 6.w),
            Text(
              label,
              style: TextStyle(
                fontSize: 11.sp,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                color: isSelected ? Colors.white : AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
