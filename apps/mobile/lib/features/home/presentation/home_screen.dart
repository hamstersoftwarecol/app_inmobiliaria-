import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/router/app_router.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/domain/user.dart';
import '../../notifications/data/notification_repository.dart';

/// Hub: shortcuts for everyone, plus agent mode (leads, tasks, transactions) for agents and staff.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  static bool canUseCrm(User user) => user.can('leads.view_any') || user.can('leads.view_assigned');

  static bool canSeeShowings(User user) => [
    'appointments.request',
    'appointments.manage_own',
    'appointments.manage_any',
    'appointments.view_any',
  ].any(user.can);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final user = ref.watch(authControllerProvider).value;
    final unread = ref.watch(unreadNotificationsProvider).value ?? 0;

    Widget tile(IconData icon, String key, String route, {Key? tileKey, Widget? trailing}) => Card(
      child: ListTile(
        key: tileKey,
        leading: Icon(icon),
        title: Text(l10n.t(key)),
        trailing: trailing ?? const Icon(Icons.chevron_right),
        onTap: () => route.startsWith(Routes.home) ? context.push(route) : context.go(route),
      ),
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('appName')),
        actions: [
          IconButton(
            key: const Key('notifications-bell'),
            tooltip: l10n.t('notificationsCount', {'n': '$unread'}),
            icon: Badge(
              isLabelVisible: unread > 0,
              label: Text(unread > 99 ? '99+' : '$unread'),
              child: const Icon(Icons.notifications_outlined),
            ),
            onPressed: () => context.push(Routes.notifications),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (user != null)
            Text(l10n.t('welcome', {'name': user.firstName}), style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 8),
          Text(l10n.t('tagline')),
          const SizedBox(height: 16),
          if (user != null && !user.emailVerified)
            Card(
              child: ListTile(
                leading: const Icon(Icons.mark_email_unread_outlined),
                title: Text(l10n.t('emailNotVerified')),
              ),
            ),
          tile(Icons.search, 'searchHomes', Routes.search, tileKey: const Key('home-search')),
          if (user != null && user.can('saved_searches.manage'))
            tile(Icons.bookmark_border, 'savedSearches', Routes.savedSearches),
          if (user != null && canSeeShowings(user)) tile(Icons.event, 'showings', Routes.showings),
          tile(Icons.handshake_outlined, 'transactions', Routes.transactions),
          if (user != null && canUseCrm(user)) ...[
            const SizedBox(height: 16),
            Text(l10n.t('agentMode'), style: Theme.of(context).textTheme.titleMedium),
            tile(Icons.people_outline, 'leads', Routes.leads, tileKey: const Key('home-leads')),
            tile(Icons.task_alt, 'tasks', Routes.tasks),
            if (user.isStaff && !user.mfaEnabled)
              Card(
                child: ListTile(leading: const Icon(Icons.shield_outlined), title: Text(l10n.t('staffMfaRequired'))),
              ),
          ],
        ],
      ),
    );
  }
}
