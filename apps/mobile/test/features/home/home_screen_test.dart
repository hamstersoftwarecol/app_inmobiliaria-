import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/features/home/presentation/home_screen.dart';
import 'package:app_inmobiliaria_mobile/features/notifications/data/notification_repository.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  final adapter = FakeHttpAdapter((_) => (200, {'data': <String>[]}));
  final overrides = [unreadNotificationsProvider.overrideWith((ref) => Stream.value(3))];

  testWidgets('buyers see search and their showings, not agent mode', (tester) async {
    await pumpScreen(
      tester,
      const HomeScreen(),
      adapter: adapter,
      overrides: overrides,
      user: testUser(permissions: ['appointments.request', 'saved_searches.manage']),
    );

    expect(find.byKey(const Key('home-search')), findsOneWidget);
    expect(find.text('Showings'), findsOneWidget);
    expect(find.text('Saved searches'), findsOneWidget);
    expect(find.byKey(const Key('home-leads')), findsNothing);
    expect(find.byTooltip('Notifications (3 unread)'), findsOneWidget);
  });

  testWidgets('agents get agent mode, in Spanish too', (tester) async {
    await pumpScreen(
      tester,
      const HomeScreen(),
      adapter: adapter,
      overrides: overrides,
      locale: const Locale('es'),
      user: testUser(roles: ['agent'], permissions: ['leads.view_assigned', 'appointments.manage_own']),
    );

    expect(find.text('Modo agente'), findsOneWidget);
    expect(find.byKey(const Key('home-leads')), findsOneWidget);
    expect(find.text('Tareas'), findsOneWidget);
  });
}
