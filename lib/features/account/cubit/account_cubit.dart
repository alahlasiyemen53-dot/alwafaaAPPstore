import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:store/core/auth/customer_session_cleanup.dart';
import 'package:store/core/network/api_failure.dart';
import 'package:store/data/models/auth/otp_challenge_model.dart';
import 'package:store/data/models/user_model.dart';
import 'package:store/data/repositories/account_repository.dart';
import 'package:store/data/repositories/auth_repository.dart';

enum AuthStatus {
  unknown,
  unauthenticated,
  requestingOtp,
  challengeReady,
  verifyingOtp,
  authenticated,
  loggingOut,
}

final class AccountState extends Equatable {
  final AuthStatus status;
  final UserModel? user;
  final OtpChallengeModel? challenge;
  final int? otpCountryId;
  final ApiFailure? failure;

  const AccountState({
    this.status = AuthStatus.unknown,
    this.user,
    this.challenge,
    this.otpCountryId,
    this.failure,
  });

  bool get isLoggedIn => status == AuthStatus.authenticated && user != null;
  bool get isBusy => const {
        AuthStatus.requestingOtp,
        AuthStatus.verifyingOtp,
        AuthStatus.loggingOut,
      }.contains(status);

  String? get phoneError => failure?.fieldErrors['phone']?.firstOrNull;
  String? get codeError => failure?.fieldErrors['code']?.firstOrNull;

  @override
  List<Object?> get props => [status, user, challenge, otpCountryId, failure];
}

final class AccountCubit extends Cubit<AccountState> {
  late final AuthRepository _repository;
  final AccountRepository _accountRepository;
  final CustomerSessionCleanup? _sessionCleanup;

  AccountCubit({
    AuthRepository? repository,
    AccountRepository? accountRepository,
    this._sessionCleanup,
  })  : _accountRepository = accountRepository ?? AccountRepositoryImpl(),
        super(const AccountState()) {
    _repository = repository ??
        AuthRepositoryImpl(
          onUnauthorized: handleSessionExpired,
        );
  }

  void handleSessionExpired([ApiFailure? failure]) {
    if (isClosed) return;
    _sessionCleanup?.clearCustomerScopedData();
    emit(AccountState(
      status: AuthStatus.unauthenticated,
      failure: failure ??
          const ApiFailure(
            kind: ApiFailureKind.unauthenticated,
            message: 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مجدداً.',
            statusCode: 401,
            code: 'AUTH_REQUIRED',
          ),
    ));
  }

  Future<void> restoreSession() async {
    // Keep showing unknown briefly only when we have not resolved auth yet.
    if (state.status != AuthStatus.unknown) {
      emit(const AccountState());
    }
    try {
      final customer = await _repository.restoreSession();
      if (isClosed) return;
      emit(
        customer == null
            ? const AccountState(status: AuthStatus.unauthenticated)
            : AccountState(
                status: AuthStatus.authenticated,
                user: customer,
              ),
      );
    } on ApiFailure catch (failure) {
      if (isClosed) return;
      if (failure.kind == ApiFailureKind.unauthenticated) {
        _sessionCleanup?.clearCustomerScopedData();
      }
      emit(AccountState(
        status: AuthStatus.unauthenticated,
        failure: failure,
      ));
    } catch (_) {
      if (isClosed) return;
      // Network/storage glitch: treat as logged-out so the UI is never stuck
      // on "جارٍ التحقق من الجلسة الآمنة...".
      emit(const AccountState(status: AuthStatus.unauthenticated));
    }
  }

  Future<void> requestOtp(String phone, {int? countryId}) async {
    if (state.isBusy) return;

    emit(const AccountState(status: AuthStatus.requestingOtp));
    try {
      final challenge =
          await _repository.requestOtp(phone.trim(), countryId: countryId);
      emit(AccountState(
        status: AuthStatus.challengeReady,
        challenge: challenge,
        otpCountryId: countryId,
      ));
    } on ApiFailure catch (failure) {
      emit(AccountState(
        status: AuthStatus.unauthenticated,
        failure: failure,
      ));
    }
  }

  Future<void> verifyOtp(
    String code, {
    String? name,
    int? countryId,
    int? governorateId,
  }) async {
    final challenge = state.challenge;
    if (challenge == null || state.isBusy) return;

    emit(AccountState(
      status: AuthStatus.verifyingOtp,
      challenge: challenge,
      otpCountryId: state.otpCountryId,
    ));
    try {
      final customer = await _repository.verifyOtp(
        phone: challenge.phone,
        challengeId: challenge.challengeId,
        code: code.trim(),
        name: name,
        countryId: countryId ?? state.otpCountryId,
        governorateId: governorateId,
      );
      emit(AccountState(
        status: AuthStatus.authenticated,
        user: customer,
      ));
    } on ApiFailure catch (failure) {
      emit(AccountState(
        status: AuthStatus.challengeReady,
        challenge: challenge,
        otpCountryId: state.otpCountryId,
        failure: failure,
      ));
    }
  }

  Future<void> resendOtp() async {
    final challenge = state.challenge;
    if (challenge == null || DateTime.now().isBefore(challenge.canResendAt)) {
      return;
    }
    await requestOtp(challenge.phone, countryId: state.otpCountryId);
  }

  void resetChallenge() {
    if (state.isBusy) return;
    emit(const AccountState(status: AuthStatus.unauthenticated));
  }

  Future<void> logout() async {
    if (!state.isLoggedIn || state.isBusy) return;

    emit(AccountState(status: AuthStatus.loggingOut, user: state.user));
    try {
      await _repository.logout();
      emit(const AccountState(status: AuthStatus.unauthenticated));
    } on ApiFailure catch (failure) {
      emit(AccountState(
        status: AuthStatus.unauthenticated,
        failure: failure,
      ));
    } finally {
      _sessionCleanup?.clearCustomerScopedData();
    }
  }

  Future<bool> updateProfile({String? name, String? email}) async {
    if (!state.isLoggedIn || state.isBusy) return false;
    try {
      final updated = await _accountRepository.updateProfile(name: name, email: email);
      emit(AccountState(
        status: AuthStatus.authenticated,
        user: updated,
      ));
      return true;
    } on ApiFailure catch (failure) {
      emit(AccountState(
        status: AuthStatus.authenticated,
        user: state.user,
        failure: failure,
      ));
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<void> refreshProfile() async {
    if (!state.isLoggedIn) return;
    try {
      final user = await _accountRepository.getProfile();
      emit(AccountState(
        status: AuthStatus.authenticated,
        user: user,
      ));
    } catch (_) {}
  }
}
