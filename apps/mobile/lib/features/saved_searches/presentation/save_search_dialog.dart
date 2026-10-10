import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/async_view.dart';
import '../../properties/domain/property.dart';
import '../data/saved_search_repository.dart';
import '../domain/saved_search.dart';

class SaveSearchDialog extends ConsumerStatefulWidget {
  const SaveSearchDialog({super.key, required this.filters});

  final SearchFilters filters;

  static Future<void> show(BuildContext context, SearchFilters filters) => showDialog<void>(
    context: context,
    builder: (_) => SaveSearchDialog(filters: filters),
  );

  @override
  ConsumerState<SaveSearchDialog> createState() => _SaveSearchDialogState();
}

class _SaveSearchDialogState extends ConsumerState<SaveSearchDialog> {
  late final _name = TextEditingController(text: widget.filters.locationLabel ?? '');
  String _frequency = 'daily';
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    if (_name.text.trim().length < 2) return setState(() => _error = l10n.t('nameMin'));
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(savedSearchRepositoryProvider).create(_name.text, widget.filters, _frequency);
      ref.invalidate(savedSearchesProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      showMessage(context, l10n.t('searchSaved'));
    } on ApiException catch (e) {
      setState(() => _error = e.fieldError('name') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return AlertDialog(
      title: Text(l10n.t('saveSearch')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            key: const Key('saved-search-name'),
            controller: _name,
            maxLength: 100,
            decoration: InputDecoration(labelText: l10n.t('name'), errorText: _error),
          ),
          DropdownButtonFormField<String>(
            initialValue: _frequency,
            decoration: InputDecoration(labelText: l10n.t('alerts')),
            items: [
              for (final f in SavedSearch.frequencies) DropdownMenuItem(value: f, child: Text(l10n.t('alert_$f'))),
            ],
            onChanged: (v) => setState(() => _frequency = v ?? 'daily'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l10n.t('cancel'))),
        TextButton(onPressed: _busy ? null : _save, child: Text(l10n.t('save'))),
      ],
    );
  }
}
