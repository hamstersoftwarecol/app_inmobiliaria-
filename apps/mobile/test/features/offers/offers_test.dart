import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hitlease_mobile/features/offers/presentation/offer_detail_screen.dart';
import 'package:hitlease_mobile/features/offers/presentation/offers_screen.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

Map<String, dynamic> offerJson({
  int id = 5,
  String status = 'submitted',
  Map<String, bool>? can,
  double amount = 640000,
}) => {
  'id': id,
  'parent_id': null,
  'side': 'buyer',
  'status': status,
  'property': {'id': 1, 'slug': 'e2e-56-offer-court-hoboken', 'title': 'Offer Court', 'list_price': 650000},
  'buyer_name': 'Bianca Buyer',
  'buyer_agent': null,
  'buyer_agent_external': null,
  'lead_id': null,
  'amount': amount,
  'earnest_money': 10000,
  'financing': 'conventional',
  'down_payment_percent': 20,
  'closing_date': '2027-01-15',
  'contingencies': ['inspection'],
  'terms': null,
  'expires_at': null,
  'submitted_at': '2026-10-10T12:00:00Z',
  'responded_at': null,
  'response_note': null,
  'can': can ?? {'edit': false, 'submit': false, 'withdraw': false, 'respond': true},
  'created_at': '2026-10-10T12:00:00Z',
};

void main() {
  const agent = ['offers.manage_own', 'offers.view_any'];

  testWidgets('lists offers and filters by status', (tester) async {
    final adapter = FakeHttpAdapter((o) => (200, page([offerJson()])));
    await pumpScreen(
      tester,
      const OffersScreen(),
      adapter: adapter,
      user: testUser(permissions: agent),
    );

    expect(find.text('Offer Court'), findsOneWidget);
    expect(find.textContaining(r'$640,000'), findsOneWidget);
    expect(find.byKey(const Key('new-offer')), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Accepted'));
    await tester.pumpAndSettle();
    expect(adapter.requests.last.queryParameters['status'], 'accepted');
  });

  testWidgets('accepting an offer opens its transaction', (tester) async {
    final adapter = FakeHttpAdapter((o) {
      if (o.method == 'POST' && o.path.endsWith('/accept')) {
        return (
          200,
          {
            'data': {'id': 77},
          },
        );
      }
      return (
        200,
        {
          'data': [offerJson()],
        },
      );
    });
    await pumpScreen(
      tester,
      const OfferDetailScreen(id: 5),
      adapter: adapter,
      user: testUser(permissions: agent),
    );

    expect(find.text('Offer Court'), findsOneWidget);
    expect(find.text('Inspection'), findsOneWidget);
    await scrollTo(tester, find.byKey(const Key('offer-accept')));
    await tester.tap(find.byKey(const Key('offer-accept')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-dialog')));
    await tester.pumpAndSettle();

    expect(adapter.requests.any((r) => r.method == 'POST' && r.path == '/offers/5/accept'), isTrue);
    expect(find.text('route:/home/transactions/77'), findsOneWidget);
  });

  testWidgets('a new offer is validated, then sent with an idempotency key', (tester) async {
    Object? sent;
    String? key;
    tester.view.physicalSize = const Size(800, 3000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final adapter = FakeHttpAdapter((o) {
      sent = o.data;
      key = o.headers['Idempotency-Key'] as String?;
      return (201, {'data': offerJson(id: 9, status: 'submitted')});
    });
    await pumpScreen(
      tester,
      const NewOfferScreen(propertySlug: 'e2e-56-offer-court-hoboken', propertyTitle: 'Offer Court'),
      adapter: adapter,
      user: testUser(permissions: agent),
    );

    await tester.tap(find.byKey(const Key('offer-submit-now')));
    await tester.pumpAndSettle();
    expect(adapter.requests, isEmpty);

    await tester.enterText(find.byKey(const Key('offer-buyer')), 'Bianca Buyer');
    await tester.enterText(find.byKey(const Key('offer-amount')), '640000');
    await tester.tap(find.byKey(const Key('offer-submit-now')));
    await tester.pumpAndSettle();

    final body = sent! as Map<String, dynamic>;
    expect(body['property_slug'], 'e2e-56-offer-court-hoboken');
    expect(body['amount'], 640000);
    expect(body['submit'], true);
    expect(body['contingencies'], ['attorney_review', 'inspection']);
    expect(key, isNotNull);
    expect(find.text('route:/home/offers/9'), findsOneWidget);
  });
}
