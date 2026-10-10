import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/paginated.dart';
import '../domain/lead.dart';

/// Agent-mode CRM (the API scopes leads to the agent's own unless they may see all).
class CrmRepository {
  CrmRepository(this._dio);

  final Dio _dio;

  Future<Paginated<Lead>> leads({String? status, String? search, int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>(
      '/crm/leads',
      queryParameters: {'status': ?status, if (search != null && search.isNotEmpty) 'search': search, 'page': page},
    );
    return Paginated.fromJson(res.data!, (j) => Lead.fromJson(j));
  });

  Future<Lead> lead(int id) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/crm/leads/$id');
    return Lead.fromJson(res.data!['data'] as Map<String, dynamic>, meta: res.data!['meta'] as Map<String, dynamic>?);
  });

  /// Records a lead taken by phone, walk-in, referral… Returns its id.
  Future<int> createLead(Map<String, dynamic> input) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/crm/leads', data: input);
    return (res.data!['data'] as Map<String, dynamic>)['id'] as int;
  });

  Future<void> setStatus(int id, String status, {String? lostReason}) =>
      guardApi(() => _dio.post<void>('/crm/leads/$id/status', data: {'status': status, 'lost_reason': ?lostReason}));

  Future<void> logActivity(int id, String type, String? body) => guardApi(
    () => _dio.post<void>(
      '/crm/leads/$id/activities',
      data: {'type': type, if (body != null && body.isNotEmpty) 'body': body},
    ),
  );

  Future<Paginated<CrmTask>> tasks({String state = 'open'}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/crm/tasks', queryParameters: {'state': state});
    return Paginated.fromJson(res.data!, CrmTask.fromJson);
  });

  Future<void> setTaskCompleted(int id, bool completed) =>
      guardApi(() => _dio.patch<void>('/crm/tasks/$id', data: {'completed': completed}));
}

final crmRepositoryProvider = Provider<CrmRepository>((ref) => CrmRepository(ref.watch(dioProvider)));

final leadsProvider = FutureProvider.autoDispose.family<Paginated<Lead>, String?>(
  (ref, status) => ref.watch(crmRepositoryProvider).leads(status: status),
);

final leadProvider = FutureProvider.autoDispose.family<Lead, int>(
  (ref, id) => ref.watch(crmRepositoryProvider).lead(id),
);

final tasksProvider = FutureProvider.autoDispose.family<Paginated<CrmTask>, String>(
  (ref, state) => ref.watch(crmRepositoryProvider).tasks(state: state),
);
