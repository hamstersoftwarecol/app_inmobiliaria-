import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/util/format.dart';
import '../../../shared/launch.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/crm_repository.dart';
import '../domain/lead.dart';

class LeadDetailScreen extends ConsumerWidget {
  const LeadDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final lead = ref.watch(leadProvider(id));

    return Scaffold(
      appBar: AppBar(title: Text(lead.value?.fullName ?? l10n.t('leads'))),
      body: AsyncView(
        value: lead,
        onRetry: () => ref.invalidate(leadProvider(id)),
        data: (l) => RefreshIndicator(
          onRefresh: () => ref.refresh(leadProvider(id).future),
          child: ListView(
            padding: const EdgeInsets.all(12),
            children: [
              Card(
                child: Column(
                  children: [
                    ListTile(
                      title: Text(l.fullName, style: Theme.of(context).textTheme.titleMedium),
                      subtitle: Text(
                        [l10n.t('source_${l.source}'), if (l.intent != null) l10n.t('intent_${l.intent}')].join(' · '),
                      ),
                    ),
                    if (l.phone != null)
                      ListTile(
                        leading: const Icon(Icons.phone_outlined),
                        title: Text(l.phone!),
                        onTap: () => openExternal(context, telUri(l.phone!)),
                      ),
                    if (l.email != null)
                      ListTile(
                        leading: const Icon(Icons.mail_outline),
                        title: Text(l.email!),
                        onTap: () => openExternal(context, mailUri(l.email!)),
                      ),
                    if (l.propertyTitle != null)
                      ListTile(leading: const Icon(Icons.home_outlined), title: Text(l.propertyTitle!)),
                    if (l.budgetMin != null || l.budgetMax != null)
                      ListTile(
                        leading: const Icon(Icons.attach_money),
                        title: Text('${formatPrice(l.budgetMin, lang)} – ${formatPrice(l.budgetMax, lang)}'),
                      ),
                    if (l.message != null) ListTile(leading: const Icon(Icons.notes), title: Text(l.message!)),
                  ],
                ),
              ),
              if (l.canUpdate) _StatusPicker(lead: l),
              if (l.canUpdate) _ActivityForm(leadId: l.id),
              const SizedBox(height: 8),
              Text(l10n.t('timeline'), style: Theme.of(context).textTheme.titleMedium),
              for (final a in l.activities)
                ListTile(
                  dense: true,
                  leading: Icon(_activityIcon(a.type)),
                  title: Text(a.body?.isNotEmpty == true ? a.body! : l10n.t('activity_${a.type}')),
                  subtitle: Text(
                    [l10n.t('activity_${a.type}'), ?a.userName, formatDateTime(a.occurredAt, lang)].join(' · '),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  static IconData _activityIcon(String type) => switch (type) {
    'call' => Icons.phone,
    'email' => Icons.mail,
    'meeting' => Icons.groups,
    'showing' => Icons.event,
    'status_change' => Icons.swap_horiz,
    'assignment' => Icons.person_add,
    'inquiry' => Icons.question_answer,
    _ => Icons.sticky_note_2_outlined,
  };
}

class _StatusPicker extends ConsumerWidget {
  const _StatusPicker({required this.lead});

  final Lead lead;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: DropdownButtonFormField<String>(
        key: const Key('lead-status'),
        initialValue: lead.status,
        decoration: InputDecoration(labelText: l10n.t('status')),
        items: [for (final s in Lead.statuses) DropdownMenuItem(value: s, child: Text(l10n.t('lead_$s')))],
        onChanged: (value) async {
          if (value == null || value == lead.status) return;
          String? reason;
          if (value == 'lost') {
            reason = await _askLostReason(context);
            if (reason == null) {
              ref.invalidate(leadProvider(lead.id));
              return;
            }
          }
          try {
            await ref.read(crmRepositoryProvider).setStatus(lead.id, value, lostReason: reason);
          } catch (e) {
            if (context.mounted) showError(context, e);
          }
          ref.invalidate(leadProvider(lead.id));
          ref.invalidate(leadsProvider);
        },
      ),
    );
  }

  Future<String?> _askLostReason(BuildContext context) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.l10n.t('lostReason')),
        content: TextField(controller: controller, maxLength: 500),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l10n.t('cancel'))),
          TextButton(
            onPressed: () => controller.text.trim().length >= 3 ? Navigator.pop(ctx, controller.text.trim()) : null,
            child: Text(ctx.l10n.t('save')),
          ),
        ],
      ),
    );
  }
}

class _ActivityForm extends ConsumerStatefulWidget {
  const _ActivityForm({required this.leadId});

  final int leadId;

  @override
  ConsumerState<_ActivityForm> createState() => _ActivityFormState();
}

class _ActivityFormState extends ConsumerState<_ActivityForm> {
  final _body = TextEditingController();
  String _type = 'note';
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_type == 'note' && _body.text.trim().isEmpty) return setState(() => _error = context.l10n.t('required'));
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(crmRepositoryProvider).logActivity(widget.leadId, _type, _body.text.trim());
      _body.clear();
      ref.invalidate(leadProvider(widget.leadId));
    } on ApiException catch (e) {
      setState(() => _error = e.fieldError('body') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.t('logActivity'), style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: [
                for (final t in Lead.manualActivities) ButtonSegment(value: t, label: Text(l10n.t('activity_$t'))),
              ],
              selected: {_type},
              onSelectionChanged: (s) => setState(() => _type = s.first),
            ),
            const SizedBox(height: 8),
            TextField(
              key: const Key('activity-body'),
              controller: _body,
              maxLines: 3,
              maxLength: 5000,
              decoration: InputDecoration(labelText: l10n.t('details'), errorText: _error),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: _busy ? null : _save,
                icon: const Icon(Icons.add),
                label: Text(l10n.t('add')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
