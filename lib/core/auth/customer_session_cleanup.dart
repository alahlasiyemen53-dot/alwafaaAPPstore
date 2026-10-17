typedef SessionCleanupCallback = void Function();

abstract interface class CustomerSessionCleanup {
  void registerCleanupCallback(SessionCleanupCallback callback);
  void unregisterCleanupCallback(SessionCleanupCallback callback);
  void clearCustomerScopedData();
}

final class DefaultCustomerSessionCleanup implements CustomerSessionCleanup {
  final List<SessionCleanupCallback> _callbacks = [];

  @override
  void registerCleanupCallback(SessionCleanupCallback callback) {
    if (!_callbacks.contains(callback)) {
      _callbacks.add(callback);
    }
  }

  @override
  void unregisterCleanupCallback(SessionCleanupCallback callback) {
    _callbacks.remove(callback);
  }

  @override
  void clearCustomerScopedData() {
    for (final callback in List<SessionCleanupCallback>.from(_callbacks)) {
      try {
        callback();
      } catch (_) {
        // Safe execution: prevent one cleanup error from blocking others
      }
    }
  }
}
