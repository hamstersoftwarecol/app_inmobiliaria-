import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/util/format.dart';
import '../../../shared/launch.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/presentation/validators.dart';
import '../data/account_repository.dart';

String? Function(String?) _v(BuildContext context, String? Function(String?) rule) => (value) {
  final key = rule(value);
  return key == null ? null : context.l10n.t(key);
};

// ------------------------------------------------------------------------------------ profile

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _user = ref.read(authControllerProvider).value;
  late final _first = TextEditingController(text: _user?.firstName ?? '');
  late final _last = TextEditingController(text: _user?.lastName ?? '');
  late final _phone = TextEditingController(text: _user?.phone ?? '');
  Map<String, List<String>> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_first, _last, _phone]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _errors = const {});
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final user = await ref.read(accountRepositoryProvider).updateProfile({
        'first_name': _first.text.trim(),
        'last_name': _last.text.trim(),
        'phone': nullIfBlank(_phone.text),
      });
      ref.read(authControllerProvider.notifier).updateUser(user);
      if (mounted) {
        showMessage(context, context.l10n.t('saved'));
        Navigator.of(context).maybePop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _errors = e.fieldErrors);
        showError(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('editProfile'))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              key: const Key('profile-first'),
              controller: _first,
              decoration: InputDecoration(labelText: l10n.t('firstName'), errorText: _errors['first_name']?.first),
              validator: _v(context, Validators.requiredMax(100)),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _last,
              decoration: InputDecoration(labelText: l10n.t('lastName'), errorText: _errors['last_name']?.first),
              validator: _v(context, Validators.requiredMax(100)),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: l10n.t('phone'), errorText: _errors['phone']?.first),
              validator: _v(context, Validators.optionalPhone),
            ),
            const SizedBox(height: 8),
            Text(l10n.t('emailChangeOnWeb'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 16),
            FilledButton(key: const Key('profile-save'), onPressed: _busy ? null : _save, child: Text(l10n.t('save'))),
          ],
        ),
      ),
    );
  }
}

// ------------------------------------------------------------------------------------ password

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();
  Map<String, List<String>> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_current, _new, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _errors = const {});
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref.read(accountRepositoryProvider).changePassword(current: _current.text, password: _new.text);
      if (mounted) {
        showMessage(context, context.l10n.t('passwordChanged'));
        Navigator.of(context).maybePop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _errors = e.fieldErrors);
        showError(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('changePassword'))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              key: const Key('password-current'),
              controller: _current,
              obscureText: true,
              decoration: InputDecoration(
                labelText: l10n.t('currentPassword'),
                errorText: _errors['current_password']?.first,
              ),
              validator: _v(context, Validators.required),
            ),
            const SizedBox(height: 12),
            TextFormField(
              key: const Key('password-new'),
              controller: _new,
              obscureText: true,
              decoration: InputDecoration(
                labelText: l10n.t('newPassword'),
                helperText: l10n.t('passwordRules'),
                helperMaxLines: 3,
                errorText: _errors['password']?.first,
              ),
              validator: _v(context, Validators.strongPassword),
            ),
            const SizedBox(height: 12),
            TextFormField(
              key: const Key('password-confirm'),
              controller: _confirm,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.t('confirmPassword')),
              validator: (v) => v == _new.text ? null : l10n.t('passwordsDontMatch'),
            ),
            const SizedBox(height: 8),
            Text(l10n.t('otherDevicesSignedOut'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 16),
            FilledButton(key: const Key('password-save'), onPressed: _busy ? null : _save, child: Text(l10n.t('save'))),
          ],
        ),
      ),
    );
  }
}

// ------------------------------------------------------------------------------------ security

