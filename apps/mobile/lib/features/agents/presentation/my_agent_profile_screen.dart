import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/presentation/validators.dart';
import '../data/agent_repository.dart';
import '../domain/agent.dart';

/// The agent's public profile (contact, bios, languages, specialties, visibility) and licences.
class MyAgentProfileScreen extends ConsumerWidget {
  const MyAgentProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final profile = ref.watch(myAgentProfileProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('myAgentProfile'))),
      body: AsyncView<MyAgentProfile>(
        value: profile,
        onRetry: () => ref.invalidate(myAgentProfileProvider),
        data: (p) => _ProfileForm(key: ValueKey(p), profile: p),
      ),
    );
  }
}

class _ProfileForm extends ConsumerStatefulWidget {
  const _ProfileForm({super.key, required this.profile});

  final MyAgentProfile profile;

  @override
  ConsumerState<_ProfileForm> createState() => _ProfileFormState();
}

class _ProfileFormState extends ConsumerState<_ProfileForm> {
  final _formKey = GlobalKey<FormState>();
  late final _email = TextEditingController(text: widget.profile.publicEmail ?? '');
  late final _phone = TextEditingController(text: widget.profile.publicPhone ?? '');
  late final _bio = TextEditingController(text: widget.profile.bio ?? '');
  late final _bioEs = TextEditingController(text: widget.profile.bioEs ?? '');
  late final Set<String> _languages = widget.profile.languages.toSet();
  late final Set<String> _specialties = widget.profile.specialties.toSet();
  late bool _isPublic = widget.profile.isPublic;
  Map<String, List<String>> _serverErrors = const {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_email, _phone, _bio, _bioEs]) {
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
    setState(() => _serverErrors = const {});
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref.read(agentRepositoryProvider).updateMyProfile({
        'public_email': nullIfBlank(_email.text),
        'public_phone': nullIfBlank(_phone.text),
        'bio': nullIfBlank(_bio.text),
        'bio_es': nullIfBlank(_bioEs.text),
        'languages': [
          for (final c in agentLanguages)
            if (_languages.contains(c)) c,
        ],
        'specialties': [
          for (final s in agentSpecialties)
            if (_specialties.contains(s)) s,
        ],
        'is_public': _isPublic,
      });
      if (mounted) showMessage(context, l10n.t('saved'));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _serverErrors = e.fieldErrors);
        showError(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final p = widget.profile;
    final text = Theme.of(context).textTheme;
    String? err(String field) => _serverErrors[field]?.first;

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: Icon(p.licenseVerified ? Icons.verified : Icons.pending_outlined),
              title: Text([?p.agencyName, ?p.officeName].join(' · ')),
              subtitle: Text(p.licenseVerified ? l10n.t('licenseVerified') : l10n.t('licenseNotVerified')),
            ),
          ),
          SwitchListTile(
            key: const Key('agent-public'),
            contentPadding: EdgeInsets.zero,
            value: _isPublic,
            title: Text(l10n.t('showInDirectory')),
            subtitle: Text(l10n.t('showInDirectoryHint')),
            onChanged: (v) => setState(() => _isPublic = v),
          ),
          TextFormField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(labelText: l10n.t('publicEmail'), errorText: err('public_email')),
            validator: _v(Validators.optionalEmail),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(labelText: l10n.t('publicPhone'), errorText: err('public_phone')),
            validator: _v(Validators.optionalPhone),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _bio,
            maxLength: 5000,
            maxLines: 4,
            decoration: InputDecoration(labelText: l10n.t('bioEn'), errorText: err('bio')),
          ),
          TextFormField(
            controller: _bioEs,
            maxLength: 5000,
            maxLines: 4,
            decoration: InputDecoration(labelText: l10n.t('bioEs'), errorText: err('bio_es')),
          ),
          Text(l10n.t('languagesSpoken'), style: text.titleSmall),
          Wrap(
            spacing: 6,
            children: [
              for (final c in agentLanguages)
                FilterChip(
                  label: Text(l10n.t('lang_$c')),
                  selected: _languages.contains(c),
                  onSelected: (on) => setState(() {
                    if (!on) {
                      _languages.remove(c);
                    } else if (_languages.length < 10) {
                      _languages.add(c);
                    }
                  }),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(l10n.t('specialties'), style: text.titleSmall),
          Wrap(
            spacing: 6,
            children: [
              for (final s in agentSpecialties)
                FilterChip(
                  label: Text(l10n.t('spec_$s')),
                  selected: _specialties.contains(s),
                  onSelected: (on) => setState(() {
                    if (!on) {
                      _specialties.remove(s);
                    } else if (_specialties.length < 10) {
                      _specialties.add(s);
                    }
                  }),
                ),
            ],
          ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('agent-profile-save'),
            onPressed: _busy ? null : _save,
            child: Text(l10n.t('save')),
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(child: Text(l10n.t('licenses'), style: text.titleMedium)),
              TextButton.icon(
                key: const Key('submit-license'),
                icon: const Icon(Icons.add),
                label: Text(l10n.t('submitLicense')),
                onPressed: () async {
                  final ok = await showModalBottomSheet<bool>(
                    context: context,
                    isScrollControlled: true,
                    useSafeArea: true,
                    builder: (_) => const _LicenseSheet(),
                  );
                  if (ok == true) {
                    ref.invalidate(myAgentProfileProvider);
                    if (context.mounted) showMessage(context, l10n.t('licenseSubmitted'));
                  }
                },
              ),
            ],
          ),
          if (p.licenses.isEmpty) Text(l10n.t('noLicenses')),
          for (final l in p.licenses)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.badge_outlined),
              title: Text('${l.typeLabel} · ${l.state} ${l.number}'),
              subtitle: Text(
                [
                  l10n.t('lic_${l.status}'),
                  if (l.expiresOn != null) l10n.t('expiresDate', {'date': formatCalendarDate(l.expiresOn, lang)}),
                  ?l.statusReason,
                ].join(' · '),
              ),
            ),
          Text(l10n.t('licenseReviewNote'), style: text.bodySmall),
        ],
      ),
    );
  }
}

