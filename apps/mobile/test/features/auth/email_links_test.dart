import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/features/auth/presentation/email_link_screens.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  testWidgets('reset password sends the token and the new password', (tester) async {
    Object? sent;
    final adapter = FakeHttpAdapter((o) {
      sent = o.data;
      return (200, {'message': 'Your password has been reset.'});
    });
    await pumpScreen(
      tester,
      const ResetPasswordScreen(token: 'tok', email: 'ana@example.com'),
      adapter: adapter,
    );

    await tester.enterText(find.byKey(const Key('reset-password')), 'New-Password-2026');
    await tester.enterText(find.byKey(const Key('reset-confirm')), 'New-Password-2026');
    await tester.tap(find.byKey(const Key('reset-submit')));
    await tester.pumpAndSettle();

    expect(sent, {
      'token': 'tok',
      'email': 'ana@example.com',
      'password': 'New-Password-2026',
      'password_confirmation': 'New-Password-2026',
    });
    expect(find.text('Your password was changed. Sign in with the new one.'), findsOneWidget);
  });

  testWidgets('an incomplete reset link is rejected without calling the API', (tester) async {
    final adapter = FakeHttpAdapter((o) => (200, null));
    await pumpScreen(tester, const ResetPasswordScreen(token: null, email: null), adapter: adapter);
    expect(find.textContaining('no longer valid'), findsOneWidget);
    expect(adapter.requests, isEmpty);
  });

  testWidgets('verify e-mail calls the signed API URL', (tester) async {
    final adapter = FakeHttpAdapter((o) => (200, {'verified': true}));
    await pumpScreen(
      tester,
      const VerifyEmailScreen(params: {'id': '7', 'hash': 'abc', 'expires': '123', 'signature': 'sig'}),
      adapter: adapter,
    );
    final r = adapter.requests.single;
    expect(r.path, '/auth/email/verify/7/abc');
    expect(r.queryParameters, {'expires': '123', 'signature': 'sig'});
    expect(find.textContaining('is verified'), findsOneWidget);
  });

  testWidgets('unsubscribe shows the API error when the token is invalid', (tester) async {
    final adapter = FakeHttpAdapter((o) => (422, errorJson('invalid_token', 'This unsubscribe link is not valid.')));
    await pumpScreen(tester, const UnsubscribeScreen(token: 'bad'), adapter: adapter);
    expect(adapter.requests.single.data, {'token': 'bad'});
    expect(find.text('This unsubscribe link is not valid.'), findsOneWidget);
  });
}
