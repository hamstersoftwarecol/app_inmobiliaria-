import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/showing_repository.dart';

/// Pick a free slot and request a showing (the agent confirms it).
class RequestShowingSheet extends ConsumerStatefulWidget {
  const RequestShowingSheet({super.key, required this.slug});

  final String slug;

  static Future<bool?> show(BuildContext context, String slug) => showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (_) => RequestShowingSheet(slug: slug),
  );

  @override
  ConsumerState<RequestShowingSheet> createState() => _RequestShowingSheetState();
}

class _RequestShowingSheetState extends ConsumerState<RequestShowingSheet> {
  // One key per attempt at this sheet: retrying after a timeout replays the same request.
  final _idempotencyKey = newIdempotencyKey();
  final _notes = TextEditingController();
  DateTime? _slot;
  bool _consent = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    if (_slot == null) return setState(() => _error = l10n.t('pickTime'));
    if (!_consent) return setState(() => _error = l10n.t('consentRequired'));
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(showingRepositoryProvider)
          .request(
            slug: widget.slug,
            startsAt: _slot!,
            idempotencyKey: _idempotencyKey,
            notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
          );
      ref.invalidate(showingsProvider);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (e.code == 'slot_unavailable') ref.invalidate(showingSlotsProvider(widget.slug));
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final slots = ref.watch(showingSlotsProvider(widget.slug));

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.8),
        child: AsyncView(
          value: slots,
          onRetry: () => ref.invalidate(showingSlotsProvider(widget.slug)),
          data: (s) {
            if (!s.bookable || !s.hasSlots) return EmptyState(message: l10n.t('noSlots'), icon: Icons.event_busy);
            return ListView(
              shrinkWrap: true,
              children: [
                Text(l10n.t('requestShowing'), style: Theme.of(context).textTheme.titleLarge),
                Text(l10n.t('timesLocal'), style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 8),
                for (final day in s.days.where((d) => d.slots.isNotEmpty)) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Text(formatCalendarDate(day.date, lang), style: Theme.of(context).textTheme.titleSmall),
                  ),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final slot in day.slots)
                        ChoiceChip(
                          label: Text(TimeOfDay.fromDateTime(slot.toLocal()).format(context)),
                          selected: _slot == slot,
                          onSelected: (_) => setState(() => _slot = slot),
                        ),
                    ],
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  controller: _notes,
                  maxLength: 1000,
                  maxLines: 2,
                  decoration: InputDecoration(labelText: l10n.t('notesOptional')),
                ),
                CheckboxListTile(
                  key: const Key('showing-consent'),
                  contentPadding: EdgeInsets.zero,
                  value: _consent,
                  onChanged: (v) => setState(() => _consent = v ?? false),
                  title: Text(l10n.t('showingConsent')),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ),
                FilledButton(
                  key: const Key('showing-submit'),
                  onPressed: _busy ? null : _submit,
                  child: Text(
                    _slot == null
                        ? l10n.t('requestShowing')
                        : l10n.t('requestAt', {'time': formatDateTime(_slot, lang)}),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
