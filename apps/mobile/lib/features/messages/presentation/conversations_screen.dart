import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../data/message_repository.dart';

class ConversationsScreen extends ConsumerWidget {
  const ConversationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final user = ref.watch(authControllerProvider).value;
    if (user != null && !user.can('messages.use')) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.t('messages'))),
        body: EmptyState(message: l10n.t('forbidden')),
      );
    }
    final list = ref.watch(conversationsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('messages'))),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('new-message'),
        onPressed: () => context.push(Routes.newConversation()),
        icon: const Icon(Icons.edit_outlined),
        label: Text(l10n.t('newMessage')),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(conversationsProvider.future),
        child: AsyncView(
          value: list,
          onRetry: () => ref.invalidate(conversationsProvider),
          data: (page) => page.items.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 80),
                    EmptyState(message: l10n.t('noConversations'), icon: Icons.chat_bubble_outline),
                  ],
                )
              : ListView.separated(
                  itemCount: page.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) {
                    final c = page.items[i];
                    return ListTile(
                      leading: CircleAvatar(child: Text(c.title.isNotEmpty ? c.title[0] : '?')),
                      title: Text(c.title, style: c.unread > 0 ? const TextStyle(fontWeight: FontWeight.bold) : null),
                      subtitle: Text(
                        [
                          if (c.propertyTitle != null) l10n.t('about', {'title': c.propertyTitle!}),
                          if (c.lastMessage != null) '${c.lastMessageMine ? '${l10n.t('you')}: ' : ''}${c.lastMessage}',
                        ].join('\n'),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(formatDate(c.lastMessageAt, lang), style: Theme.of(context).textTheme.labelSmall),
                          if (c.unread > 0) Badge(label: Text('${c.unread}')),
                        ],
                      ),
                      onTap: () async {
                        await context.push(Routes.conversation(c.id));
                        ref.invalidate(conversationsProvider);
                        ref.invalidate(unreadMessagesProvider);
                      },
                    );
                  },
                ),
        ),
      ),
    );
  }
}
