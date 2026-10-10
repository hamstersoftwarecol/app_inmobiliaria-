import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/showing_repository.dart';
import '../domain/appointment.dart';

class ShowingsScreen extends StatelessWidget {
  const ShowingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.t('showings')),
          bottom: TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            tabs: [
              Tab(text: l10n.t('upcoming')),
              Tab(text: l10n.t('past')),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _List(when: 'upcoming'),
            _List(when: 'past'),
          ],
        ),
      ),
    );
  }
}

class _List extends ConsumerWidget {
  const _List({required this.when});

  final String when;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final list = ref.watch(showingsProvider(when));
    return RefreshIndicator(
      onRefresh: () => ref.refresh(showingsProvider(when).future),
      child: AsyncView(
        value: list,
        onRetry: () => ref.invalidate(showingsProvider(when)),
        data: (page) => page.items.isEmpty
            ? ListView(
                children: [
                  const SizedBox(height: 80),
                  EmptyState(message: l10n.t('noShowings'), icon: Icons.event_available),
                ],
              )
            : ListView(
                padding: const EdgeInsets.all(12),
                children: [for (final a in page.items) _ShowingCard(appointment: a)],
              ),
      ),
    );
  }
}

class _ShowingCard extends ConsumerWidget {
  const _ShowingCard({required this.appointment});

  final Appointment appointment;

  Future<String?> _askReason(BuildContext context, String title) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLength: 500,
          decoration: InputDecoration(labelText: ctx.l10n.t('reason')),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l10n.t('cancel'))),
          TextButton(
            onPressed: () => controller.text.trim().length >= 3 ? Navigator.pop(ctx, controller.text.trim()) : null,
            child: Text(ctx.l10n.t('confirm')),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final a = appointment;

    Future<void> act(String action, {String? reason}) async {
      try {
        await ref.read(showingRepositoryProvider).act(a.id, action, reason: reason);
        ref.invalidate(showingsProvider);
      } catch (e) {
        if (context.mounted) showError(context, e);
      }
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(a.propertyTitle ?? '—', style: Theme.of(context).textTheme.titleMedium)),
                Chip(label: Text(l10n.t('showing_${a.status}'))),
              ],
            ),
            Text(formatDateTime(a.startsAt, lang)),
            if ((a.propertyAddress ?? '').isNotEmpty)
              Text(a.propertyAddress!, style: Theme.of(context).textTheme.bodySmall),
            if (a.contactName != null) Text(l10n.t('visitor', {'name': a.contactName!})),
            if (a.agentName != null)
              Text(l10n.t('agentName', {'name': a.agentName!}), style: Theme.of(context).textTheme.bodySmall),
            if (a.statusReason != null) Text(a.statusReason!, style: Theme.of(context).textTheme.bodySmall),
            Wrap(
              spacing: 8,
              children: [
                if (a.canConfirm) FilledButton.tonal(onPressed: () => act('confirm'), child: Text(l10n.t('confirm'))),
                if (a.canDecline)
                  OutlinedButton(
                    onPressed: () async {
                      final reason = await _askReason(context, l10n.t('decline'));
                      if (reason != null) await act('decline', reason: reason);
                    },
                    child: Text(l10n.t('decline')),
                  ),
                if (a.canCancel)
                  TextButton(
                    onPressed: () async {
                      final reason = await _askReason(context, l10n.t('cancelShowing'));
                      if (reason != null) await act('cancel', reason: reason);
                    },
                    child: Text(l10n.t('cancelShowing')),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
