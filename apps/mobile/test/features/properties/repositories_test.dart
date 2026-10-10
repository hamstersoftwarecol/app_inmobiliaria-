import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:app_inmobiliaria_mobile/core/network/api_client.dart';
import 'package:app_inmobiliaria_mobile/core/storage/token_storage.dart';
import 'package:app_inmobiliaria_mobile/features/deals/data/deal_repository.dart';
import 'package:app_inmobiliaria_mobile/features/messages/data/message_repository.dart';
import 'package:app_inmobiliaria_mobile/features/properties/data/property_repository.dart';
import 'package:app_inmobiliaria_mobile/features/properties/domain/property.dart';
import 'package:app_inmobiliaria_mobile/features/showings/data/showing_repository.dart';

import '../../helpers/app.dart';
import '../../helpers/fake_http_adapter.dart';

void main() {
  test('the real client identifies itself as mobile (MLS mobile display rules)', () async {
    final container = ProviderContainer(overrides: [tokenStorageProvider.overrideWithValue(MemoryTokenStorage())]);
    addTearDown(container.dispose);
    final adapter = FakeHttpAdapter((_) => (200, page([propertyJson(mls: true)])));
    container.read(dioProvider).httpClientAdapter = adapter;

    final result = await container.read(propertyRepositoryProvider).search(const SearchFilters(city: 'Hoboken'));

    expect(adapter.requests.single.headers['X-App-Inmobiliaria-Client'], 'mobile');
    expect(adapter.requests.single.uri.queryParameters, {'city': 'Hoboken'});
    expect(result.items.single.compliance?.attribution, 'Listing courtesy of Example Realty');
    expect(result.items.single.bathrooms, 2.5);
  });

  test('showing requests carry an Idempotency-Key and explicit consent', () async {
    final adapter = FakeHttpAdapter(
      (_) => (
        201,
        {
          'data': {
            'id': 9,
            'status': 'requested',
            'starts_at': '2027-03-11T14:00:00+00:00',
            'ends_at': '2027-03-11T14:30:00+00:00',
            'can': {'cancel': true},
          },
        },
      ),
    );
    final repo = ShowingRepository(fakeDio(adapter));

    final a = await repo.request(slug: 'home', startsAt: DateTime.utc(2027, 3, 11, 14), idempotencyKey: 'm-key-12345');

    final req = adapter.requests.single;
    expect(req.headers['Idempotency-Key'], 'm-key-12345');
    expect(req.data, {'property_slug': 'home', 'starts_at': '2027-03-11T14:00:00.000Z', 'contact_consent': true});
    expect(a.canCancel, isTrue);
    expect(a.status, 'requested');
  });

  test('messages to a listing agent are addressed by public slugs', () async {
    final adapter = FakeHttpAdapter(
      (_) => (
        201,
        {
          'data': {
            'id': 4,
            'participants': [
              {'id': 8, 'full_name': 'Valeria Verified'},
            ],
            'messages': [
              {'id': 1, 'body': 'Hi', 'mine': true, 'created_at': '2026-10-10T12:00:00Z'},
            ],
          },
        },
      ),
    );
    final c = await MessageRepository(fakeDio(adapter)).start(body: 'Hi', agentSlug: 'valeria', propertySlug: 'home');

    expect(adapter.requests.single.data, {'body': 'Hi', 'agent_slug': 'valeria', 'property_slug': 'home'});
    expect(c.title, 'Valeria Verified');
    expect(c.messages.single.mine, isTrue);
  });

  test('document downloads use a short-lived signed link', () async {
    final adapter = FakeHttpAdapter(
      (o) => (
        200,
        {
          'data': {
            'url': 'https://api.example.test/api/v1/documents/download/3?expires=1&signature=abc',
            'expires_at': 'x',
          },
        },
      ),
    );
    final url = await DealRepository(fakeDio(adapter)).downloadLink(2, 3);

    expect(adapter.requests.single.path, '/documents/2/versions/3/link');
    expect(adapter.requests.single.method, 'POST');
    expect(url.queryParameters['signature'], 'abc');
  });
}
