import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/features/account/presentation/account_screens.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  testWidgets('change password validates and keeps this device signed in', (tester) async {
    Object? sent;
    final adapter = FakeHttpAdapter((o) {
      sent = o.data;
      return (200, {'message': 'Password updated.'});
    });
    await pumpScreen(tester, const ChangePasswordScreen(), adapter: adapter, user: testUser());

    await tester.enterText(find.byKey(const Key('password-current')), 'old-Password-1');
    await tester.enterText(find.byKey(const Key('password-new')), 'New-Password-2026');
    await tester.enterText(find.byKey(const Key('password-confirm')), 'Different-2026!');
    await tester.tap(find.byKey(const Key('password-save')));
    await tester.pumpAndSettle();
    expect(find.text('The passwords do not match.'), findsOneWidget);
    expect(adapter.requests, isEmpty);

    await tester.enterText(find.byKey(const Key('password-confirm')), 'New-Password-2026');
    await tester.tap(find.byKey(const Key('password-save')));
    await tester.pumpAndSettle();
    expect(sent, {
      'current_password': 'old-Password-1',
      'password': 'New-Password-2026',
      'password_confirmation': 'New-Password-2026',
      'logout_other_sessions': true,
    });
  });

  testWidgets('security lists devices and revokes another one', (tester) async {
    final adapter = FakeHttpAdapter((o) {
      if (o.method == 'DELETE') return (204, null);
      return (
        200,
        {
          'data': [
            {
              'id': 'token:1',
              'type': 'token',
              'name': 'Hitlease Mobile (android)',
              'last_used_at': null,
              'is_current': true,
            },
            {'id': 'web:abc', 'type': 'web', 'name': 'Browser session', 'last_used_at': null, 'is_current': false},
          ],
        },
      );
    });
    await pumpScreen(tester, const SecurityScreen(), adapter: adapter, user: testUser());

    expect(find.textContaining('this device'), findsOneWidget);
    expect(find.byKey(const Key('mfa-enable')), findsOneWidget);
    await tester.tap(find.byTooltip('Sign out'));
    await tester.pumpAndSettle();
    expect(adapter.requests.any((r) => r.method == 'DELETE' && r.path == '/auth/sessions/web%3Aabc'), isTrue);
  });

  testWidgets('e-mail preferences toggle one category', (tester) async {
    Object? sent;
    final prefs = {
      for (final e in ['messages', 'marketing']) e: {'mail': e == 'messages', 'consented_at': null},
    };
    final adapter = FakeHttpAdapter((o) {
      if (o.method == 'PUT') sent = o.data;
      return (200, {'data': prefs});
    });
    await pumpScreen(tester, const NotificationPreferencesScreen(), adapter: adapter, user: testUser());

    await scrollTo(tester, find.byKey(const Key('pref-marketing')));
    await tester.tap(find.byKey(const Key('pref-marketing')));
    await tester.pumpAndSettle();
    expect(sent, {
      'mail': {'marketing': true},
    });
  });
}
