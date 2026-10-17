import 'api_failure.dart';

final class ApiEnvelope<T> {
  final String? message;
  final T data;

  const ApiEnvelope({required this.data, this.message});

  factory ApiEnvelope.parse(
    Object? body,
    T Function(Object? data) decode,
  ) {
    if (body is! Map<String, dynamic> ||
        body['status'] != true ||
        !body.containsKey('data')) {
      throw const ApiFailure(
        kind: ApiFailureKind.malformedResponse,
        message: 'استجابة الخادم غير صالحة.',
      );
    }

    try {
      return ApiEnvelope(
        message: body['message'] as String?,
        data: decode(body['data']),
      );
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const ApiFailure(
        kind: ApiFailureKind.malformedResponse,
        message: 'استجابة الخادم غير صالحة.',
      );
    }
  }
}
