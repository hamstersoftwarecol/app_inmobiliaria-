import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/features/properties/presentation/property_detail_screen.dart';
import 'package:hitlease_mobile/features/properties/presentation/search_screen.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  testWidgets('lists results and refines them with filters', (tester) async {
    final adapter = FakeHttpAdapter((o) {
      if (o.path == '/properties') {
        final q = o.uri.queryParameters;
        return (200, page(q['min_beds'] == '3' ? [] : [propertyJson(title: 'Hoboken colonial')]));
      }
      return (200, {'data': <String>[]});
    });
    await pumpScreen(
      tester,
      const SearchScreen(),
      adapter: adapter,
      user: testUser(permissions: ['favorites.manage', 'saved_searches.manage']),
    );

    expect(find.text('Hoboken colonial'), findsOneWidget);
    expect(find.text(r'$650,000'), findsOneWidget);
    expect(find.text('3 bd · 2.5 ba · 1,800 sq ft'), findsOneWidget);

    // An invalid price range is caught before calling the API.
    await tester.tap(find.byKey(const Key('open-filters')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('filter-min-price')), '500000');
    await tester.enterText(find.byKey(const Key('filter-max-price')), '400000');
    await tester.tap(find.byKey(const Key('filter-apply')));
    await tester.pumpAndSettle();
    expect(find.text('The maximum must be at least the minimum.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('filter-max-price')), '');
    await tester.enterText(find.byKey(const Key('filter-min-price')), '');
    await tester.tap(find.text('Any').at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('3+').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('filter-apply')));
    await tester.pumpAndSettle();

    expect(adapter.requests.last.uri.queryParameters, {'min_beds': '3'});
    expect(find.text('No homes match these filters yet.'), findsOneWidget);
  });

  testWidgets('detail shows MLS attribution and only the actions the user may take', (tester) async {
    final detail = {
      ...propertyJson(mls: true),
      'details': {
        'description': 'Bright home.',
        'year_built': 1925,
        'amenities': ['central_air'],
      },
      'photos': <Map<String, dynamic>>[],
      'listing_agent': {'full_name': 'Valeria Verified', 'slug': 'valeria', 'phone': null, 'email': null},
      'brokerage': {'name': 'Example Realty', 'license_number': '123'},
    };
    final adapter = FakeHttpAdapter(
      (o) => (200, o.path.startsWith('/properties/') ? {'data': detail} : {'data': <String>[]}),
    );

    await pumpScreen(
      tester,
      const PropertyDetailScreen(slug: 'e2e-12-example-lane-hoboken'),
      adapter: adapter,
      user: testUser(permissions: ['messages.use']),
    );

    expect(find.text('Listing courtesy of Example Realty'), findsOneWidget);
    expect(find.text('Source: Garden State MLS · MLS# GS123'), findsOneWidget);
    expect(find.text('Central air'), findsOneWidget);
    expect(find.byKey(const Key('message-agent')), findsOneWidget);
    // No `appointments.request` permission → no showing button.
    expect(find.byKey(const Key('request-showing')), findsNothing);

    await tester.ensureVisible(find.byKey(const Key('message-agent')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('message-agent')));
    await tester.pumpAndSettle();
    expect(find.textContaining('route:/conversation/new?agent=valeria'), findsOneWidget);
  });
}
