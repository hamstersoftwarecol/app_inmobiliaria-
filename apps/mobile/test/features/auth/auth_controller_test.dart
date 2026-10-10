import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/core/network/api_client.dart';
import 'package:hitlease_mobile/core/network/api_exception.dart';
import 'package:hitlease_mobile/core/storage/token_storage.dart';
import 'package:hitlease_mobile/features/auth/application/auth_controller.dart';
import 'package:hitlease_mobile/features/auth/data/auth_repository.dart';

import '../../helpers/fake_http_adapter.dart';

void main() {
  late MemoryTokenStorage storage;

  ProviderContainer containerWith(FakeHttpAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))..httpClientAdapter = adapter;
    dio.interceptors.add(AuthInterceptor(storage: storage, localeCode: () => 'en', onUnauthorized: () {}));
    final container = ProviderContainer(
      overrides: [
        tokenStorageProvider.overrideWithValue(storage),
        authRepositoryProvider.overrideWithValue(AuthRepository(dio, deviceName: 'test-device')),
      ],
    );
    addTearDown(container.dispose);
    return container;
  }

  setUp(() => storage = MemoryTokenStorage());

  test('starts signed out without a stored token', () async {
    final container = containerWith(FakeHttpAdapter((_) => throw StateError('no request expected')));
    expect(await container.read(authControllerProvider.future), isNull);
  });

  test('restores the session from a stored token', () async {
    await storage.write('1|hls_token');
    final container = containerWith(FakeHttpAdapter((_) => (200, {'data': userJson()})));

    final user = await container.read(authControllerProvider.future);
    expect(user?.email, 'ana@example.com');
    expect(user?.hasRole('buyer'), isTrue);
  });

  test('drops an expired stored token', () async {
    await storage.write('expired');
    final container = containerWith(FakeHttpAdapter((_) => (401, errorJson('unauthenticated', 'Auth required'))));

    expect(await container.read(authControllerProvider.future), isNull);
    expect(await storage.read(), isNull);
  });

  test('login stores the token and exposes the user; MFA is sent when provided', () async {
    final adapter = FakeHttpAdapter(
      (o) => (
        201,
        {
          'data': userJson(mfa: true),
          'token': {'access_token': '2|hls_new', 'token_type': 'Bearer', 'expires_at': null},
        },
      ),
    );
    final container = containerWith(adapter);
    await container.read(authControllerProvider.future);

    await container
        .read(authControllerProvider.notifier)
        .login(email: ' ana@example.com ', password: 'pw', mfaCode: '123456');

    expect(await storage.read(), '2|hls_new');
    expect(container.read(authControllerProvider).value?.mfaEnabled, isTrue);
    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body, containsPair('email', 'ana@example.com'));
    expect(body, containsPair('mfa_code', '123456'));
    expect(body, containsPair('device_name', 'test-device'));
  });

  test('login surfaces mfa_required as ApiException', () async {
    final container = containerWith(FakeHttpAdapter((_) => (422, errorJson('mfa_required', 'Code required'))));
    await container.read(authControllerProvider.future);

    expect(
      () => container.read(authControllerProvider.notifier).login(email: 'a@b.co', password: 'x'),
      throwsA(isA<ApiException>().having((e) => e.code, 'code', 'mfa_required')),
    );
  });

  test('logout clears local state even if the API call fails', () async {
    await storage.write('1|hls_token');
    var calls = 0;
    final container = containerWith(
      FakeHttpAdapter((o) {
        calls++;
        return o.path.endsWith('/auth/me') ? (200, {'data': userJson()}) : (500, errorJson('server_error', 'Boom'));
      }),
    );
    expect(await container.read(authControllerProvider.future), isNotNull);

    await container.read(authControllerProvider.notifier).logout();

    expect(calls, 2);
    expect(await storage.read(), isNull);
    expect(container.read(authControllerProvider).value, isNull);
  });
}
