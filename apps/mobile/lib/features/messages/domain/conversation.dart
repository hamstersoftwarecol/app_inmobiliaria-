import '../../../core/network/json.dart';

class ChatMessage {
  const ChatMessage({required this.id, required this.body, required this.mine, this.authorName, this.createdAt});

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
    id: asInt(j['id']) ?? 0,
    body: asString(j['body']) ?? '',
    mine: j['mine'] == true,
    authorName: asString(asMap(j['author'])['full_name']),
    createdAt: asDate(j['created_at']),
  );

  final int id;
  final String body;
  final bool mine;
  final String? authorName;
  final DateTime? createdAt;
}

class Conversation {
  const Conversation({
    required this.id,
    required this.participants,
    this.subject,
    this.propertySlug,
    this.propertyTitle,
    this.transactionId,
    this.lastMessage,
    this.lastMessageMine = false,
    this.lastMessageAt,
    this.unread = 0,
    this.messages = const [],
  });

  factory Conversation.fromJson(Map<String, dynamic> j) {
    final property = asMapOrNull(j['property']);
    final last = asMapOrNull(j['last_message']);
    return Conversation(
      id: asInt(j['id']) ?? 0,
      subject: asString(j['subject']),
      participants: asList(
        j['participants'],
        (p) => asString(p['full_name']) ?? '',
      ).where((n) => n.isNotEmpty).toList(),
      propertySlug: asString(property?['slug']),
      propertyTitle: asString(property?['title']),
      transactionId: asInt(j['transaction_id']),
      lastMessage: asString(last?['body']),
      lastMessageMine: last?['mine'] == true,
      lastMessageAt: asDate(j['last_message_at']),
      unread: asInt(j['unread']) ?? 0,
      messages: asList(j['messages'], ChatMessage.fromJson),
    );
  }

  final int id;
  final String? subject;
  final List<String> participants;
  final String? propertySlug;
  final String? propertyTitle;
  final int? transactionId;
  final String? lastMessage;
  final bool lastMessageMine;
  final DateTime? lastMessageAt;
  final int unread;
  final List<ChatMessage> messages;

  String get title => participants.isEmpty ? '—' : participants.join(', ');

  Conversation withMessage(ChatMessage m) => Conversation(
    id: id,
    participants: participants,
    subject: subject,
    propertySlug: propertySlug,
    propertyTitle: propertyTitle,
    transactionId: transactionId,
    lastMessage: m.body,
    lastMessageMine: true,
    lastMessageAt: m.createdAt,
    messages: [...messages, m],
  );
}
