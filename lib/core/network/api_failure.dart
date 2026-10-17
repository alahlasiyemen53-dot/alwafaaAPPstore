import 'package:dio/dio.dart';

enum ApiFailureKind {
  network,
  timeout,
  unauthenticated,
  forbidden,
  notFound,
  conflict,
  validation,
  rateLimited,
  server,
  malformedResponse,
  cancelled,
  unknown,
}

class ApiFailure implements Exception {
  final ApiFailureKind kind;
  final String message;
  final int? statusCode;
  final String? code;
  final Map<String, List<String>> fieldErrors;
  final String? requestId;
  final int? retryAfterSeconds;

  const ApiFailure({
    required this.kind,
    required this.message,
    this.statusCode,
    this.code,
    this.fieldErrors = const {},
    this.requestId,
    this.retryAfterSeconds,
  });

  factory ApiFailure.fromDio(DioException exception) {
    final transportFailure = _transportFailure(exception.type);
    return transportFailure ?? _responseFailure(exception);
  }

  static ApiFailure _responseFailure(DioException exception) {
    final status = exception.response?.statusCode;
    final body = exception.response?.data;
    final envelope =
        body is Map<String, dynamic> ? body : const <String, dynamic>{};
    final payload = envelope['data'] is Map<String, dynamic>
        ? envelope['data'] as Map<String, dynamic>
        : const <String, dynamic>{};

    return ApiFailure(
      kind: _kindForStatus(status),
      message: envelope['message'] is String
          ? envelope['message'] as String
          : 'تعذر إكمال الطلب.',
      statusCode: status,
      code: payload['code'] is String ? payload['code'] as String : null,
      fieldErrors: _fieldErrors(payload['errors']),
      requestId: exception.response?.headers.value('x-request-id'),
      retryAfterSeconds: int.tryParse(
        exception.response?.headers.value('retry-after') ?? '',
      ),
    );
  }

  static ApiFailure? _transportFailure(DioExceptionType type) {
    if (const {
      DioExceptionType.connectionTimeout,
      DioExceptionType.sendTimeout,
      DioExceptionType.receiveTimeout,
    }.contains(type)) {
      return const ApiFailure(
        kind: ApiFailureKind.timeout,
        message: 'انتهت مهلة الاتصال. يرجى المحاولة مجدداً.',
      );
    }
    return switch (type) {
      DioExceptionType.connectionError => const ApiFailure(
          kind: ApiFailureKind.network,
          message: 'تعذر الاتصال بالشبكة.',
        ),
      DioExceptionType.cancel => const ApiFailure(
          kind: ApiFailureKind.cancelled,
          message: 'تم إلغاء الطلب.',
        ),
      _ => null,
    };
  }

  static ApiFailureKind _kindForStatus(int? status) => switch (status) {
        401 => ApiFailureKind.unauthenticated,
        403 => ApiFailureKind.forbidden,
        404 => ApiFailureKind.notFound,
        409 => ApiFailureKind.conflict,
        422 => ApiFailureKind.validation,
        429 => ApiFailureKind.rateLimited,
        int value when value >= 500 => ApiFailureKind.server,
        _ => ApiFailureKind.unknown,
      };

  static Map<String, List<String>> _fieldErrors(Object? rawErrors) {
    if (rawErrors is! Map) return const {};
    return {
      for (final entry in rawErrors.entries)
        if (entry.key is String && entry.value is List)
          entry.key as String:
              (entry.value as List).whereType<String>().toList(growable: false),
    };
  }

  @override
  String toString() => message;
}
