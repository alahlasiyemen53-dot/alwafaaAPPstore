import 'package:dio/dio.dart';
import '../auth/token_store.dart';
import '../config/app_config.dart';
import 'api_failure.dart';

class DioClient {
  final Dio _dio;
  final TokenStore _tokenStore;
  final void Function()? _onUnauthorized;
  static int _requestSequence = 0;

  DioClient({
    Dio? dio,
    AppConfig? config,
    TokenStore? tokenStore,
    this._onUnauthorized,
  })  : _dio = dio ?? Dio(),
        _tokenStore = tokenStore ?? FlutterSecureTokenStore() {
    final resolvedConfig = config ?? AppConfig.current();
    _dio.options = BaseOptions(
      baseUrl: resolvedConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      sendTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: const {'Accept': 'application/json'},
    );
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStore.readAccessToken();
          if (token != null && token.isNotEmpty) {
            options.headers.putIfAbsent('Authorization', () => 'Bearer $token');
          }
          options.headers.putIfAbsent('X-Request-Id', _nextRequestId);
          return handler.next(options);
        },
      ),
    );
  }

  Dio get dio => _dio;

  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.get(path,
          queryParameters: queryParameters, options: options);
    } on DioException catch (e) {
      await _clearInvalidSession(e);
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Response> post(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.post(path,
          data: data, queryParameters: queryParameters, options: options);
    } on DioException catch (e) {
      await _clearInvalidSession(e);
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Response> put(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.put(path,
          data: data, queryParameters: queryParameters, options: options);
    } on DioException catch (e) {
      await _clearInvalidSession(e);
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Response> patch(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.patch(path,
          data: data, queryParameters: queryParameters, options: options);
    } on DioException catch (e) {
      await _clearInvalidSession(e);
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Response> delete(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.delete(path,
          data: data, queryParameters: queryParameters, options: options);
    } on DioException catch (e) {
      await _clearInvalidSession(e);
      throw ApiFailure.fromDio(e);
    }
  }

  Future<void> _clearInvalidSession(DioException exception) async {
    if (exception.response?.statusCode == 401) {
      await _tokenStore.clearSession();
      _onUnauthorized?.call();
    }
  }

  static String _nextRequestId() =>
      'flutter-${DateTime.now().microsecondsSinceEpoch}-${_requestSequence++}';
}
