import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/core/router/app_router.dart';

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

  test('links from e-mails open without signing in', () {
    expect(authRedirect(auth: signedOut, location: Routes.resetPassword), isNull);
    expect(authRedirect(auth: signedIn, location: Routes.unsubscribe), isNull);
    expect(authRedirect(auth: loading, location: Routes.verifyEmail), isNull);
  });

  test('a protected link survives sign-in', () {
    final link = Uri.parse('/home/leads/5');
    final toLogin = authRedirect(auth: signedOut, location: '/home/leads/5', uri: link)!;
    expect(toLogin, '/login?from=%2Fhome%2Fleads%2F5');

    final loginUri = Uri.parse(toLogin);
    expect(authRedirect(auth: signedOut, location: Routes.login, uri: loginUri), isNull);
    expect(authRedirect(auth: signedIn, location: Routes.login, uri: loginUri), '/home/leads/5');
  });

  test('a protected link survives the session restore on cold start', () {
    final toSplash = authRedirect(auth: loading, location: '/home/offers/3', uri: Uri.parse('/home/offers/3'))!;
    expect(authRedirect(auth: signedIn, location: Routes.splash, uri: Uri.parse(toSplash)), '/home/offers/3');
  });

  test('website links map to app screens', () {
    String? map(String path) => appLocationForWebLink(Uri.parse(path));
    expect(map('/properties/12-main-st-hoboken'), '/property/12-main-st-hoboken');
    expect(map('/dashboard/messages/4'), '/conversation/4');
    expect(map('/dashboard/crm/leads/9'), '/home/leads/9');
    expect(map('/dashboard/transactions/2'), '/home/transactions/2');
    expect(map('/dashboard/offers/7'), '/home/offers/7');
    expect(map('/dashboard/showings'), '/home/showings');
    expect(map('/dashboard/messages/abc'), isNull);
    expect(map('/agents/maria'), isNull);
    expect(map('/reset-password'), isNull);
  });
}