/// Two-factor authentication, signed-in devices and account closure.
class SecurityScreen extends ConsumerWidget {
  const SecurityScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final user = ref.watch(authControllerProvider).value;
    final sessions = ref.watch(sessionsProvider);
    final text = Theme.of(context).textTheme;
    if (user == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('security'))),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(sessionsProvider.future),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(l10n.t('twoFactor'), style: text.titleMedium),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(user.mfaEnabled ? Icons.verified_user : Icons.shield_outlined),
              title: Text(user.mfaEnabled ? l10n.t('mfaOn') : l10n.t('mfaOff')),
            ),
            if (!user.mfaEnabled)
              FilledButton(
                key: const Key('mfa-enable'),
                onPressed: () => showModalBottomSheet<void>(
                  context: context,
                  isScrollControlled: true,
                  useSafeArea: true,
                  builder: (_) => const _EnableMfaSheet(),
                ),
                child: Text(l10n.t('enableMfa')),
              )
            else
              Wrap(
                spacing: 8,
                children: [
                  OutlinedButton(
                    onPressed: () => _passwordAndCode(
                      context,
                      ref,
                      titleKey: 'newRecoveryCodes',
                      onConfirm: (p, c) async {
                        final codes = await ref
                            .read(accountRepositoryProvider)
                            .regenerateRecoveryCodes(password: p, code: c);
                        if (context.mounted) await _showRecoveryCodes(context, codes);
                      },
                    ),
                    child: Text(l10n.t('newRecoveryCodes')),
                  ),
                  TextButton(
                    key: const Key('mfa-disable'),
                    onPressed: () => _passwordAndCode(
                      context,
                      ref,
                      titleKey: 'disableMfa',
                      onConfirm: (p, c) async {
                        await ref.read(accountRepositoryProvider).mfaDisable(password: p, code: c);
                        await ref.read(authControllerProvider.notifier).refreshUser();
                      },
                    ),
                    child: Text(l10n.t('disableMfa')),
                  ),
                ],
              ),
            const Divider(height: 32),
            Row(
              children: [
                Expanded(child: Text(l10n.t('signedInDevices'), style: text.titleMedium)),
                TextButton(
                  onPressed: () async {
                    try {
                      await ref.read(accountRepositoryProvider).revokeOtherSessions();
                      ref.invalidate(sessionsProvider);
                    } catch (e) {
                      if (context.mounted) showError(context, e);
                    }
                  },
                  child: Text(l10n.t('signOutOthers')),
                ),
              ],
            ),
            AsyncView<List<AuthSession>>(
              value: sessions,
              onRetry: () => ref.invalidate(sessionsProvider),
              data: (list) => Column(
                children: [
                  for (final s in list)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(s.type == 'web' ? Icons.computer : Icons.smartphone),
                      title: Text(s.isCurrent ? '${s.name} · ${l10n.t('thisDevice')}' : s.name),
                      subtitle: Text(l10n.t('lastActive', {'date': formatDateTime(s.lastUsedAt, lang)})),
                      trailing: s.isCurrent
                          ? null
                          : IconButton(
                              tooltip: l10n.t('signOut'),
                              icon: const Icon(Icons.logout),
                              onPressed: () async {
                                try {
                                  await ref.read(accountRepositoryProvider).revokeSession(s.id);
                                  ref.invalidate(sessionsProvider);
                                } catch (e) {
                                  if (context.mounted) showError(context, e);
                                }
                              },
                            ),
                    ),
                ],
              ),
            ),
            const Divider(height: 32),
            Text(l10n.t('closeAccount'), style: text.titleMedium),
            Text(l10n.t('closeAccountHint'), style: text.bodySmall),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton(
                key: const Key('close-account'),
                style: TextButton.styleFrom(foregroundColor: Theme.of(context).colorScheme.error),
                onPressed: () => _passwordAndCode(
                  context,
                  ref,
                  titleKey: 'closeAccount',
                  codeOptional: !user.mfaEnabled,
                  onConfirm: (p, c) async {
                    await ref.read(accountRepositoryProvider).deactivate(password: p, code: nullIfBlank(c));
                    await ref.read(authControllerProvider.notifier).signedOutRemotely();
                  },
                ),
                child: Text(l10n.t('closeAccount')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Re-authentication dialog (password, plus the 6-digit code when MFA is on).
Future<void> _passwordAndCode(
  BuildContext context,
  WidgetRef ref, {
  required String titleKey,
  required Future<void> Function(String password, String code) onConfirm,
  bool codeOptional = false,
}) => showDialog<void>(
  context: context,
  builder: (_) => _ReauthDialog(titleKey: titleKey, onConfirm: onConfirm, withCode: !codeOptional),
);

class _ReauthDialog extends StatefulWidget {
  const _ReauthDialog({required this.titleKey, required this.onConfirm, required this.withCode});

  final String titleKey;
  final Future<void> Function(String password, String code) onConfirm;
  final bool withCode;

  @override
  State<_ReauthDialog> createState() => _ReauthDialogState();
}

class _ReauthDialogState extends State<_ReauthDialog> {
  final _password = TextEditingController();
  final _code = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    if (_password.text.isEmpty) return setState(() => _error = l10n.t('required'));
    if (widget.withCode && Validators.mfaCode(_code.text) != null) {
      return setState(() => _error = l10n.t('mfaCodeInvalid'));
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onConfirm(_password.text, _code.text.trim());
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.t(widget.titleKey)),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            key: const Key('reauth-password'),
            controller: _password,
            obscureText: true,
            decoration: InputDecoration(labelText: l10n.t('password')),
          ),
          if (widget.withCode)
            TextField(
              key: const Key('reauth-code'),
              controller: _code,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: InputDecoration(labelText: l10n.t('mfaCode')),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l10n.t('cancel'))),
        FilledButton(
          key: const Key('reauth-confirm'),
          onPressed: _busy ? null : _submit,
          child: Text(l10n.t('confirm')),
        ),
      ],
    );
  }
}

