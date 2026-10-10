import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/paginated.dart';
import '../domain/property.dart';

/// Public listing search and detail. Only listings the API publishes are ever shown.
class PropertyRepository {
  PropertyRepository(this._dio);

  final Dio _dio;

  Future<Paginated<PropertySummary>> search(SearchFilters filters, {int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/properties', queryParameters: filters.toQuery(page: page));
    return Paginated.fromJson(res.data!, PropertySummary.fromJson);
  });

  Future<PropertyDetail> get(String slug) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/properties/${Uri.encodeComponent(slug)}');
    return PropertyDetail.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<List<LocationSuggestion>> locations(String q) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/locations', queryParameters: {'q': q});
    final data = res.data!['data'];
    return data is List ? [for (final e in data.whereType<Map<String, dynamic>>()) LocationSuggestion.fromJson(e)] : [];
  });
}

final propertyRepositoryProvider = Provider<PropertyRepository>((ref) => PropertyRepository(ref.watch(dioProvider)));
