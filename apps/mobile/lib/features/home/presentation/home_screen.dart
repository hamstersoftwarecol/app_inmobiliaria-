import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/router/app_router.dart';
import '../../auth/application/auth_controller.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('appName'))),
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
          Card(
            child: ListTile(
              leading: const Icon(Icons.search),
              title: Text(l10n.t('search')),
              subtitle: Text(l10n.t('comingSoon')),
              onTap: () => context.go(Routes.search),
            ),
          ),
        ],
      ),
    );
  }
}