Future<void> _showRecoveryCodes(BuildContext context, List<String> codes) => showDialog<void>(
  context: context,
  barrierDismissible: false,
  builder: (context) {
    final l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.t('recoveryCodes')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.t('recoveryCodesHint')),
          const SizedBox(height: 8),
          SelectableText(codes.join('\n'), style: const TextStyle(fontFamily: 'monospace')),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Clipboard.setData(ClipboardData(text: codes.join('\n'))),
          child: Text(l10n.t('copy')),
        ),
        FilledButton(onPressed: () => Navigator.of(context).pop(), child: Text(l10n.t('savedThem'))),
      ],
    );
  },
);

class _EnableMfaSheet extends ConsumerStatefulWidget {
  const _EnableMfaSheet();

  @override
  ConsumerState<_EnableMfaSheet> createState() => _EnableMfaSheetState();
}

class _EnableMfaSheetState extends ConsumerState<_EnableMfaSheet> {
  final _password = TextEditingController();
  final _code = TextEditingController();
  MfaSetup? _setup;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _step(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _start() => _step(() async {
    if (_password.text.isEmpty) {
      throw ApiException(statusCode: 422, code: 'required', message: context.l10n.t('required'));
    }
    final setup = await ref.read(accountRepositoryProvider).mfaSetup(_password.text);
    setState(() => _setup = setup);
  });

  Future<void> _confirm() => _step(() async {
    if (Validators.mfaCode(_code.text) != null) {
      throw ApiException(statusCode: 422, code: 'mfa', message: context.l10n.t('mfaCodeInvalid'));
    }
    final codes = await ref.read(accountRepositoryProvider).mfaConfirm(_code.text.trim());
    await ref.read(authControllerProvider.notifier).refreshUser();
    if (!mounted) return;
    final navigator = Navigator.of(context);
    await _showRecoveryCodes(context, codes);
    navigator.pop();
  });

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final setup = _setup;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: ListView(
        shrinkWrap: true,
        children: [
          Text(l10n.t('enableMfa'), style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          if (setup == null) ...[
            Text(l10n.t('mfaStep1')),
            TextField(
              key: const Key('mfa-password'),
              controller: _password,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.t('password')),
            ),
          ] else ...[
            Text(l10n.t('mfaStep2')),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              icon: const Icon(Icons.open_in_new),
              label: Text(l10n.t('openAuthenticator')),
              onPressed: () => openExternal(context, Uri.parse(setup.otpauthUrl)),
            ),
            const SizedBox(height: 8),
            Text(l10n.t('orEnterKey')),
            Row(
              children: [
                Expanded(
                  child: SelectableText(setup.secret, style: const TextStyle(fontFamily: 'monospace')),
                ),
                IconButton(
                  tooltip: l10n.t('copy'),
                  icon: const Icon(Icons.copy),
                  onPressed: () => Clipboard.setData(ClipboardData(text: setup.secret)),
                ),
              ],
            ),
            TextField(
              key: const Key('mfa-confirm-code'),
              controller: _code,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: InputDecoration(labelText: l10n.t('mfaCode')),
            ),
          ],
          if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 12),
          FilledButton(
            key: const Key('mfa-next'),
            onPressed: _busy ? null : (setup == null ? _start : _confirm),
            child: Text(setup == null ? l10n.t('continue') : l10n.t('activate')),
          ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------------------------------ e-mail prefs

class NotificationPreferencesScreen extends ConsumerWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final prefs = ref.watch(notificationPreferencesProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('emailPreferences'))),
      body: AsyncView<Map<String, bool>>(
        value: prefs,
        onRetry: () => ref.invalidate(notificationPreferencesProvider),
        data: (map) => ListView(
          children: [
            Padding(padding: const EdgeInsets.all(16), child: Text(l10n.t('emailPreferencesIntro'))),
            for (final event in notificationEvents)
              SwitchListTile(
                key: Key('pref-$event'),
                value: map[event] ?? false,
                title: Text(l10n.t('evt_$event')),
                onChanged: (on) async {
                  try {
                    await ref.read(accountRepositoryProvider).setNotificationPreference(event, on);
                    ref.invalidate(notificationPreferencesProvider);
                  } catch (e) {
                    if (context.mounted) showError(context, e);
                  }
                },
              ),
          ],
        ),
      ),
    );
  }
}
