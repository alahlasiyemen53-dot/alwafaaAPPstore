import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/data/models/support/customer_support_model.dart';
import 'package:store/data/repositories/support_repository.dart';

enum SupportStatus { initial, loading, success, failure }

class SupportState extends Equatable {
  final SupportStatus status;
  final CustomerSupportModel? support;
  final String? errorMessage;

  const SupportState({
    this.status = SupportStatus.initial,
    this.support,
    this.errorMessage,
  });

  SupportState copyWith({
    SupportStatus? status,
    CustomerSupportModel? support,
    String? errorMessage,
    bool clearErrorMessage = false,
  }) {
    return SupportState(
      status: status ?? this.status,
      support: support ?? this.support,
      errorMessage: clearErrorMessage ? null : (errorMessage ?? this.errorMessage),
    );
  }

  @override
  List<Object?> get props => [status, support, errorMessage];
}

class SupportCubit extends Cubit<SupportState> {
  final SupportRepository _supportRepository;

  SupportCubit({SupportRepository? supportRepository})
      : _supportRepository = supportRepository ?? SupportRepositoryImpl(),
        super(const SupportState());

  Future<void> fetchSupport() async {
    emit(state.copyWith(status: SupportStatus.loading, clearErrorMessage: true));
    try {
      final info = await _supportRepository.getSupportInfo();
      emit(state.copyWith(
        status: SupportStatus.success,
        support: info,
      ));
    } on ApiFailure catch (e) {
      emit(state.copyWith(
        status: SupportStatus.failure,
        errorMessage: e.message,
      ));
    } catch (_) {
      emit(state.copyWith(
        status: SupportStatus.failure,
        errorMessage: 'تعذر جلب معلومات خدمة العملاء.',
      ));
    }
  }
}
