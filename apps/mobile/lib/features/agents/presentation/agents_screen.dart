import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/launch.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../data/agent_repository.dart';
import '../domain/agent.dart';

/// Public directory of the brokerage's agents (verified licences only, enforced by the API).
class AgentsScreen extends ConsumerStatefulWidget {
  const AgentsScreen({super.key});

  @override
  ConsumerState<AgentsScreen> createState() => _AgentsScreenState();
}

class _AgentsScreenState extends ConsumerState<AgentsScreen> {
  final _search = TextEditingController();
  String? _language;
  String? _specialty;
  AgentDirectoryFilters _filters = const AgentDirectoryFilters();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _apply() {
    final text = _search.text.trim();
    setState(
      () => _filters = AgentDirectoryFilters(
        search: text.isEmpty ? null : text,
        language: _language,
        specialty: _specialty,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final list = ref.watch(agentDirectoryProvider(_filters));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('ourAgents'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: TextField(
              key: const Key('agents-search'),
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                labelText: l10n.t('searchAgents'),
                border: const OutlineInputBorder(),
              ),
              onSubmitted: (_) => _apply(),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
            child: Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    initialValue: _language,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: l10n.t('language'), isDense: true),
                    items: [
                      DropdownMenuItem(child: Text(l10n.t('any'))),
                      for (final code in agentLanguages)
                        DropdownMenuItem(value: code, child: Text(l10n.t('lang_$code'))),
                    ],
                    onChanged: (v) {
                      _language = v;
                      _apply();
                    },
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    initialValue: _specialty,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: l10n.t('specialty'), isDense: true),
                    items: [
                      DropdownMenuItem(child: Text(l10n.t('any'))),
                      for (final s in agentSpecialties) DropdownMenuItem(value: s, child: Text(l10n.t('spec_$s'))),
                    ],
                    onChanged: (v) {
                      _specialty = v;
                      _apply();
                    },
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(agentDirectoryProvider(_filters).future),
              child: AsyncView(
                value: list,
                onRetry: () => ref.invalidate(agentDirectoryProvider(_filters)),
                data: (page) => page.items.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          EmptyState(message: l10n.t('noAgents'), icon: Icons.person_search_outlined),
                        ],
                      )
                    : ListView.separated(
                        itemCount: page.items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final a = page.items[i];
                          return ListTile(
                            leading: AgentAvatar(agent: a),
                            title: Text(a.fullName),
                            subtitle: Text(
                              [
                                ?a.officeName,
                                if (a.languages.isNotEmpty) a.languages.map((c) => l10n.t('lang_$c')).join(', '),
                              ].join(' · '),
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => context.push(Routes.agent(a.slug)),
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

class AgentAvatar extends StatelessWidget {
  const AgentAvatar({super.key, required this.agent, this.radius = 20});

  final PublicAgent agent;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final photo = agent.photoUrl;
    return CircleAvatar(
      radius: radius,
      foregroundImage: photo != null && photo.startsWith('https://') ? NetworkImage(photo) : null,
      child: Text(agent.initials),
    );
  }
}

class AgentProfileScreen extends ConsumerWidget {
  const AgentProfileScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final user = ref.watch(authControllerProvider).value;
    final agent = ref.watch(publicAgentProvider(slug));
    final text = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(title: Text(agent.value?.fullName ?? l10n.t('ourAgents'))),
      body: AsyncView<PublicAgent>(
        value: agent,
        onRetry: () => ref.invalidate(publicAgentProvider(slug)),
        data: (a) {
          final bio = a.bioFor(lang);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Center(child: AgentAvatar(agent: a, radius: 48)),
              const SizedBox(height: 12),
              Text(a.fullName, style: text.headlineSmall, textAlign: TextAlign.center),
              if (a.licenseLabel != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified, size: 18, color: Theme.of(context).colorScheme.primary),
                      const SizedBox(width: 4),
                      Flexible(
                        child: Text(
                          l10n.t('licenseVerifiedLabel', {
                            'type': a.licenseLabel!,
                            'state': a.licenseState ?? 'NJ',
                            'number': a.licenseNumber ?? '',
                          }),
                          style: text.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              if (a.phone != null)
                ListTile(
                  leading: const Icon(Icons.phone_outlined),
                  title: Text(a.phone!),
                  onTap: () => openExternal(context, telUri(a.phone!)),
                ),
              if (a.email != null)
                ListTile(
                  leading: const Icon(Icons.mail_outline),
                  title: Text(a.email!),
                  onTap: () => openExternal(context, mailUri(a.email!)),
                ),
              if (user != null && user.can('messages.use'))
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: FilledButton.icon(
                    key: const Key('agent-message'),
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: Text(l10n.t('messageAgent')),
                    onPressed: () => context.push(Routes.newConversation(agentSlug: a.slug, agentName: a.fullName)),
                  ),
                ),
              if (bio != null && bio.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(l10n.t('aboutAgent'), style: text.titleMedium),
                const SizedBox(height: 4),
                Text(bio),
              ],
              if (a.languages.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(l10n.t('languagesSpoken'), style: text.titleMedium),
                Wrap(spacing: 6, children: [for (final c in a.languages) Chip(label: Text(l10n.t('lang_$c')))]),
              ],
              if (a.specialties.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(l10n.t('specialties'), style: text.titleMedium),
                Wrap(spacing: 6, children: [for (final s in a.specialties) Chip(label: Text(l10n.t('spec_$s')))]),
              ],
              if (a.officeName != null) ...[
                const SizedBox(height: 12),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.business_outlined),
                    title: Text(a.officeName!),
                    subtitle: Text([?a.officeAddress, ?a.officePhone].join('\n')),
                    onTap: a.officePhone == null ? null : () => openExternal(context, telUri(a.officePhone!)),
                  ),
                ),
              ],
              if (a.agencyName != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    '${l10n.t('brokerage')}: ${a.agencyName}${a.agencyLicense != null ? ' · #${a.agencyLicense}' : ''}',
                    style: text.bodySmall,
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
