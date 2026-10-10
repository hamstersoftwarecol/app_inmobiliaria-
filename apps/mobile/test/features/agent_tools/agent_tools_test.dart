import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/features/availability/data/availability_repository.dart';
import 'package:hitlease_mobile/features/availability/presentation/availability_screen.dart';
import 'package:hitlease_mobile/features/crm/presentation/new_lead_screen.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  test('detects overlapping availability windows', () {
    const windows = [
      AvailabilityWindow(weekday: 1, startTime: '09:00', endTime: '12:00'),
      AvailabilityWindow(weekday: 1, startTime: '11:00', endTime: '14:00'),
      AvailabilityWindow(weekday: 2, startTime: '11:00', endTime: '14:00'),
    ];
    expect(overlappingWindows(windows), {0, 1});
  });

  testWidgets('availability adds a window and saves the week', (tester) async {
    Object? sent;
    final data = {
      'timezone': 'America/New_York',
      'windows': [
        {'weekday': 1, 'start_time': '09:00', 'end_time': '17:00'},
      ],
      'time_off': [],
    };
    final adapter = FakeHttpAdapter((o) {
      if (o.method == 'PUT') sent = o.data;
      return (200, {'data': data});
    });
    await pumpScreen(
      tester,
      const AvailabilityScreen(),
      adapter: adapter,
      user: testUser(roles: ['agent'], permissions: ['appointments.manage_own']),
    );

    expect(find.textContaining('America/New_York'), findsOneWidget);
    await tester.tap(find.byTooltip('Add hours').at(1)); // Tuesday
    await tester.pumpAndSettle();
    await scrollTo(tester, find.byKey(const Key('availability-save')));
    await tester.tap(find.byKey(const Key('availability-save')));
    await tester.pumpAndSettle();

    expect(sent, {
      'windows': [
        {'weekday': 1, 'start_time': '09:00', 'end_time': '17:00'},
        {'weekday': 2, 'start_time': '09:00', 'end_time': '17:00'},
      ],
    });
  });

  testWidgets('new lead posts a manual lead and opens it', (tester) async {
    Object? sent;
    final adapter = FakeHttpAdapter((o) {
      sent = o.data;
      return (
        201,
        {
          'data': {'id': 31},
        },
      );
    });
    await pumpScreen(
      tester,
      const NewLeadScreen(),
      adapter: adapter,
      user: testUser(roles: ['agent'], permissions: ['leads.create']),
    );

    await tester.enterText(find.byKey(const Key('lead-first')), 'Carla');
    await tester.enterText(find.byKey(const Key('lead-email')), 'carla@example.com');
    await scrollTo(tester, find.byKey(const Key('lead-save')));
    await tester.tap(find.byKey(const Key('lead-save')));
    await tester.pumpAndSettle();

    final body = sent! as Map<String, dynamic>;
    expect(body['first_name'], 'Carla');
    expect(body['source'], 'phone');
    expect(body['email'], 'carla@example.com');
    expect(find.text('route:/home/leads/31'), findsOneWidget);
  });
}
