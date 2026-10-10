import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/core/network/api_client.dart';
import 'package:hitlease_mobile/core/network/api_exception.dart';
import 'package:hitlease_mobile/core/storage/token_storage.dart';

import '../helpers/fake_http_adapter.dart';

void main() {
  late MemoryTokenStorage storage;
  late bool unauthorizedCalled;

  Dio buildDio(FakeHttpAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))..httpClientAdapter = adapter;
    dio.interceptors.add(
      AuthInterceptor(storage: storage, localeCode: () => 'es', onUnauthorized: () => unauthorizedCalled = true),
    );
    return dio;
  }

  setUp(() {
    storage = MemoryTokenStorage();
    unauthorizedCalled = false;
  });

  test('adds bearer token and language headers', () async {
    await storage.write('1|hls_secret');
    final adapter = FakeHttpAdapter((_) => (200, {'ok': true}));

    await buildDio(adapter).get<dynamic>('/auth/me');

    expect(adapter.requests.single.headers['Authorization'], 'Bearer 1|hls_secret');
    expect(adapter.requests.single.headers['Accept-Language'], 'es');
  });

  test('maps the API error envelope to ApiException', () async {
    final adapter = FakeHttpAdapter(
      (_) => (
        422,
        errorJson('validation_failed', 'The given data was invalid.', {
          'email': ['The email has already been taken.'],
        }),
      ),
    );

    final error = await guardApi(() => buildDio(adapter).post<dynamic>('/auth/register'))
        .then<Object?>((_) => null, onError: (Object e) => e);

    expect(error, isA<ApiException>());
    final apiError = error! as ApiException;
    expect(apiError.code, 'validation_failed');
    expect(apiError.statusCode, 422);
    expect(apiError.fieldError('email'), 'The email has already been taken.');
    expect(apiError.requestId, 'req-1');
  });

  test('a 401 with a token clears storage and notifies', () async {
    await storage.write('expired');
    final adapter = FakeHttpAdapter((_) => (401, errorJson('unauthenticated', 'Authentication is required.')));

    await expectLater(guardApi(() => buildDio(adapter).get<dynamic>('/auth/me')), throwsA(isA<ApiException>()));

    expect(await storage.read(), isNull);
    expect(unauthorizedCalled, isTrue);
  });

  test('network failures become network_error', () {
    final exception = ApiException.fromDio(
      DioException(
        requestOptions: RequestOptions(path: '/x'),
        type: DioExceptionType.connectionError,
      ),
    );
    expect(exception.code, 'network_error');
    expect(exception.statusCode, 0);
  });
}
