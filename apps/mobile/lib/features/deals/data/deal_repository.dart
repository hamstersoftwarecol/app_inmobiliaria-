import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/network/paginated.dart';
import '../domain/transaction.dart';

class DealRepository {
  DealRepository(this._dio);

  final Dio _dio;

  Future<Paginated<DealTransaction>> transactions({int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/transactions', queryParameters: {'page': page});
    return Paginated.fromJson(res.data!, DealTransaction.fromJson);
  });

  Future<DealTransaction> transaction(int id) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/transactions/$id');
    return DealTransaction.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<void> setMilestoneCompleted(int transactionId, int milestoneId, bool completed) => guardApi(
    () => _dio.patch<void>('/transactions/$transactionId/milestones/$milestoneId', data: {'completed': completed}),
  );

  Future<List<DealDocument>> documents(int transactionId) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/transactions/$transactionId/documents');
    return asList(res.data!['data'], DealDocument.fromJson);
  });

  /// Short-lived signed download URL (opened outside the app right away).
  Future<Uri> downloadLink(int documentId, int versionId) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/documents/$documentId/versions/$versionId/link');
    return Uri.parse(asString(asMap(res.data!['data'])['url']) ?? '');
  });
}

final dealRepositoryProvider = Provider<DealRepository>((ref) => DealRepository(ref.watch(dioProvider)));

final transactionsProvider = FutureProvider.autoDispose<Paginated<DealTransaction>>(
  (ref) => ref.watch(dealRepositoryProvider).transactions(),
);

final transactionProvider = FutureProvider.autoDispose.family<DealTransaction, int>(
  (ref, id) => ref.watch(dealRepositoryProvider).transaction(id),
);

final documentsProvider = FutureProvider.autoDispose.family<List<DealDocument>, int>(
  (ref, id) => ref.watch(dealRepositoryProvider).documents(id),
);
