import 'api_failure.dart';

class NetworkException extends ApiFailure {
  NetworkException(String message, [int? statusCode])
      : super(
          kind: ApiFailureKind.unknown,
          message: message,
          statusCode: statusCode,
        );
}
