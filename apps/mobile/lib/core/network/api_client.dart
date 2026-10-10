import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../l10n/locale_controller.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

final tokenStorageProvider = Provider<TokenStorage>((ref) => SecureTokenStorage());

/// Called when the API rejects the stored token (expired or revoked).
final unauthorizedHandlerProvider = Provider<void Function()>((ref) => () {});

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiV1,
      connectTimeout: AppConfig.connectTimeout,
      receiveTimeout: AppConfig.receiveTimeout,
      // `X-App-Inmobiliaria-Client: mobile` lets the API apply MLS display rules for mobile apps.
      headers: {'Accept': 'application/json', 'X-App-Inmobiliaria-Client': 'mobile'},
      responseType: ResponseType.json,
    ),
  );
  dio.interceptors.add(
    AuthInterceptor(
      storage: ref.watch(tokenStorageProvider),
      localeCode: () => ref.read(localeControllerProvider).languageCode,
      onUnauthorized: () => ref.read(unauthorizedHandlerProvider)(),
    ),
  );
  return dio;
});

/// Adds the bearer token and preferred language, and turns Dio errors into
/// [ApiException]s so the rest of the app never handles raw HTTP errors.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required this.storage, required this.localeCode, required this.onUnauthorized});

  final TokenStorage storage;
  final String Function() localeCode;
  final void Function() onUnauthorized;

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await storage.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    options.headers['Accept-Language'] = localeCode();
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    final apiError = ApiException.fromDio(err);
    if (apiError.isUnauthenticated && err.requestOptions.headers.containsKey('Authorization')) {
      await storage.clear();
      onUnauthorized();
    }
    handler.reject(
      DioException(requestOptions: err.requestOptions, response: err.response, type: err.type, error: apiError),
    );
  }
}

/// Unwraps the [ApiException] attached by [AuthInterceptor].
Future<T> guardApi<T>(Future<T> Function() call) async {
  try {
    return await call();
  } on DioException catch (e) {
    final inner = e.error;
    throw inner is ApiException ? inner : ApiException.fromDio(e);
  }
}
