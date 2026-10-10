import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/features/agents/presentation/agents_screen.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

Map<String, dynamic> agentJson() => {
  'slug': 'maria-lopez',
  'full_name': 'Maria Lopez',
  'first_name': 'Maria',
  'photo_url': null,
  'email': 'maria@example.com',
  'phone': '+1 201 555 0100',
  'bio': 'Hoboken specialist.',
  'bio_es': 'Especialista en Hoboken.',
  'languages': ['en', 'es'],
  'specialties': ['condos'],
  'agency': {'name': 'App Inmobiliaria Real Estate', 'license_number': null},
  'office': null,
  'license': {'type': 'salesperson', 'type_label': 'Salesperson', 'state': 'NJ', 'number': '1234567', 'verified': true},
};

void main() {
  testWidgets('directory searches by name', (tester) async {
    final adapter = FakeHttpAdapter((o) => (200, page([agentJson()])));
    await pumpScreen(tester, const AgentsScreen(), adapter: adapter);

    expect(find.text('Maria Lopez'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('agents-search')), 'maria');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    expect(adapter.requests.last.queryParameters['search'], 'maria');

    await tester.tap(find.text('Maria Lopez'));
    await tester.pumpAndSettle();
    expect(find.text('route:/agents/maria-lopez'), findsOneWidget);
  });

  testWidgets('profile shows bio, licence and lets signed-in users message the agent', (tester) async {
    final adapter = FakeHttpAdapter((o) => (200, {'data': agentJson()}));
    await pumpScreen(
      tester,
      const AgentProfileScreen(slug: 'maria-lopez'),
      adapter: adapter,
      user: testUser(permissions: ['messages.use']),
    );

    expect(find.text('Hoboken specialist.'), findsOneWidget);
    expect(find.textContaining('licence verified'), findsOneWidget);
    await tester.tap(find.byKey(const Key('agent-message')));
    await tester.pumpAndSettle();
    expect(find.textContaining('route:/conversation/new?agent=maria-lopez'), findsOneWidget);
  });
}
