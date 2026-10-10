import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/core/router/app_router.dart';

void main() {
  const loading = AsyncLoading<Object?>();
  const signedOut = AsyncData<Object?>(null);
  const signedIn = AsyncData<Object?>('user');

  test('shows the splash while the session is restored', () {
    expect(authRedirect(auth: loading, location: Routes.home), Routes.splash);
    expect(authRedirect(auth: loading, location: Routes.splash), isNull);
  });

  test('guests can only reach public routes', () {
    expect(authRedirect(auth: signedOut, location: Routes.profile), Routes.login);
    expect(authRedirect(auth: signedOut, location: Routes.splash), Routes.login);
    expect(authRedirect(auth: signedOut, location: Routes.register), isNull);
  });

  test('signed-in users skip auth screens', () {
    expect(authRedirect(auth: signedIn, location: Routes.login), Routes.home);
    expect(authRedirect(auth: signedIn, location: Routes.splash), Routes.home);
    expect(authRedirect(auth: signedIn, location: Routes.favorites), isNull);
  });
}
