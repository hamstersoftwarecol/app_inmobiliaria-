import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../auth/application/auth_controller.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final user = ref.watch(authControllerProvider).value;
    final locale = ref.watch(localeControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('profile'))),
      body: user == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                ListTile(
                  leading: CircleAvatar(child: Text(user.firstName.isNotEmpty ? user.firstName[0] : '?')),
                  title: Text(user.fullName),
                  subtitle: Text(user.email),
                ),
                const Divider(),
                ListTile(
                  title: Text(l10n.t('roles')),
                  subtitle: Wrap(
                    spacing: 8,
                    children: [for (final role in user.roles) Chip(label: Text(role.displayName))],
                  ),
                ),
                ListTile(
                  leading: Icon(user.mfaEnabled ? Icons.verified_user : Icons.shield_outlined),
                  title: Text(user.mfaEnabled ? l10n.t('mfaStatusOn') : l10n.t('mfaStatusOff')),
                ),
                const Divider(),
                for (final (icon, key, route) in [
                  (Icons.edit_outlined, 'editProfile', Routes.editProfile),
                  (Icons.password, 'changePassword', Routes.changePassword),
                  (Icons.security, 'security', Routes.security),
                  (Icons.mark_email_read_outlined, 'emailPreferences', Routes.emailPreferences),
                  if (user.can('agents.manage_own_profile'))
                    (Icons.badge_outlined, 'myAgentProfile', Routes.myAgentProfile),
                ])
                  ListTile(
                    key: Key('profile-$key'),
                    leading: Icon(icon),
                    title: Text(l10n.t(key)),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push(route),
                  ),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.language),
                  title: Text(l10n.t('language')),
                  trailing: DropdownButton<String>(
                    value: locale.languageCode,
                    items: const [
                      DropdownMenuItem(value: 'en', child: Text('English')),
                      DropdownMenuItem(value: 'es', child: Text('Español')),
                    ],
                    onChanged: (v) => ref.read(localeControllerProvider.notifier).setLocale(Locale(v ?? 'en')),
                  ),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  key: const Key('logout-button'),
                  icon: const Icon(Icons.logout),
                  label: Text(l10n.t('signOut')),
                  onPressed: () => ref.read(authControllerProvider.notifier).logout(),
                ),
              ],
            ),
    );
  }
}
