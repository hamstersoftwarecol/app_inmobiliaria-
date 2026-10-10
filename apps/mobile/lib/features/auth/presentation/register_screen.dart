import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/error_banner.dart';
import '../application/auth_controller.dart';
import '../data/auth_repository.dart';
import 'validators.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  String _accountType = 'buyer';
  bool _acceptTerms = false;
  bool _submitting = false;
  String? _error;
  Map<String, String> _serverErrors = {};

  @override
  void dispose() {
    for (final c in [_first, _last, _email, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _serverErrors = {});
    if (!_formKey.currentState!.validate()) return;
    if (!_acceptTerms) {
      setState(() => _error = context.l10n.t('acceptTermsRequired'));
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .register(
            RegisterRequest(
              firstName: _first.text,
              lastName: _last.text,
              email: _email.text,
              password: _password.text,
              accountType: _accountType,
              locale: ref.read(localeControllerProvider).languageCode,
            ),
          );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _serverErrors = e.fieldErrors.map((k, v) => MapEntry(k, v.first));
        _error = e.fieldErrors.isEmpty ? e.message : null;
      });
      _formKey.currentState!.validate();
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    String? tr(String? key) => key == null ? null : l10n.t(key);
    String? server(String field) => _serverErrors[field];

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('createAccount'))),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null) ErrorBanner(_error!),
                TextFormField(
                  controller: _first,
                  decoration: InputDecoration(labelText: l10n.t('firstName')),
                  autofillHints: const [AutofillHints.givenName],
                  validator: (v) => tr(Validators.required(v)) ?? server('first_name'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _last,
                  decoration: InputDecoration(labelText: l10n.t('lastName')),
                  autofillHints: const [AutofillHints.familyName],
                  validator: (v) => tr(Validators.required(v)) ?? server('last_name'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _email,
                  decoration: InputDecoration(labelText: l10n.t('email')),
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.email],
                  validator: (v) => tr(Validators.email(v)) ?? server('email'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _accountType,
                  decoration: InputDecoration(labelText: l10n.t('accountType')),
                  items: [
                    DropdownMenuItem(value: 'buyer', child: Text(l10n.t('buyer'))),
                    DropdownMenuItem(value: 'seller', child: Text(l10n.t('seller'))),
                  ],
                  onChanged: (v) => setState(() => _accountType = v ?? 'buyer'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _password,
                  decoration: InputDecoration(
                    labelText: l10n.t('password'),
                    helperText: l10n.t('passwordHint'),
                    helperMaxLines: 2,
                  ),
                  obscureText: true,
                  autofillHints: const [AutofillHints.newPassword],
                  validator: (v) => tr(Validators.strongPassword(v)) ?? server('password'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _confirm,
                  decoration: InputDecoration(labelText: l10n.t('confirmPassword')),
                  obscureText: true,
                  validator: (v) => v != _password.text ? l10n.t('passwordMismatch') : null,
                ),
                CheckboxListTile(
                  value: _acceptTerms,
                  onChanged: (v) => setState(() => _acceptTerms = v ?? false),
                  title: Text(l10n.t('acceptTerms')),
                  controlAffinity: ListTileControlAffinity.leading,
                  contentPadding: EdgeInsets.zero,
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(l10n.t('createAccount')),
                ),
                TextButton(onPressed: () => context.go(Routes.login), child: Text(l10n.t('haveAccount'))),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
