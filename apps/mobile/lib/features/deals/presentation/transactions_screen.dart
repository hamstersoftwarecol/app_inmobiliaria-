import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/launch.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../data/deal_repository.dart';
import '../domain/transaction.dart';

class TransactionsScreen extends ConsumerWidget {
  const TransactionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final list = ref.watch(transactionsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('transactions'))),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(transactionsProvider.future),
        child: AsyncView(
          value: list,
          onRetry: () => ref.invalidate(transactionsProvider),
          data: (page) => page.items.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 80),
                    EmptyState(message: l10n.t('noTransactions'), icon: Icons.handshake_outlined),
                  ],
                )
              : ListView.separated(
                  itemCount: page.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) {
                    final tx = page.items[i];
                    final next = tx.nextMilestone;
                    return ListTile(
                      title: Text(tx.title),
                      subtitle: Text(
                        [
                          formatPrice(tx.salePrice, lang),
                          if (tx.status == 'open' && next != null)
                            '${next.title}: ${formatCalendarDate(next.dueDate, lang)}',
                        ].join(' · '),
                      ),
                      trailing: Chip(label: Text(l10n.t('tx_${tx.status}'))),
                      onTap: () => context.push(Routes.transaction(tx.id)),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

class TransactionDetailScreen extends ConsumerWidget {
  const TransactionDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final tx = ref.watch(transactionProvider(id));
    final user = ref.watch(authControllerProvider).value;
    final today = DateTime.now().toIso8601String().substring(0, 10);

    return Scaffold(
      appBar: AppBar(title: Text(tx.value?.title ?? l10n.t('transactions'))),
      body: AsyncView(
        value: tx,
        onRetry: () => ref.invalidate(transactionProvider(id)),
        data: (t) => RefreshIndicator(
          onRefresh: () {
            ref.invalidate(documentsProvider(id));
            return ref.refresh(transactionProvider(id).future);
          },
          child: ListView(
            padding: const EdgeInsets.all(12),
            children: [
              Card(
                child: Column(
                  children: [
                    ListTile(
                      title: Text(formatPrice(t.salePrice, lang), style: Theme.of(context).textTheme.titleLarge),
                      subtitle: Text(t.propertyTitle ?? ''),
                      trailing: Chip(label: Text(l10n.t('tx_${t.status}'))),
                    ),
                    ListTile(
                      dense: true,
                      title: Text(l10n.t('closingDate')),
                      trailing: Text(formatCalendarDate(t.closingDate, lang)),
                    ),
                    if (t.listingAgent != null)
                      ListTile(dense: true, title: Text(l10n.t('listingAgent')), trailing: Text(t.listingAgent!)),
                    if (t.buyerAgent != null)
                      ListTile(dense: true, title: Text(l10n.t('buyerAgent')), trailing: Text(t.buyerAgent!)),
                  ],
                ),
              ),
              if (user != null && user.can('messages.use'))
                OutlinedButton.icon(
                  icon: const Icon(Icons.forum_outlined),
                  label: Text(l10n.t('messageDeal')),
                  onPressed: () => context.push(Routes.newConversation(transactionId: t.id)),
                ),
              const SizedBox(height: 12),
              Text(l10n.t('milestones'), style: Theme.of(context).textTheme.titleMedium),
              for (final m in t.milestones)
                CheckboxListTile(
                  value: m.completedAt != null,
                  title: Text(m.title),
                  subtitle: Text(
                    m.dueDate == null
                        ? l10n.t('noDate')
                        : l10n.t('dueOn', {'date': formatCalendarDate(m.dueDate, lang)}),
                    style: m.completedAt == null && m.dueDate != null && m.dueDate!.compareTo(today) < 0
                        ? TextStyle(color: Theme.of(context).colorScheme.error)
                        : null,
                  ),
                  onChanged: t.canManage && t.status == 'open'
                      ? (v) async {
                          try {
                            await ref.read(dealRepositoryProvider).setMilestoneCompleted(t.id, m.id, v ?? false);
                          } catch (e) {
                            if (context.mounted) showError(context, e);
                          }
                          ref.invalidate(transactionProvider(id));
                        }
                      : null,
                ),
              Text(l10n.t('milestoneDisclaimer'), style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: 12),
              Text(l10n.t('parties'), style: Theme.of(context).textTheme.titleMedium),
              for (final p in t.participants)
                ListTile(dense: true, leading: Text(l10n.t('role_${p.role}')), title: Text(p.name)),
              const SizedBox(height: 12),
              Text(l10n.t('documents'), style: Theme.of(context).textTheme.titleMedium),
              _Documents(transactionId: t.id),
            ],
          ),
        ),
      ),
    );
  }
}

class _Documents extends ConsumerWidget {
  const _Documents({required this.transactionId});

  final int transactionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final docs = ref.watch(documentsProvider(transactionId));
    return AsyncView<List<DealDocument>>(
      value: docs,
      onRetry: () => ref.invalidate(documentsProvider(transactionId)),
      data: (list) => list.isEmpty
          ? Padding(padding: const EdgeInsets.all(8), child: Text(l10n.t('noDocuments')))
          : Column(
              children: [
                for (final d in list)
                  ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text(d.title),
                    subtitle: Text(
                      [
                        l10n.t('doc_${d.category}'),
                        ?d.fileName,
                        if (d.scanStatus != null) l10n.t('scan_${d.scanStatus}'),
                      ].join(' · '),
                    ),
                    trailing: IconButton(
                      tooltip: l10n.t('download'),
                      icon: const Icon(Icons.download),
                      onPressed: d.latestVersionId == null
                          ? null
                          : () async {
                              try {
                                final url = await ref
                                    .read(dealRepositoryProvider)
                                    .downloadLink(d.id, d.latestVersionId!);
                                if (context.mounted) await openExternal(context, url);
                              } catch (e) {
                                if (context.mounted) showError(context, e);
                              }
                            },
                    ),
                  ),
                Text(l10n.t('uploadOnWeb'), style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
    );
  }
}
