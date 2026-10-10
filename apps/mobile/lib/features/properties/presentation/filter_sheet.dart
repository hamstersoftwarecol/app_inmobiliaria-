import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/l10n/app_localizations.dart';
import '../domain/property.dart';

/// Bottom sheet editing the search filters; returns the new filters or `null`.
class FilterSheet extends StatefulWidget {
  const FilterSheet({super.key, required this.initial});

  final SearchFilters initial;

  static Future<SearchFilters?> show(BuildContext context, SearchFilters initial) =>
      showModalBottomSheet<SearchFilters>(
        context: context,
        isScrollControlled: true,
        builder: (_) => FilterSheet(initial: initial),
      );

  @override
  State<FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<FilterSheet> {
  late String? _type = widget.initial.listingType;
  late final _min = TextEditingController(text: widget.initial.minPrice?.toString() ?? '');
  late final _max = TextEditingController(text: widget.initial.maxPrice?.toString() ?? '');
  late int? _beds = widget.initial.minBeds;
  late int? _baths = widget.initial.minBaths;
  late String _sort = widget.initial.sort;
  String? _error;

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _apply() {
    final next = widget.initial.copyWith(
      listingType: () => _type,
      minPrice: () => int.tryParse(_min.text),
      maxPrice: () => int.tryParse(_max.text),
      minBeds: () => _beds,
      minBaths: () => _baths,
      sort: _sort,
    );
    final problem = next.validate();
    if (problem != null) return setState(() => _error = context.l10n.t(problem));
    Navigator.of(context).pop(next);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final digits = [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)];
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.t('filters'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            SegmentedButton<String?>(
              segments: [
                ButtonSegment(value: null, label: Text(l10n.t('any'))),
                ButtonSegment(value: 'sale', label: Text(l10n.t('forSale'))),
                ButtonSegment(value: 'rent', label: Text(l10n.t('forRent'))),
              ],
              selected: {_type},
              onSelectionChanged: (s) => setState(() => _type = s.first),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    key: const Key('filter-min-price'),
                    controller: _min,
                    keyboardType: TextInputType.number,
                    inputFormatters: digits,
                    decoration: InputDecoration(labelText: l10n.t('minPrice'), prefixText: r'$'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    key: const Key('filter-max-price'),
                    controller: _max,
                    keyboardType: TextInputType.number,
                    inputFormatters: digits,
                    decoration: InputDecoration(labelText: l10n.t('maxPrice'), prefixText: r'$', errorText: _error),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _countDropdown(l10n.t('minBeds'), _beds, (v) => setState(() => _beds = v))),
                const SizedBox(width: 12),
                Expanded(child: _countDropdown(l10n.t('minBaths'), _baths, (v) => setState(() => _baths = v))),
              ],
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _sort,
              decoration: InputDecoration(labelText: l10n.t('sortBy')),
              items: [for (final s in SearchFilters.sorts) DropdownMenuItem(value: s, child: Text(l10n.t('sort_$s')))],
              onChanged: (v) => setState(() => _sort = v ?? 'newest'),
            ),
            const SizedBox(height: 16),
            FilledButton(key: const Key('filter-apply'), onPressed: _apply, child: Text(l10n.t('showResults'))),
            TextButton(
              onPressed: () => Navigator.of(context).pop(
                const SearchFilters().copyWith(
                  city: () => widget.initial.city,
                  postalCode: () => widget.initial.postalCode,
                  locationLabel: () => widget.initial.locationLabel,
                ),
              ),
              child: Text(l10n.t('clearFilters')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _countDropdown(String label, int? value, ValueChanged<int?> onChanged) => DropdownButtonFormField<int?>(
    initialValue: value,
    decoration: InputDecoration(labelText: label),
    items: [
      DropdownMenuItem(value: null, child: Text(context.l10n.t('any'))),
      for (final n in [1, 2, 3, 4, 5]) DropdownMenuItem(value: n, child: Text('$n+')),
    ],
    onChanged: onChanged,
  );
}
