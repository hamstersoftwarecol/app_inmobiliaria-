import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/async_view.dart';
import '../application/auth_controller.dart';
import '../data/auth_repository.dart';
import 'validators.dart';

/// Screens opened from e-mail links (App Links / Universal Links): password reset, e-mail
/// verification and one-click unsubscribe. They work signed in or out.

class _LinkScaffold extends StatelessWidget {
  const _LinkScaffold({required this.titleKey, required this.child});

  final String titleKey;
  final Widget child;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.t(titleKey))),
    body: SafeArea(
      child: ListView(padding: const EdgeInsets.all(24), children: [child]),
    ),
  );
}

class _Result extends ConsumerWidget {
  const _Result({required this.ok, required this.message});

  final bool ok;
  final String message;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final signedIn = ref.watch(authControllerProvider).value != null;
    return Column(
      children: [
        Icon(
          ok ? Icons.check_circle_outline : Icons.error_outline,
          size: 48,
          color: ok ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.error,
        ),
        const SizedBox(height: 12),
        Text(message, key: const Key('link-result'), textAlign: TextAlign.center),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: () => context.go(signedIn ? Routes.home : Routes.login),
          child: Text(context.l10n.t(signedIn ? 'goHome' : 'signIn')),
        ),
      ],
    );
  }
}

// ------------------------------------------------------------------------------------ reset

class ResetPasswordScreen extends ConsumerStatefulWidget {
  const ResetPasswordScreen({super.key, required this.token, required this.email});

  final String? token;
  final String? email;

  @override
  ConsumerState<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends ConsumerState<ResetPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  String? _error;
  bool _busy = false;
  bool _done = false;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _error = null);
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(authRepositoryProvider)
          .resetPassword(token: widget.token!, email: widget.email!, password: _password.text);
      setState(() => _done = true);
    } on ApiException catch (e) {
      setState(() => _error = e.fieldError('email') ?? e.fieldError('password') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if ((widget.token ?? '').isEmpty || (widget.email ?? '').isEmpty) {
      return _LinkScaffold(
        titleKey: 'resetPassword',
        child: _Result(ok: false, message: l10n.t('invalidLink')),
      );
    }
    if (_done) {
      return _LinkScaffold(
        titleKey: 'resetPassword',
        child: _Result(ok: true, message: l10n.t('passwordResetDone')),
      );
    }
    return _LinkScaffold(
      titleKey: 'resetPassword',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(widget.email!, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            TextFormField(
              key: const Key('reset-password'),
              controller: _password,
              obscureText: true,
              decoration: InputDecoration(
                labelText: l10n.t('newPassword'),
                helperText: l10n.t('passwordRules'),
                helperMaxLines: 3,
              ),
              validator: (v) {
                final key = Validators.strongPassword(v);
                return key == null ? null : l10n.t(key);
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              key: const Key('reset-confirm'),
              controller: _confirm,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.t('confirmPassword')),
              validator: (v) => v == _password.text ? null : l10n.t('passwordsDontMatch'),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('reset-submit'),
              onPressed: _busy ? null : _submit,
              child: Text(l10n.t('resetPassword')),
            ),
          ],
        ),
      ),
    );
  }
}

// ------------------------------------------------------------------------------------ one-shot links

/// Runs a single request when opened (verify e-mail, unsubscribe) and shows the outcome.
class _OneShotLink extends ConsumerStatefulWidget {
  const _OneShotLink({required this.titleKey, required this.successKey, required this.valid, required this.action});

  final String titleKey;
  final String successKey;
  final bool valid;
  final Future<void> Function(WidgetRef ref) action;

  @override
  ConsumerState<_OneShotLink> createState() => _OneShotLinkState();
}

class _OneShotLinkState extends ConsumerState<_OneShotLink> {
  late final Future<void>? _future = widget.valid ? widget.action(ref) : null;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (_future == null) {
      return _LinkScaffold(
        titleKey: widget.titleKey,
        child: _Result(ok: false, message: l10n.t('invalidLink')),
      );
    }
    return _LinkScaffold(
      titleKey: widget.titleKey,
      child: FutureBuilder<void>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) return _Result(ok: false, message: errorMessage(context, snap.error!));
          return _Result(ok: true, message: l10n.t(widget.successKey));
        },
      ),
    );
  }
}

class VerifyEmailScreen extends StatelessWidget {
  const VerifyEmailScreen({super.key, required this.params});

  final Map<String, String> params;

  @override
  Widget build(BuildContext context) {
    final valid = ['id', 'hash', 'expires', 'signature'].every((k) => (params[k] ?? '').isNotEmpty);
    return _OneShotLink(
      titleKey: 'verifyEmail',
      successKey: 'emailVerified',
      valid: valid,
      action: (ref) async {
        await ref
            .read(authRepositoryProvider)
            .verifyEmail(
              id: params['id']!,
              hash: params['hash']!,
              expires: params['expires']!,
              signature: params['signature']!,
            );
        // Refresh the banner on the home screen when this device is signed in.
        if (ref.read(authControllerProvider).value != null) {
          await ref.read(authControllerProvider.notifier).refreshUser();
        }
      },
    );
  }
}

class UnsubscribeScreen extends StatelessWidget {
  const UnsubscribeScreen({super.key, required this.token});

  final String? token;

  @override
  Widget build(BuildContext context) => _OneShotLink(
    titleKey: 'unsubscribeTitle',
    successKey: 'unsubscribed',
    valid: (token ?? '').isNotEmpty,
    action: (ref) => ref.read(authRepositoryProvider).unsubscribe(token!),
  );
}
