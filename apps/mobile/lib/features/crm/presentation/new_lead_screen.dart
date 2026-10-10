import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/presentation/validators.dart';
import '../data/crm_repository.dart';

/// Sources an agent can record by hand (website sources are created by the site itself).
const manualLeadSources = ['phone', 'email', 'walk_in', 'referral', 'social', 'other'];
const leadIntents = ['buy', 'rent', 'sell', 'other'];

class NewLeadScreen extends ConsumerStatefulWidget {
  const NewLeadScreen({super.key});

  @override
  ConsumerState<NewLeadScreen> createState() => _NewLeadScreenState();
}

class _NewLeadScreenState extends ConsumerState<NewLeadScreen> {
  final _formKey = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _message = TextEditingController();
  final _budgetMin = TextEditingController();
  final _budgetMax = TextEditingController();
  String _source = 'phone';
  String? _intent;
  String? _language;
  bool _consent = false;
  Map<String, List<String>> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_first, _last, _email, _phone, _message, _budgetMin, _budgetMax]) {
      c.dispose();
    }
    super.dispose();
  }

  String? Function(String?) _v(String? Function(String?) rule) => (value) {
    final key = rule(value);
    return key == null ? null : context.l10n.t(key);
  };

  Future<void> _save() async {
    final l10n = context.l10n;
    setState(() => _errors = const {});
    if (!_formKey.currentState!.validate()) return;
    final min = parseOptionalNumber(_budgetMin.text);
    final max = parseOptionalNumber(_budgetMax.text);
    if (min != null && max != null && max < min) return showMessage(context, l10n.t('priceRange'));

    setState(() => _busy = true);
    try {
      final id = await ref.read(crmRepositoryProvider).createLead({
        'first_name': _first.text.trim(),
        'last_name': nullIfBlank(_last.text),
        'email': nullIfBlank(_email.text),
        'phone': nullIfBlank(_phone.text),
        'source': _source,
        'intent': _intent,
        'message': nullIfBlank(_message.text),
        'budget_min': min,
        'budget_max': max,
        'preferred_language': _language,
        'contact_consent': _consent,
      });
      ref.invalidate(leadsProvider);
      if (mounted) context.pushReplacement(Routes.lead(id));
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
    const gap = SizedBox(height: 12);
    String? err(String f) => _errors[f]?.first;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('newLead'))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              key: const Key('lead-first'),
              controller: _first,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(labelText: l10n.t('firstName'), errorText: err('first_name')),
              validator: _v(Validators.requiredMax(100)),
            ),
            gap,
            TextFormField(
              controller: _last,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(labelText: l10n.t('lastName'), errorText: err('last_name')),
            ),
            gap,
            TextFormField(
              key: const Key('lead-email'),
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(labelText: l10n.t('email'), errorText: err('email')),
              validator: _v(Validators.optionalEmail),
            ),
            gap,
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: l10n.t('phone'), errorText: err('phone')),
              validator: _v(Validators.optionalPhone),
            ),
            gap,
            DropdownButtonFormField<String>(
              initialValue: _source,
              decoration: InputDecoration(labelText: l10n.t('leadSource')),
              items: [for (final s in manualLeadSources) DropdownMenuItem(value: s, child: Text(l10n.t('source_$s')))],
              onChanged: (v) => setState(() => _source = v ?? _source),
            ),
            gap,
            DropdownButtonFormField<String?>(
              initialValue: _intent,
              decoration: InputDecoration(labelText: l10n.t('lookingTo')),
              items: [
                DropdownMenuItem(child: Text(l10n.t('any'))),
                for (final i in leadIntents) DropdownMenuItem(value: i, child: Text(l10n.t('intent_$i'))),
              ],
              onChanged: (v) => setState(() => _intent = v),
            ),
            gap,
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _budgetMin,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: l10n.t('minPrice'),
                      prefixText: r'$ ',
                      errorText: err('budget_min'),
                    ),
                    validator: _v(Validators.optionalMoney),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _budgetMax,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: l10n.t('maxPrice'),
                      prefixText: r'$ ',
                      errorText: err('budget_max'),
                    ),
                    validator: _v(Validators.optionalMoney),
                  ),
                ),
              ],
            ),
            gap,
            DropdownButtonFormField<String?>(
              initialValue: _language,
              decoration: InputDecoration(labelText: l10n.t('preferredLanguage')),
              items: [
                DropdownMenuItem(child: Text(l10n.t('any'))),
                const DropdownMenuItem(value: 'en', child: Text('English')),
                const DropdownMenuItem(value: 'es', child: Text('Español')),
              ],
              onChanged: (v) => setState(() => _language = v),
            ),
            gap,
            TextFormField(
              controller: _message,
              maxLength: 5000,
              maxLines: 3,
              decoration: InputDecoration(labelText: l10n.t('notes'), errorText: err('message')),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _consent,
              title: Text(l10n.t('leadConsent')),
              onChanged: (v) => setState(() => _consent = v ?? false),
            ),
            const SizedBox(height: 8),
            FilledButton(key: const Key('lead-save'), onPressed: _busy ? null : _save, child: Text(l10n.t('save'))),
          ],
        ),
      ),
    );
  }
}
