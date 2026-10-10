import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../../auth/presentation/validators.dart';
import '../data/availability_repository.dart';

/// Weekly showing hours and time off of the signed-in agent.
class AvailabilityScreen extends ConsumerWidget {
  const AvailabilityScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final availability = ref.watch(availabilityProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.t('availability'))),
      body: AsyncView<Availability>(
        value: availability,
        onRetry: () => ref.invalidate(availabilityProvider),
        data: (a) => _Editor(key: ValueKey(a), availability: a),
      ),
    );
  }
}

class _Editor extends ConsumerStatefulWidget {
  const _Editor({super.key, required this.availability});

  final Availability availability;

  @override
  ConsumerState<_Editor> createState() => _EditorState();
}

class _EditorState extends ConsumerState<_Editor> {
  late final List<AvailabilityWindow> _windows = [...widget.availability.windows];
  bool _busy = false;
  bool _dirty = false;

  static String _hhmm(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  static TimeOfDay _parse(String s) {
    final parts = s.split(':');
    return TimeOfDay(hour: int.tryParse(parts[0]) ?? 9, minute: int.tryParse(parts.elementAtOrNull(1) ?? '') ?? 0);
  }

  Future<void> _pickTime(int index, {required bool start}) async {
    final w = _windows[index];
    final picked = await showTimePicker(context: context, initialTime: _parse(start ? w.startTime : w.endTime));
    if (picked == null) return;
    setState(() {
      _windows[index] = start ? w.copyWith(startTime: _hhmm(picked)) : w.copyWith(endTime: _hhmm(picked));
      _dirty = true;
    });
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    if (_windows.any((w) => w.startTime.compareTo(w.endTime) >= 0) || overlappingWindows(_windows).isNotEmpty) {
      return showMessage(context, l10n.t('fixWindows'));
    }
    setState(() => _busy = true);
    try {
      await ref.read(availabilityRepositoryProvider).save(_windows);
      if (mounted) {
        setState(() => _dirty = false);
        showMessage(context, l10n.t('saved'));
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final text = Theme.of(context).textTheme;
    final bad = overlappingWindows(_windows);
    final error = Theme.of(context).colorScheme.error;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(l10n.t('availabilityIntro', {'zone': widget.availability.timezone})),
        for (var day = 1; day <= 7; day++) ...[
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: Text(l10n.t('weekday_$day'), style: text.titleSmall)),
              IconButton(
                tooltip: l10n.t('addHours'),
                icon: const Icon(Icons.add),
                onPressed: () => setState(() {
                  _windows.add(AvailabilityWindow(weekday: day, startTime: '09:00', endTime: '17:00'));
                  _dirty = true;
                }),
              ),
            ],
          ),
          if (!_windows.any((w) => w.weekday == day)) Text(l10n.t('unavailable'), style: text.bodySmall),
          for (final (i, w) in _windows.indexed)
            if (w.weekday == day)
              Row(
                children: [
                  TextButton(
                    onPressed: () => _pickTime(i, start: true),
                    child: Text(_parse(w.startTime).format(context)),
                  ),
                  const Text('–'),
                  TextButton(
                    onPressed: () => _pickTime(i, start: false),
                    child: Text(_parse(w.endTime).format(context)),
                  ),
                  if (bad.contains(i) || w.startTime.compareTo(w.endTime) >= 0)
                    Icon(Icons.error_outline, color: error, semanticLabel: l10n.t('fixWindows')),
                  const Spacer(),
                  IconButton(
                    tooltip: l10n.t('remove'),
                    icon: const Icon(Icons.delete_outline),
                    onPressed: () => setState(() {
                      _windows.removeAt(i);
                      _dirty = true;
                    }),
                  ),
                ],
              ),
        ],
        const SizedBox(height: 16),
        FilledButton(
          key: const Key('availability-save'),
          onPressed: _busy || !_dirty ? null : _save,
          child: Text(l10n.t('saveHours')),
        ),
        const Divider(height: 32),
        Row(
          children: [
            Expanded(child: Text(l10n.t('timeOff'), style: text.titleMedium)),
            TextButton.icon(
              icon: const Icon(Icons.add),
              label: Text(l10n.t('add')),
              onPressed: () async {
                final ok = await showModalBottomSheet<bool>(
                  context: context,
                  isScrollControlled: true,
                  builder: (_) => const _TimeOffSheet(),
                );
                if (ok == true) ref.invalidate(availabilityProvider);
              },
            ),
          ],
        ),
        if (widget.availability.timeOff.isEmpty) Text(l10n.t('noTimeOff'), style: text.bodySmall),
        for (final t in widget.availability.timeOff)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.beach_access_outlined),
            title: Text('${formatDateTime(t.startsAt, lang)} – ${formatDateTime(t.endsAt, lang)}'),
            subtitle: t.reason == null ? null : Text(t.reason!),
            trailing: IconButton(
              tooltip: l10n.t('remove'),
              icon: const Icon(Icons.delete_outline),
              onPressed: () async {
                try {
                  await ref.read(availabilityRepositoryProvider).removeTimeOff(t.id);
                  ref.invalidate(availabilityProvider);
                } catch (e) {
                  if (context.mounted) showError(context, e);
                }
              },
            ),
          ),
      ],
    );
  }
}

class _TimeOffSheet extends ConsumerStatefulWidget {
  const _TimeOffSheet();

  @override
  ConsumerState<_TimeOffSheet> createState() => _TimeOffSheetState();
}

class _TimeOffSheetState extends ConsumerState<_TimeOffSheet> {
  final _reason = TextEditingController();
  DateTime? _start;
  DateTime? _end;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<DateTime?> _pick(DateTime? current) async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 364)),
      initialDate: current ?? now,
    );
    if (date == null || !mounted) return null;
    final time = await showTimePicker(
      context: context,
      initialTime: current != null ? TimeOfDay.fromDateTime(current) : const TimeOfDay(hour: 9, minute: 0),
    );
    return time == null ? null : DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    if (_start == null || _end == null) return setState(() => _error = l10n.t('required'));
    if (!_end!.isAfter(_start!)) return setState(() => _error = l10n.t('endAfterStart'));
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(availabilityRepositoryProvider).addTimeOff(_start!, _end!, reason: nullIfBlank(_reason.text));
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.t('timeOff'), style: Theme.of(context).textTheme.titleLarge),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l10n.t('starts')),
            subtitle: Text(_start == null ? '—' : formatDateTime(_start, lang)),
            onTap: () async {
              final d = await _pick(_start);
              if (d != null) setState(() => _start = d);
            },
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l10n.t('ends')),
            subtitle: Text(_end == null ? '—' : formatDateTime(_end, lang)),
            onTap: () async {
              final d = await _pick(_end ?? _start);
              if (d != null) setState(() => _end = d);
            },
          ),
          TextField(
            controller: _reason,
            maxLength: 200,
            decoration: InputDecoration(labelText: l10n.t('reasonOptional')),
          ),
          if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 8),
          FilledButton(onPressed: _busy ? null : _save, child: Text(l10n.t('save'))),
        ],
      ),
    );
  }
}
