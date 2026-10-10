import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/paginated.dart';
import '../domain/appointment.dart';

class ShowingRepository {
  ShowingRepository(this._dio);

  final Dio _dio;

  Future<ShowingSlots> slots(String slug, {int days = 7}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>(
      '/properties/${Uri.encodeComponent(slug)}/showing-slots',
      queryParameters: {'days': days},
    );
    return ShowingSlots.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  /// The same [idempotencyKey] must be reused when retrying, so a flaky network cannot book twice.
  Future<Appointment> request({
    required String slug,
    required DateTime startsAt,
    required String idempotencyKey,
    String? phone,
    String? notes,
  }) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>(
      '/appointments',
      data: {
        'property_slug': slug,
        'starts_at': startsAt.toUtc().toIso8601String(),
        'phone': ?phone,
        'notes': ?notes,
        'contact_consent': true,
      },
      options: Options(headers: {'Idempotency-Key': idempotencyKey}),
    );
    return Appointment.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<Paginated<Appointment>> list(String when, {int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/appointments', queryParameters: {'when': when, 'page': page});
    return Paginated.fromJson(res.data!, Appointment.fromJson);
  });

  Future<Appointment> act(int id, String action, {String? reason}) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/appointments/$id/$action', data: {'reason': ?reason});
    return Appointment.fromJson(res.data!['data'] as Map<String, dynamic>);
  });
}

final showingRepositoryProvider = Provider<ShowingRepository>((ref) => ShowingRepository(ref.watch(dioProvider)));

final showingSlotsProvider = FutureProvider.autoDispose.family<ShowingSlots, String>(
  (ref, slug) => ref.watch(showingRepositoryProvider).slots(slug),
);

final showingsProvider = FutureProvider.autoDispose.family<Paginated<Appointment>, String>(
  (ref, when) => ref.watch(showingRepositoryProvider).list(when),
);
