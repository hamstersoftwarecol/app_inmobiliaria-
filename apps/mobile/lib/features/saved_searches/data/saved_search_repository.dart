import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../properties/domain/property.dart';
import '../domain/saved_search.dart';

class SavedSearchRepository {
  SavedSearchRepository(this._dio);

  final Dio _dio;

  Future<List<SavedSearch>> list() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/saved-searches');
    final data = res.data!['data'];
    return data is List ? [for (final e in data.whereType<Map<String, dynamic>>()) SavedSearch.fromJson(e)] : [];
  });

  Future<SavedSearch> create(String name, SearchFilters filters, String alertFrequency) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>(
      '/me/saved-searches',
      data: {
        'name': name.trim(),
        'filters': filters.filterMap(),
        'sort': filters.sort,
        'alert_frequency': alertFrequency,
      },
    );
    return SavedSearch.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<SavedSearch> setFrequency(int id, String alertFrequency) => guardApi(() async {
    final res = await _dio.patch<Map<String, dynamic>>(
      '/me/saved-searches/$id',
      data: {'alert_frequency': alertFrequency},
    );
    return SavedSearch.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<void> remove(int id) => guardApi(() => _dio.delete<void>('/me/saved-searches/$id'));
}

final savedSearchRepositoryProvider = Provider<SavedSearchRepository>(
  (ref) => SavedSearchRepository(ref.watch(dioProvider)),
);

final savedSearchesProvider = FutureProvider.autoDispose<List<SavedSearch>>(
  (ref) => ref.watch(savedSearchRepositoryProvider).list(),
);
