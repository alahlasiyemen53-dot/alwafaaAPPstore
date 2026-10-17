import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

final class ConnectivityState extends Equatable {
  final bool isOffline;

  const ConnectivityState({this.isOffline = false});

  @override
  List<Object?> get props => [isOffline];
}

final class ConnectivityCubit extends Cubit<ConnectivityState> {
  ConnectivityCubit({Connectivity? connectivity})
      : _connectivity = connectivity ?? Connectivity(),
        super(const ConnectivityState()) {
    _init();
  }

  final Connectivity _connectivity;
  StreamSubscription<List<ConnectivityResult>>? _subscription;

  Future<void> _init() async {
    try {
      final results = await _connectivity.checkConnectivity();
      _emitFrom(results);
      _subscription = _connectivity.onConnectivityChanged.listen(_emitFrom);
    } catch (_) {
      // Plugin unavailable — keep online assumption.
    }
  }

  void _emitFrom(List<ConnectivityResult> results) {
    final offline = results.isEmpty ||
        results.every((r) => r == ConnectivityResult.none);
    if (isClosed) return;
    emit(ConnectivityState(isOffline: offline));
  }

  @override
  Future<void> close() async {
    await _subscription?.cancel();
    return super.close();
  }
}
