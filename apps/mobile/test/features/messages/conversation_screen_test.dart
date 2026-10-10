import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/features/messages/presentation/conversation_screen.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  testWidgets('shows the thread and sends a reply', (tester) async {
    final sent = <Object?>[];
    final messages = [
      {
        'id': 1,
        'body': 'Is the basement finished?',
        'mine': false,
        'author': {'id': 30, 'full_name': 'Bianca Buyer'},
        'created_at': '2026-10-10T12:00:00Z',
      },
    ];
    final adapter = FakeHttpAdapter((o) {
      if (o.method == 'POST') {
        sent.add(o.data);
        final m = {'id': 2, 'body': 'Yes, in 2021.', 'mine': true, 'created_at': '2026-10-10T12:05:00Z'};
        messages.add(m);
        return (201, {'data': m});
      }
      return (
        200,
        {
          'data': {
            'id': 4,
            'participants': [
              {'id': 30, 'full_name': 'Bianca Buyer'},
            ],
            'property': {'slug': 'home', 'title': 'Colonial'},
            'messages': messages,
          },
        },
      );
    });

    await pumpScreen(
      tester,
      const ConversationScreen(id: 4),
      adapter: adapter,
      user: testUser(permissions: ['messages.use']),
    );

    expect(find.text('Bianca Buyer'), findsOneWidget);
    expect(find.text('About Colonial'), findsOneWidget);
    expect(find.text('Is the basement finished?'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('message-body')), 'Yes, in 2021.');
    await tester.tap(find.byKey(const Key('message-send')));
    await tester.pumpAndSettle();

    expect(sent, [
      {'body': 'Yes, in 2021.'},
    ]);
    expect(find.text('Yes, in 2021.'), findsOneWidget);
  });
}
