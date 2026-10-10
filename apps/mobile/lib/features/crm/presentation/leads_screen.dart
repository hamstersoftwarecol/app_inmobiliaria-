import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../data/crm_repository.dart';
import '../domain/lead.dart';

class LeadsScreen extends ConsumerStatefulWidget {
  const LeadsScreen({super.key});

  @override
  ConsumerState<LeadsScreen> createState() => _LeadsScreenState();
}

class _LeadsScreenState extends ConsumerState<LeadsScreen> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final leads = ref.watch(leadsProvider(_status));

    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('leads'))),
      floatingActionButton: user != null && user.can('leads.create')
          ? FloatingActionButton.extended(
              key: const Key('new-lead'),
              icon: const Icon(Icons.person_add_alt),
              label: Text(l10n.t('newLead')),
              onPressed: () => context.push(Routes.newLead),
            )
          : null,
      body: Column(
        children: [
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              children: [
                for (final s in [null, ...Lead.statuses])
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Text(s == null ? l10n.t('all') : l10n.t('lead_$s')),
                      selected: _status == s,
                      onSelected: (_) => setState(() => _status = s),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(leadsProvider(_status).future),
              child: AsyncView(
                value: leads,
                onRetry: () => ref.invalidate(leadsProvider(_status)),
                data: (page) => page.items.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          EmptyState(message: l10n.t('noLeads'), icon: Icons.people_outline),
                        ],
                      )
                    : ListView.separated(
                        itemCount: page.items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final lead = page.items[i];
                          return ListTile(
                            title: Text(lead.fullName),
                            subtitle: Text(
                              [
                                l10n.t('source_${lead.source}'),
                                ?lead.propertyTitle,
                                formatDate(lead.lastActivityAt ?? lead.createdAt, lang),
                              ].join(' · '),
                            ),
                            trailing: Chip(label: Text(l10n.t('lead_${lead.status}'))),
                            onTap: () => context.push(Routes.lead(lead.id)),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
