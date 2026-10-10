import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/error_banner.dart';
import '../application/auth_controller.dart';
import 'validators.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _mfa = TextEditingController();
  bool _mfaRequired = false;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _mfa.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .login(email: _email.text, password: _password.text, mfaCode: _mfaRequired ? _mfa.text : null);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        if (e.code == 'mfa_required') {
          _mfaRequired = true;
        } else {
          _error = e.message;
        }
      });
    } catch (_) {
      if (mounted) setState(() => _error = context.l10n.t('genericError'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    String? tr(String? key) => key == null ? null : l10n.t(key);

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        l10n.t('appName'),
                        style: Theme.of(context).textTheme.headlineMedium,
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 8),
                      Text(l10n.t('tagline'), textAlign: TextAlign.center),
                      const SizedBox(height: 32),
                      if (_error != null) ErrorBanner(_error!),
                      TextFormField(
                        key: const Key('login-email'),
                        controller: _email,
                        decoration: InputDecoration(labelText: l10n.t('email')),
                        keyboardType: TextInputType.emailAddress,
                        autofillHints: const [AutofillHints.email],
                        textInputAction: TextInputAction.next,
                        validator: (v) => tr(Validators.email(v)),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        key: const Key('login-password'),
                        controller: _password,
                        decoration: InputDecoration(labelText: l10n.t('password')),
                        obscureText: true,
                        autofillHints: const [AutofillHints.password],
                        validator: (v) => tr(Validators.required(v)),
                      ),
                      if (_mfaRequired) ...[
                        const SizedBox(height: 16),
                        Text(l10n.t('mfaPrompt')),
                        const SizedBox(height: 8),
                        TextFormField(
                          key: const Key('login-mfa'),
                          controller: _mfa,
                          decoration: InputDecoration(labelText: l10n.t('mfaCode')),
                          keyboardType: TextInputType.number,
                          autofillHints: const [AutofillHints.oneTimeCode],
                          maxLength: 6,
                          autofocus: true,
                          validator: (v) => tr(Validators.mfaCode(v)),
                        ),
                      ],
                      const SizedBox(height: 24),
                      FilledButton(
                        key: const Key('login-submit'),
                        onPressed: _submitting ? null : _submit,
                        child: _submitting
                            ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                            : Text(l10n.t('signIn')),
                      ),
                      TextButton(
                        onPressed: () => context.push(Routes.forgotPassword),
                        child: Text(l10n.t('forgotPassword')),
                      ),
                      TextButton(onPressed: () => context.go(Routes.register), child: Text(l10n.t('noAccount'))),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
