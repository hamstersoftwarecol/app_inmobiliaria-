import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/paginated.dart';
import '../../properties/domain/property.dart';

class FavoritesRepository {
  FavoritesRepository(this._dio);

  final Dio _dio;

  Future<Set<String>> slugs() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/favorites/slugs');
    final data = res.data!['data'];
    return data is List ? data.map((e) => e.toString()).toSet() : <String>{};
  });

  Future<Paginated<PropertySummary>> list({int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/favorites', queryParameters: {'page': page});
    return Paginated.fromJson(res.data!, PropertySummary.fromJson);
  });

  Future<void> add(String slug) => guardApi(() => _dio.put<void>('/me/favorites/${Uri.encodeComponent(slug)}'));

  Future<void> remove(String slug) => guardApi(() => _dio.delete<void>('/me/favorites/${Uri.encodeComponent(slug)}'));
}

final favoritesRepositoryProvider = Provider<FavoritesRepository>((ref) => FavoritesRepository(ref.watch(dioProvider)));
