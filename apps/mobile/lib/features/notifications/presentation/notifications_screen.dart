import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/notification_repository.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final list = ref.watch(notificationsProvider);
    final repo = ref.read(notificationRepositoryProvider);

    void refresh() {
      ref.invalidate(notificationsProvider);
      ref.invalidate(unreadNotificationsProvider);
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('notifications')),
        actions: [
          IconButton(
            tooltip: l10n.t('markAllRead'),
            icon: const Icon(Icons.done_all),
            onPressed: () async {
              try {
                await repo.markAllRead();
                refresh();
              } catch (e) {
                if (context.mounted) showError(context, e);
              }
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(notificationsProvider.future),
        child: AsyncView(
          value: list,
          onRetry: refresh,
          data: (page) => page.items.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 80),
                    EmptyState(message: l10n.t('allCaughtUp'), icon: Icons.notifications_none),
                  ],
                )
              : ListView.separated(
                  itemCount: page.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) {
                    final n = page.items[i];
                    return ListTile(
                      leading: Icon(
                        n.unread ? Icons.circle : Icons.circle_outlined,
                        size: 12,
                        color: n.unread ? Theme.of(context).colorScheme.primary : null,
                      ),
                      title: Text(n.text(l10n), style: n.unread ? const TextStyle(fontWeight: FontWeight.w600) : null),
                      subtitle: Text(formatDateTime(n.createdAt, lang)),
                      onTap: () async {
                        try {
                          if (n.unread) await repo.markRead(n.id);
                          refresh();
                        } catch (_) {
                          // Opening still works if marking as read fails.
                        }
                        final route = n.route;
                        if (route != null && context.mounted) await context.push(route);
                      },
                    );
                  },
                ),
        ),
      ),
    );
  }
}