class _LicenseSheet extends ConsumerStatefulWidget {
  const _LicenseSheet();

  @override
  ConsumerState<_LicenseSheet> createState() => _LicenseSheetState();
}

class _LicenseSheetState extends ConsumerState<_LicenseSheet> {
  final _formKey = GlobalKey<FormState>();
  final _number = TextEditingController();
  String _type = 'salesperson';
  DateTime? _issued;
  DateTime? _expires;
  Map<String, List<String>> _serverErrors = const {};
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _number.dispose();
    super.dispose();
  }

  static String _ymd(DateTime d) => d.toIso8601String().substring(0, 10);

  Future<void> _submit() async {
    setState(() {
      _serverErrors = const {};
      _error = null;
    });
    if (!_formKey.currentState!.validate()) return;
    if (_issued != null && _expires != null && !_expires!.isAfter(_issued!)) {
      return setState(() => _error = context.l10n.t('expiryAfterIssue'));
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(agentRepositoryProvider)
          .submitLicense(
            number: _number.text.trim(),
            type: _type,
            issuedOn: _issued == null ? null : _ymd(_issued!),
            expiresOn: _expires == null ? null : _ymd(_expires!),
          );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() {
        _serverErrors = e.fieldErrors;
        _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final now = DateTime.now();

    Future<DateTime?> pick(DateTime? current, DateTime first, DateTime last) => showDatePicker(
      context: context,
      firstDate: first,
      lastDate: last,
      initialDate: current ?? (now.isAfter(last) ? last : (now.isBefore(first) ? first : now)),
    );

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Form(
        key: _formKey,
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(l10n.t('submitLicense'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            TextFormField(
              key: const Key('license-number'),
              controller: _number,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: l10n.t('licenseNumber'),
                errorText: _serverErrors['license_number']?.first,
              ),
              validator: (v) {
                final t = v?.trim() ?? '';
                if (t.isEmpty) return l10n.t('required');
                return RegExp(r'^[A-Za-z0-9 -]{4,40}$').hasMatch(t) ? null : l10n.t('invalidLicenseNumber');
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: l10n.t('licenseType')),
              items: [for (final t in licenseTypes) DropdownMenuItem(value: t, child: Text(l10n.t('lt_$t')))],
              onChanged: (v) => setState(() => _type = v ?? _type),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.t('issuedOn')),
              subtitle: Text(_issued == null ? l10n.t('noDate') : formatDate(_issued, lang)),
              onTap: () async {
                final d = await pick(_issued, DateTime(now.year - 60), now);
                if (d != null) setState(() => _issued = d);
              },
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l10n.t('expiresOn')),
              subtitle: Text(_expires == null ? l10n.t('noDate') : formatDate(_expires, lang)),
              onTap: () async {
                final d = await pick(_expires, DateTime(now.year - 1), DateTime(now.year + 10));
                if (d != null) setState(() => _expires = d);
              },
            ),
            Text(l10n.t('licenseReviewNote'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('license-submit'),
              onPressed: _busy ? null : _submit,
              child: Text(l10n.t('submitForReview')),
            ),
          ],
        ),
      ),
    );
  }
}
