import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/error_banner.dart';
import '../data/auth_repository.dart';
import 'validators.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _sent = false;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).forgotPassword(_email.text);
      if (mounted) setState(() => _sent = true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('forgotPassword'))),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: _sent
            ? Text(l10n.t('resetLinkSent'))
            : Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null) ErrorBanner(_error!),
                    TextFormField(
                      controller: _email,
                      decoration: InputDecoration(labelText: l10n.t('email')),
                      keyboardType: TextInputType.emailAddress,
                      validator: (v) {
                        final key = Validators.email(v);
                        return key == null ? null : l10n.t(key);
                      },
                    ),
                    const SizedBox(height: 16),
                    FilledButton(onPressed: _submitting ? null : _submit, child: Text(l10n.t('sendResetLink'))),
                  ],
                ),
              ),
      ),
    );
  }
}
