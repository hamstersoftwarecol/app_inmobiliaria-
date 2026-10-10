import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/router/app_router.dart';
import '../data/message_repository.dart';
import 'conversation_screen.dart';

/// Starts a conversation with a listing's agent, everyone on a deal, or a chosen contact.
class NewConversationScreen extends ConsumerStatefulWidget {
  const NewConversationScreen({
    super.key,
    this.agentSlug,
    this.agentName,
    this.propertySlug,
    this.propertyTitle,
    this.transactionId,
  });

  final String? agentSlug;
  final String? agentName;
  final String? propertySlug;
  final String? propertyTitle;
  final int? transactionId;

  @override
  ConsumerState<NewConversationScreen> createState() => _NewConversationScreenState();
}

class _NewConversationScreenState extends ConsumerState<NewConversationScreen> {
  ({int id, String fullName})? _recipient;
  List<({int id, String fullName})> _contacts = const [];
  Timer? _debounce;
  String? _error;

  bool get _fixed => widget.agentSlug != null || widget.transactionId != null;

  @override
  void initState() {
    super.initState();
    if (!_fixed) _search(null);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _search(String? q) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 250), () async {
      try {
        final found = await ref
            .read(messageRepositoryProvider)
            .contacts(q == null || q.trim().isEmpty ? null : q.trim());
        if (mounted) setState(() => _contacts = found);
      } catch (_) {
        // Leave the previous list.
      }
    });
  }

  Future<Object?> _send(String body) async {
    if (!_fixed && _recipient == null) {
      setState(() => _error = context.l10n.t('pickRecipient'));
      throw StateError('no recipient');
    }
    final c = await ref
        .read(messageRepositoryProvider)
        .start(
          body: body,
          agentSlug: widget.agentSlug,
          propertySlug: widget.propertySlug,
          transactionId: widget.transactionId,
          recipientIds: _fixed ? null : [_recipient!.id],
        );
    ref.invalidate(conversationsProvider);
    if (mounted) context.pushReplacement(Routes.conversation(c.id));
    return c;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final to = widget.transactionId != null
        ? l10n.t('dealNumber', {'id': '${widget.transactionId}'})
        : widget.agentName ?? widget.agentSlug;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('newMessage'))),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Text(l10n.t('privateHint'), style: Theme.of(context).textTheme.bodySmall),
          ),
          if (_fixed)
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text('${l10n.t('to')}: $to'),
              subtitle: widget.propertyTitle == null ? null : Text(l10n.t('about', {'title': widget.propertyTitle!})),
            )
          else if (_recipient != null)
            ListTile(
              leading: const Icon(Icons.person),
              title: Text('${l10n.t('to')}: ${_recipient!.fullName}'),
              trailing: IconButton(
                tooltip: l10n.t('remove'),
                icon: const Icon(Icons.close),
                onPressed: () => setState(() => _recipient = null),
              ),
            )
          else ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: TextField(
                key: const Key('contact-search'),
                decoration: InputDecoration(
                  labelText: l10n.t('searchPeople'),
                  prefixIcon: const Icon(Icons.search),
                  errorText: _error,
                ),
                onChanged: _search,
              ),
            ),
          ],
          Expanded(
            child: _fixed || _recipient != null
                ? const SizedBox.shrink()
                : ListView(
                    children: [
                      for (final c in _contacts)
                        ListTile(
                          title: Text(c.fullName),
                          onTap: () => setState(() {
                            _recipient = c;
                            _error = null;
                          }),
                        ),
                    ],
                  ),
          ),
          SafeArea(top: false, child: MessageComposer(onSend: _send)),
        ],
      ),
    );
  }
}
