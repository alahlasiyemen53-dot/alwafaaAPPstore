import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/core/utils/currency_formatter.dart';
import 'package:store/data/models/currency_model.dart';
import 'package:store/data/repositories/reference_repository.dart';

class CurrencyState extends Equatable {
  final List<CurrencyModel> currencies;
  final CurrencyModel selectedCurrency;
  final bool isLoading;

  const CurrencyState({
    required this.currencies,
    required this.selectedCurrency,
    this.isLoading = false,
  });

  bool get hasCurrencies => currencies.isNotEmpty;

  CurrencyState copyWith({
    List<CurrencyModel>? currencies,
    CurrencyModel? selectedCurrency,
    bool? isLoading,
  }) {
    return CurrencyState(
      currencies: currencies ?? this.currencies,
      selectedCurrency: selectedCurrency ?? this.selectedCurrency,
      isLoading: isLoading ?? this.isLoading,
    );
  }

  String formatProductBasePrice(num baseAmount) {
    final currency = selectedCurrency;
    // Before currencies load, avoid "غير متاح" — show a sensible base fallback.
    if (currency.code.isEmpty || currency.id == 0) {
      return CurrencyFormatter.format(
        baseAmount,
        symbol: 'ر.ي',
        decimalDigits: 0,
      );
    }
    if (currency.isBase) {
      return _format(baseAmount, currency);
    }

    final rate = double.tryParse(currency.rate ?? '');
    if (rate == null || rate <= 0) {
      return 'غير متاح';
    }

    return _format(baseAmount / rate, currency);
  }

  String formatServerPrice(
    num amount, {
    required String symbol,
    int decimalDigits = 0,
  }) {
    return CurrencyFormatter.format(
      amount,
      symbol: symbol,
      decimalDigits: decimalDigits,
    );
  }

  String _format(num amount, CurrencyModel currency) =>
      CurrencyFormatter.format(
        amount,
        symbol: currency.symbol,
        decimalDigits: currency.decimalPlaces,
      );

  @override
  List<Object?> get props => [currencies, selectedCurrency, isLoading];
}

class CurrencyCubit extends Cubit<CurrencyState> {
  final ReferenceRepository _referenceRepository;
  static const _unselectedCurrency = CurrencyModel(
    id: 0,
    code: '',
    symbol: '',
    nameAr: '',
    isActive: false,
  );

  CurrencyCubit({required ReferenceRepository referenceRepository})
      : _referenceRepository = referenceRepository,
        super(
          const CurrencyState(
            currencies: [],
            selectedCurrency: _unselectedCurrency,
          ),
        );

  /// Loads currencies if missing. Safe to call on every screen open.
  Future<void> ensureLoaded() async {
    if (state.hasCurrencies) return;
    try {
      await loadCurrencies(force: true).timeout(
        const Duration(seconds: 3),
      );
    } catch (_) {
      // Bounded so caller/splash is never hung on slow networks or retries.
    }
  }

  Future<void> loadCurrencies({bool force = false}) async {
    if (!force && state.hasCurrencies) return;
    if (!force && state.isLoading) return;

    emit(state.copyWith(isLoading: true));
    try {
      final currencies = await _loadCurrenciesWithRetry();
      if (isClosed) return;
      if (currencies.isNotEmpty) {
        final currentSelected = _resolveSelected(currencies);
        emit(state.copyWith(
          currencies: currencies,
          selectedCurrency: currentSelected,
          isLoading: false,
        ));
        return;
      }
    } on ApiFailure {
      // Keep the existing server-derived selection when a refresh fails.
    } catch (_) {
      // Network/timeout — leave previous selection if any.
    }
    if (!isClosed) {
      emit(state.copyWith(isLoading: false));
    }
  }

  Future<List<CurrencyModel>> _loadCurrenciesWithRetry() async {
    Object? lastError;
    for (var attempt = 0; attempt < 3; attempt++) {
      try {
        final currencies = await _referenceRepository.getCurrencies();
        if (currencies.isNotEmpty) return currencies;
      } catch (error) {
        lastError = error;
      }
      if (attempt < 2) {
        await Future<void>.delayed(
          Duration(milliseconds: 500 * (attempt + 1)),
        );
      }
    }
    if (lastError != null) {
      if (lastError is ApiFailure) throw lastError;
      throw lastError;
    }
    return const [];
  }

  CurrencyModel _resolveSelected(List<CurrencyModel> currencies) {
    final selectedCode = state.selectedCurrency.code.trim();
    if (selectedCode.isNotEmpty) {
      for (final currency in currencies) {
        if (currency.code == selectedCode) return currency;
      }
    }
    for (final currency in currencies) {
      if (currency.isBase) return currency;
    }
    for (final currency in currencies) {
      if (currency.isDefault) return currency;
    }
    return currencies.first;
  }

  void selectCurrency(String code) {
    final normalized = code.trim().toUpperCase();
    final currency = state.currencies.firstWhere(
      (c) => c.code == normalized,
      orElse: () => state.selectedCurrency,
    );
    emit(state.copyWith(selectedCurrency: currency));
  }
}
