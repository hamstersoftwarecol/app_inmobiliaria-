import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/network/paginated.dart';
import '../domain/offer.dart';

class OfferRepository {
  OfferRepository(this._dio);

  final Dio _dio;

  /// Latest offer of each negotiation the user can see.
  Future<Paginated<Offer>> offers({String? status, int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/offers', queryParameters: {'page': page, 'status': ?status});
    return Paginated.fromJson(res.data!, Offer.fromJson);
  });

  /// The whole negotiation (original offer and counters), oldest first.
  Future<List<Offer>> chain(int id) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/offers/$id');
    return asList(res.data!['data'], Offer.fromJson);
  });

  Future<Offer> create({
    required String propertySlug,
    required String buyerName,
    String? buyerAgentExternal,
    required OfferTerms terms,
    required bool submit,
    required String idempotencyKey,
  }) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>(
      '/offers',
      data: {
        ...terms.toJson(),
        'property_slug': propertySlug,
        'buyer_name': buyerName,
        'buyer_agent_external': buyerAgentExternal,
        'submit': submit,
      },
      options: Options(headers: {'Idempotency-Key': idempotencyKey}),
    );
    return Offer.fromJson(asMap(res.data!['data']));
  });

  Future<Offer> update(int id, {required String buyerName, String? buyerAgentExternal, required OfferTerms terms}) =>
      guardApi(() async {
        final res = await _dio.patch<Map<String, dynamic>>(
          '/offers/$id',
          data: {...terms.toJson(), 'buyer_name': buyerName, 'buyer_agent_external': buyerAgentExternal},
        );
        return Offer.fromJson(asMap(res.data!['data']));
      });

  Future<void> submit(int id) => guardApi(() => _dio.post<void>('/offers/$id/submit'));

  Future<void> counter(int id, OfferTerms terms, {String? note}) =>
      guardApi(() => _dio.post<void>('/offers/$id/counter', data: {...terms.toJson(), 'note': ?note}));

  /// Accepting opens a transaction; returns its id.
  Future<int> accept(int id) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/offers/$id/accept');
    return asInt(asMap(res.data!['data'])['id']) ?? 0;
  });

  Future<void> reject(int id, {String? note}) =>
      guardApi(() => _dio.post<void>('/offers/$id/reject', data: {'note': ?note}));

  Future<void> withdraw(int id, {String? note}) =>
      guardApi(() => _dio.post<void>('/offers/$id/withdraw', data: {'note': ?note}));
}

final offerRepositoryProvider = Provider<OfferRepository>((ref) => OfferRepository(ref.watch(dioProvider)));

final offersProvider = FutureProvider.autoDispose.family<Paginated<Offer>, String?>(
  (ref, status) => ref.watch(offerRepositoryProvider).offers(status: status),
);

final offerChainProvider = FutureProvider.autoDispose.family<List<Offer>, int>(
  (ref, id) => ref.watch(offerRepositoryProvider).chain(id),
);
