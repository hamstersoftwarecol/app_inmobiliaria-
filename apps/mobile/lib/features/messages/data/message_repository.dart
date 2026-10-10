import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/network/paginated.dart';
import '../../auth/application/auth_controller.dart';
import '../domain/conversation.dart';

/// Private conversations: the API only ever returns the user's own.
class MessageRepository {
  MessageRepository(this._dio);

  final Dio _dio;

  Future<Paginated<Conversation>> list({int page = 1}) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/conversations', queryParameters: {'page': page});
    return Paginated.fromJson(res.data!, Conversation.fromJson);
  });

  Future<Conversation> get(int id) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/conversations/$id');
    return Conversation.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<int> unreadCount() => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/conversations/unread-count');
    return asInt(asMap(res.data!['data'])['count']) ?? 0;
  });

  Future<Conversation> start({
    required String body,
    String? agentSlug,
    String? propertySlug,
    int? transactionId,
    List<int>? recipientIds,
  }) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>(
      '/conversations',
      data: {
        'body': body,
        'agent_slug': ?agentSlug,
        'property_slug': ?propertySlug,
        'transaction_id': ?transactionId,
        'recipient_ids': ?recipientIds,
      },
    );
    return Conversation.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<ChatMessage> send(int id, String body) => guardApi(() async {
    final res = await _dio.post<Map<String, dynamic>>('/conversations/$id/messages', data: {'body': body});
    return ChatMessage.fromJson(res.data!['data'] as Map<String, dynamic>);
  });

  Future<List<({int id, String fullName})>> contacts(String? q) => guardApi(() async {
    final res = await _dio.get<Map<String, dynamic>>('/conversations/contacts', queryParameters: {'q': ?q});
    return asList(res.data!['data'], (c) => (id: asInt(c['id']) ?? 0, fullName: asString(c['full_name']) ?? ''));
  });
}

final messageRepositoryProvider = Provider<MessageRepository>((ref) => MessageRepository(ref.watch(dioProvider)));

final conversationsProvider = FutureProvider.autoDispose<Paginated<Conversation>>(
  (ref) => ref.watch(messageRepositoryProvider).list(),
);

final conversationProvider = FutureProvider.autoDispose.family<Conversation, int>(
  (ref, id) => ref.watch(messageRepositoryProvider).get(id),
);

final unreadMessagesProvider = StreamProvider.autoDispose<int>((ref) async* {
  final user = ref.watch(authControllerProvider).value;
  if (user == null || !user.can('messages.use')) {
    yield 0;
    return;
  }
  final repo = ref.watch(messageRepositoryProvider);
  while (true) {
    try {
      yield await repo.unreadCount();
    } catch (_) {
      // Keep the last value on transient errors.
    }
    await Future<void>.delayed(const Duration(minutes: 1));
  }
});
