import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/widgets/app_bottom_nav_bar.dart';
import 'package:store/data/models/product_model.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppShellState extends Equatable {
  final int activeTabIndex;
  final ProductModel? selectedProduct;
  final bool isProductDetailsOpen;
  final bool isCheckoutOpen;
  final bool isOrderTrackingOpen;
  final BottomNavStyle bottomNavStyle;

  const AppShellState({
    this.activeTabIndex = 0,
    this.selectedProduct,
    this.isProductDetailsOpen = false,
    this.isCheckoutOpen = false,
    this.isOrderTrackingOpen = false,
    this.bottomNavStyle = BottomNavStyle.classic,
  });

  AppShellState copyWith({
    int? activeTabIndex,
    ProductModel? selectedProduct,
    bool? isProductDetailsOpen,
    bool? isCheckoutOpen,
    bool? isOrderTrackingOpen,
    BottomNavStyle? bottomNavStyle,
  }) {
    return AppShellState(
      activeTabIndex: activeTabIndex ?? this.activeTabIndex,
      selectedProduct: selectedProduct ?? this.selectedProduct,
      isProductDetailsOpen: isProductDetailsOpen ?? this.isProductDetailsOpen,
      isCheckoutOpen: isCheckoutOpen ?? this.isCheckoutOpen,
      isOrderTrackingOpen: isOrderTrackingOpen ?? this.isOrderTrackingOpen,
      bottomNavStyle: bottomNavStyle ?? this.bottomNavStyle,
    );
  }

  @override
  List<Object?> get props => [
        activeTabIndex,
        selectedProduct,
        isProductDetailsOpen,
        isCheckoutOpen,
        isOrderTrackingOpen,
        bottomNavStyle,
      ];
}

class AppShellCubit extends Cubit<AppShellState> {
  static const _bottomNavStyleKey = 'app_shell_bottom_nav_style_v1';

  AppShellCubit() : super(const AppShellState()) {
    unawaited(_loadSavedStyle());
  }

  Future<void> _loadSavedStyle() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final saved = prefs.getString(_bottomNavStyleKey);
      if (saved == 'floating') {
        emit(state.copyWith(bottomNavStyle: BottomNavStyle.floating));
      } else if (saved == 'classic') {
        emit(state.copyWith(bottomNavStyle: BottomNavStyle.classic));
      }
    } catch (_) {}
  }

  void setTab(int index) {
    emit(state.copyWith(
      activeTabIndex: index,
      isProductDetailsOpen: false,
      isCheckoutOpen: false,
      isOrderTrackingOpen: false,
    ));
  }

  void openProductDetails(ProductModel product) {
    emit(state.copyWith(
      selectedProduct: product,
      isProductDetailsOpen: true,
      isCheckoutOpen: false,
      isOrderTrackingOpen: false,
    ));
  }

  void closeProductDetails() {
    emit(state.copyWith(isProductDetailsOpen: false));
  }

  void openCheckout() {
    emit(state.copyWith(
      isCheckoutOpen: true,
      isProductDetailsOpen: false,
      isOrderTrackingOpen: false,
    ));
  }

  void closeCheckout() {
    emit(state.copyWith(isCheckoutOpen: false));
  }

  void openOrderTracking() {
    emit(state.copyWith(
      isOrderTrackingOpen: true,
      isCheckoutOpen: false,
      isProductDetailsOpen: false,
    ));
  }

  void closeOrderTracking() {
    emit(state.copyWith(isOrderTrackingOpen: false));
  }

  Future<void> setBottomNavStyle(BottomNavStyle style) async {
    emit(state.copyWith(bottomNavStyle: style));
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _bottomNavStyleKey,
        style == BottomNavStyle.floating ? 'floating' : 'classic',
      );
    } catch (_) {}
  }

  void toggleBottomNavStyle() {
    final nextStyle = state.bottomNavStyle == BottomNavStyle.floating
        ? BottomNavStyle.classic
        : BottomNavStyle.floating;
    unawaited(setBottomNavStyle(nextStyle));
  }
}
