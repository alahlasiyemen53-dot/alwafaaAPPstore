import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/data/models/loyalty/customer_loyalty_model.dart';
import 'package:store/data/repositories/loyalty_repository.dart';

enum LoyaltyStatus { initial, loading, success, failure }

class LoyaltyState extends Equatable {
  final LoyaltyStatus status;
  final CustomerLoyaltyModel loyalty;
  final String? errorMessage;

  const LoyaltyState({
    this.status = LoyaltyStatus.initial,
    this.loyalty = const CustomerLoyaltyModel(),
    this.errorMessage,
  });

  LoyaltyState copyWith({
    LoyaltyStatus? status,
    CustomerLoyaltyModel? loyalty,
    String? errorMessage,
    bool clearErrorMessage = false,
  }) {
    return LoyaltyState(
      status: status ?? this.status,
      loyalty: loyalty ?? this.loyalty,
      errorMessage: clearErrorMessage ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, loyalty, errorMessage];
}

class LoyaltyCubit extends Cubit<LoyaltyState> {
  final LoyaltyRepository _loyaltyRepository;

  LoyaltyCubit({LoyaltyRepository? loyaltyRepository})
      : _loyaltyRepository = loyaltyRepository ?? LoyaltyRepositoryImpl(),
        super(const LoyaltyState());

  Future<void> fetchLoyalty() async {
    emit(state.copyWith(status: LoyaltyStatus.loading, clearErrorMessage: true));
    try {
      final loyalty = await _loyaltyRepository.getLoyalty();
      emit(state.copyWith(
        status: LoyaltyStatus.success,
        loyalty: loyalty,
      ));
    } on ApiFailure catch (e) {
      emit(state.copyWith(
        status: LoyaltyStatus.failure,
        errorMessage: e.message,
      ));
    } catch (_) {
      emit(state.copyWith(
        status: LoyaltyStatus.failure,
        errorMessage: 'تعذر جلب بيانات نقاط الولاء.',
      ));
    }
  }

  void clearLoyalty() {
    emit(const LoyaltyState());
  }
}
