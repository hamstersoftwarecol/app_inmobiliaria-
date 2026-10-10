import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/message_repository.dart';
import '../domain/conversation.dart';

/// A conversation thread; refreshed every 15 s while open (real-time delivery is planned).
class ConversationScreen extends ConsumerStatefulWidget {
  const ConversationScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends ConsumerState<ConversationScreen> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => ref.invalidate(conversationProvider(widget.id)));
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final conversation = ref.watch(conversationProvider(widget.id));
    return Scaffold(
      appBar: AppBar(title: Text(conversation.value?.title ?? l10n.t('messages'))),
      body: AsyncView(
        value: conversation,
        onRetry: () => ref.invalidate(conversationProvider(widget.id)),
        data: (c) => Column(
          children: [
            if (c.propertyTitle != null)
              ListTile(
                dense: true,
                leading: const Icon(Icons.home_outlined),
                title: Text(l10n.t('about', {'title': c.propertyTitle!})),
              ),
            Expanded(child: _Thread(messages: c.messages)),
            SafeArea(
              top: false,
              child: MessageComposer(
                onSend: (body) async {
                  final message = await ref.read(messageRepositoryProvider).send(c.id, body);
                  ref.invalidate(conversationProvider(widget.id));
                  return message;
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Thread extends ConsumerWidget {
  const _Thread({required this.messages});

  final List<ChatMessage> messages;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lang = ref.watch(localeControllerProvider).languageCode;
    final scheme = Theme.of(context).colorScheme;
    return ListView.builder(
      reverse: true,
      padding: const EdgeInsets.all(12),
      itemCount: messages.length,
      itemBuilder: (context, i) {
        final m = messages[messages.length - 1 - i];
        return Align(
          alignment: m.mine ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.8),
            margin: const EdgeInsets.symmetric(vertical: 4),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: m.mine ? scheme.primary : scheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${m.mine ? context.l10n.t('you') : (m.authorName ?? '')} · ${formatDateTime(m.createdAt, lang)}',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(color: m.mine ? Colors.white70 : null),
                ),
                const SizedBox(height: 2),
                SelectableText(m.body, style: TextStyle(color: m.mine ? Colors.white : null)),
              ],
            ),
          ),
        );
      },
    );
  }
}

class MessageComposer extends StatefulWidget {
  const MessageComposer({super.key, required this.onSend});

  final Future<Object?> Function(String body) onSend;

  @override
  State<MessageComposer> createState() => _MessageComposerState();
}

class _MessageComposerState extends State<MessageComposer> {
  final _body = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _body.text.trim();
    if (text.isEmpty) return;
    setState(() => _busy = true);
    try {
      await widget.onSend(text);
      _body.clear();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 4, 4, 8),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              key: const Key('message-body'),
              controller: _body,
              minLines: 1,
              maxLines: 5,
              maxLength: 5000,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(labelText: l10n.t('message'), counterText: '', isDense: true),
            ),
          ),
          IconButton(
            key: const Key('message-send'),
            tooltip: l10n.t('send'),
            icon: _busy
                ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.send),
            onPressed: _busy ? null : _send,
          ),
        ],
      ),
    );
  }
}
