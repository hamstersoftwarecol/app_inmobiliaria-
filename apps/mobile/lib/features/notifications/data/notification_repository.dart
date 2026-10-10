import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/network/paginated.dart';
import '../../auth/application/auth_controller.dart';
import '../domain/app_notification.dart';

class NotificationRepository {
  NotificationRepository(this._dio);

  final Dio _dio;

  Future<Paginated<AppNotification>> list({int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/notifications', queryParameters: {'page': page});
    return Paginated.fromJson(res.data!, AppNotification.fromJson);
  });

  Future<int> unreadCount() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/me/notifications/unread-count');
    return asInt(asMap(res.data!['data'])['count']) ?? 0;
  });

  Future<void> markRead(String id) => guardApi(() => _dio.post<void>('/me/notifications/$id/read'));

  Future<void> markAllRead() => guardApi(() => _dio.post<void>('/me/notifications/read-all'));
}

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.watch(dioProvider)),
);

final notificationsProvider = FutureProvider.autoDispose<Paginated<AppNotification>>(
  (ref) => ref.watch(notificationRepositoryProvider).list(),
);

/// Polled badge count (push via FCM is blocked until a Firebase project exists).
final unreadNotificationsProvider = StreamProvider.autoDispose<int>((ref) async* {
  if (ref.watch(authControllerProvider).value == null) {
    yield 0;
    return;
  }
  final repo = ref.watch(notificationRepositoryProvider);
  while (true) {
    try {
      yield await repo.unreadCount();
    } catch (_) {
      // Keep the last value on transient errors.
    }
    await Future<void>.delayed(const Duration(minutes: 1));
  }
});
